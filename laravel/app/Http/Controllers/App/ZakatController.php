<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ZakatCalculation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * A Zakat ESTIMATE/worksheet for internal planning — not an authoritative tax
 * filing tool. BuildXact has no general ledger/chart of accounts, so unlike a
 * full accounting system this never derives equity or deductions from the
 * company's own invoice/vendor-bill/project data: the user types in the handful
 * of balance-sheet figures the standard formula needs, and the only thing this
 * controller computes is the zakat_base/zakat_due arithmetic from those inputs.
 */
class ZakatController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $calculations = ZakatCalculation::where('company_id', $companyId)
            ->orderByDesc('period_end_date')
            ->orderByDesc('id')
            ->get();

        return view('app.zakat.index', ['calculations' => $calculations]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }

        return view('app.zakat.create');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }

        $periodEndDate = $request->input('period_end_date');
        if (!$periodEndDate || strtotime($periodEndDate) === false) {
            return $this->redirectWithFlash('/app/zakat/create', 'error', t('user.zakat.period_end_date_required'));
        }

        $rateType = $request->input('rate_type') === 'gregorian' ? 'gregorian' : 'hijri';

        // The only figures this tool ever computes are the base/due amounts below —
        // every input is taken at face value from the user's own manual entry, never
        // derived from BuildXact's own transaction data.
        $equityAmount = (float) $request->input('equity_amount', 0);
        $longTermLiabilities = (float) $request->input('long_term_liabilities', 0);
        $netFixedAssets = (float) $request->input('net_fixed_assets', 0);
        $otherDeductions = (float) $request->input('other_deductions', 0);

        $zakatBase = max(0, $equityAmount + $longTermLiabilities - $netFixedAssets - $otherDeductions);
        $zakatDue = $zakatBase * ZakatCalculation::rate($rateType);

        $calculation = ZakatCalculation::create([
            'company_id' => Auth::user()->company_id,
            'created_by' => Auth::id(),
            'period_end_date' => $periodEndDate,
            'rate_type' => $rateType,
            'equity_amount' => $equityAmount,
            'long_term_liabilities' => $longTermLiabilities,
            'net_fixed_assets' => $netFixedAssets,
            'other_deductions' => $otherDeductions,
            'zakat_base' => $zakatBase,
            'zakat_due' => $zakatDue,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ]);

        return $this->redirectWithFlash('/app/zakat/' . $calculation->id, 'success', t('user.zakat.saved'));
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }
        $calculation = $this->findOwned($id);

        return view('app.zakat.show', ['calculation' => $calculation]);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $calculation = $this->findOwned($id);
        $calculation->delete();

        return $this->redirectWithFlash('/app/zakat', 'success', t('user.zakat.deleted'));
    }

    public function pdf(Request $request, int $id): Response
    {
        if ($redirect = $this->requireFeature('zakat')) {
            return $redirect;
        }
        $calculation = $this->findOwned($id);
        $company = Company::find($calculation->company_id);
        $lang = $request->input('lang') === 'ar' ? 'ar' : app()->getLocale();
        $rateLabel = $calculation->rate_type === 'gregorian' ? '2.5775%' : '2.5%';

        $items = [
            [
                'description' => $lang === 'ar' ? 'قيمة حقوق الملكية' : 'Equity amount',
                'qty' => 1,
                'unit_price' => (float) $calculation->equity_amount,
                'total' => (float) $calculation->equity_amount,
            ],
            [
                'description' => $lang === 'ar' ? 'الالتزامات طويلة الأجل' : 'Long-term liabilities',
                'qty' => 1,
                'unit_price' => (float) $calculation->long_term_liabilities,
                'total' => (float) $calculation->long_term_liabilities,
            ],
            [
                'description' => $lang === 'ar' ? 'صافي الأصول الثابتة (خصم)' : 'Net fixed assets (deduction)',
                'qty' => 1,
                'unit_price' => -(float) $calculation->net_fixed_assets,
                'total' => -(float) $calculation->net_fixed_assets,
            ],
            [
                'description' => $lang === 'ar' ? 'خصومات أخرى' : 'Other deductions (deduction)',
                'qty' => 1,
                'unit_price' => -(float) $calculation->other_deductions,
                'total' => -(float) $calculation->other_deductions,
            ],
            [
                'description' => $lang === 'ar'
                    ? 'وعاء الزكاة (المجموع أعلاه، بحد أدنى صفر)'
                    : 'Zakat base (sum above, floored at zero)',
                'qty' => 1,
                'unit_price' => (float) $calculation->zakat_base,
                'total' => (float) $calculation->zakat_base,
            ],
        ];

        $notes = $lang === 'ar'
            ? "المستحق للزكاة = وعاء الزكاة × النسبة = " . number_format((float) $calculation->zakat_base, 2)
                . " × {$rateLabel} = " . number_format((float) $calculation->zakat_due, 2) . " ريال."
                . ($calculation->notes ? "\n\nملاحظات: " . $calculation->notes : '')
            : "Zakat Due = Zakat Base x Rate = " . number_format((float) $calculation->zakat_base, 2)
                . " x {$rateLabel} = " . number_format((float) $calculation->zakat_due, 2) . " SAR."
                . ($calculation->notes ? "\n\nNotes: " . $calculation->notes : '');

        return $this->streamPdf([
            'template' => 'modern',
            'lang' => $lang,
            'currency' => 'SAR',
            'docType' => $lang === 'ar' ? 'ورقة عمل تقدير الزكاة — تقدير للتخطيط الداخلي فقط' : 'Zakat Estimate Worksheet — ESTIMATE, FOR INTERNAL PLANNING ONLY',
            'docNumber' => 'ZK-' . $calculation->id,
            'docDate' => $calculation->period_end_date,
            'issuer' => ['name' => $company->name ?? '', 'meta' => array_filter([$company->phone ?? null, ($company->vat_number ?? null) ? 'VAT: ' . $company->vat_number : null, ($company->cr_number ?? null) ? 'CR: ' . $company->cr_number : null])],
            'companyNameAr' => $company->name_ar ?? '',
            'companyLogo' => !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null,
            'items' => $items,
            'subtotal' => (float) $calculation->zakat_base,
            'discountPercent' => 0,
            'discountAmount' => 0,
            'total' => (float) $calculation->zakat_due,
            'notes' => $notes,
            'qrCode' => null,
            'footerNote' => $lang === 'ar'
                ? 'تقدير — لأغراض التخطيط الداخلي فقط. ليس بديلاً عن إقرار الزكاة الفعلي المقدَّم عبر بوابة هيئة الزكاة والضريبة والجمارك (زاتكا). يُرجى مراجعة محاسب/مدقق الشركة.'
                : 'ESTIMATE — FOR INTERNAL PLANNING ONLY. Not a substitute for the actual Zakat return filed through ZATCA\'s own portal. Consult your company\'s accountant/auditor.',
        ], 'Zakat-Estimate-' . $calculation->id . '.pdf');
    }

    private function findOwned(int $id): ZakatCalculation
    {
        $calculation = ZakatCalculation::find($id);
        abort_if(!$calculation || $calculation->company_id !== Auth::user()->company_id, 404, 'Zakat estimate not found.');
        return $calculation;
    }
}
