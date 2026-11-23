<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Support\Feature;
use App\Support\Mailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamController extends Controller
{
    /** Saudi IBAN: 'SA' + 22 digits (24 characters total) — see IBAN Registry / SAMA format. */
    private const IBAN_PATTERN = '/^SA\d{22}$/';

    /** Saudi national ID (citizens) and Iqama (residents): exactly 10 digits, starting with 1 or 2. */
    private const NATIONAL_ID_PATTERN = '/^[12]\d{9}$/';

    /**
     * The exact 6 abilities Gate::define()s in AppServiceProvider — the only abilities a
     * per-user permission_overrides entry may touch. Keep this in lockstep with that file;
     * this is deliberately NOT a general-purpose ability registry.
     */
    public const OVERRIDABLE_ABILITIES = [
        'write', 'manage_team', 'manage_company_settings',
        'manage_business_setup', 'approve_documents', 'manage_billing',
    ];

    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $members = User::where('company_id', $companyId)->orderBy('created_at')->get();

        return view('app.team.index', [
            'members' => $members->toArray(),
            'userLimit' => Feature::userLimit(),
            'withinUserLimit' => Feature::withinUserLimit(),
            'payrollReadyCount' => $members->filter(fn (User $m) => $m->hasPayrollData())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        if (!Feature::withinUserLimit()) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.team.member_limit_reached'));
        }

        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $role = (string) $request->input('role', 'estimator');
        if (!array_key_exists($role, User::ASSIGNABLE_ROLES)) {
            $role = 'estimator';
        }

        // users.name/email are both string(150) — a name over that is truncated (same
        // mb_substr-to-column-width convention used everywhere else in this app), but an
        // over-length email is rejected outright rather than silently truncated into a
        // different, wrong address.
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.valid_name_email_required'));
        }
        if (User::where('email', $email)->exists()) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.email_exists'));
        }

        $tempPassword = bin2hex(random_bytes(4));
        $companyId = Auth::user()->company_id;
        User::create([
            'company_id' => $companyId,
            'name' => mb_substr($name, 0, 150),
            'email' => $email,
            'password' => Hash::make($tempPassword),
            'role' => $role,
            'status' => 'active',
        ]);

        if (Mailer::isConfigured()) {
            $company = Company::find($companyId);
            $result = Mailer::send(
                $email,
                $name,
                "You've been invited to {$company->name} on " . Setting::siteName(),
                "Hi {$name},\n\nYou've been added to {$company->name}'s " . Setting::siteName() . " account.\n\nLog in at " . url('/login') . "\nEmail: {$email}\nTemporary password: {$tempPassword}\n\nPlease change your password after logging in."
            );
            $this->flash($result['ok'] ? 'success' : 'error', $result['ok']
                ? t('user.team.invited_with_email', ['email' => $email])
                : t('user.team.invited_email_failed', ['reason' => $result['error'], 'password' => $tempPassword]));
        } else {
            $this->flash('success', t('user.team.invited_no_email', ['password' => $tempPassword]));
        }
        return redirect('/app/team');
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwned($id);

        if ($member->role === 'owner') {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.owner_role_immutable'));
        }

        $role = (string) $request->input('role', 'estimator');
        if (!array_key_exists($role, User::ASSIGNABLE_ROLES)) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.invalid_role'));
        }

        $member->update(['role' => $role]);
        $this->flash('success', t('user.team.role_updated', ['name' => $member->name]));
        return redirect('/app/team');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwned($id);
        if ($member->id === Auth::id()) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.cannot_remove_self'));
        }
        if ($member->role === 'owner') {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.owner_cannot_be_removed'));
        }
        $member->delete();
        $this->flash('success', t('user.team.removed'));
        return redirect('/app/team');
    }

    /**
     * Sets this one member's permission_overrides — the per-user escape hatch that can
     * grant or deny one of the 6 fixed Gate abilities beyond what their role normally
     * allows (see AppServiceProvider::boot()/User::permissionOverride()). Two hard
     * security guards, both non-negotiable: findOwned() already confines $id to the
     * caller's own company (tenant isolation), and the 'owner' role is never overridable
     * — its abilities are fixed/always-everything. A non-owner is also blocked from
     * setting overrides on their OWN record, so e.g. an admin (who already has
     * manage_team) can't use this route to quietly grant themselves manage_billing.
     */
    public function updatePermissions(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        if ($redirect = $this->requireFeature('granular_permissions')) {
            return $redirect;
        }
        $member = $this->findOwned($id);

        if ($member->role === 'owner') {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team_permissions.owner_not_overridable'));
        }
        if ($member->id === Auth::id()) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team_permissions.cannot_edit_own'));
        }

        $overrides = [];
        foreach (self::OVERRIDABLE_ABILITIES as $ability) {
            $choice = (string) $request->input("overrides.{$ability}", 'default');
            if ($choice === 'allow') {
                $overrides[$ability] = true;
            } elseif ($choice === 'deny') {
                $overrides[$ability] = false;
            }
            // anything else ('default', or an unrecognized value) leaves this ability
            // out of the map entirely, so it keeps falling through to the role default.
        }

        $member->update(['permission_overrides' => $overrides === [] ? null : $overrides]);
        $this->flash('success', t('user.team_permissions.updated', ['name' => $member->name]));
        return redirect('/app/team');
    }

    public function editPayroll(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        return view('app.team.payroll', ['member' => $this->findOwned($id)]);
    }

    public function updatePayroll(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwned($id);
        $redirectPath = "/app/team/{$member->id}/payroll";

        $iban = strtoupper(str_replace(' ', '', (string) $request->input('bank_iban', '')));
        if ($iban !== '' && !preg_match(self::IBAN_PATTERN, $iban)) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.team.iban_invalid'));
        }

        $nationalId = trim((string) $request->input('national_id', ''));
        if ($nationalId !== '' && !preg_match(self::NATIONAL_ID_PATTERN, $nationalId)) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.team.national_id_invalid'));
        }

        // decimal(12,2) for the three monthly figures, decimal(8,2) for hourly_rate — each
        // rejected (not silently cast to 0/negative-allowed) when non-numeric/out of range,
        // capped at its own column's real ceiling.
        $amounts = [];
        foreach ([
            'basic_salary' => 9999999999.99,
            'housing_allowance' => 9999999999.99,
            'other_earnings' => 9999999999.99,
            'hourly_rate' => 999999.99,
        ] as $field => $max) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '') {
                $amounts[$field] = null;
                continue;
            }
            $value = $this->validatedNumber($raw, 0, $max);
            if ($value === null) {
                return $this->redirectWithFlash($redirectPath, 'error', t('user.team.salary_amounts_invalid'));
            }
            $amounts[$field] = $value;
        }

        $member->update([
            'national_id' => $nationalId ?: null,
            'nationality' => mb_substr(trim((string) $request->input('nationality', '')), 0, 100) ?: null,
            'bank_iban' => $iban ?: null,
            'bank_name' => mb_substr(trim((string) $request->input('bank_name', '')), 0, 150) ?: null,
            ...$amounts,
        ]);

        $this->flash('success', t('user.team.payroll_updated', ['name' => $member->name]));
        return redirect($redirectPath);
    }

    /**
     * Streams a CSV of this company's payroll data laid out like a WPS Salary
     * Information File (SIF) — the field set commonly published for Saudi
     * Arabia's Wage Protection System (filed via Mudad or a bank's own
     * portal): employer establishment ID, employee national ID/iqama, name,
     * IBAN, bank name, payment date, basic wage, housing allowance, other
     * earnings, deductions, and net wage.
     *
     * This is NOT a byte-perfect, bank-certified WPS upload file — each bank
     * (Mudad, SAB, Al Rajhi, etc.) has its own exact column order, delimiter,
     * and header conventions for the file its own portal accepts. Treat this
     * as a real, correct salary breakdown a bookkeeper can adapt or re-map
     * into their bank's specific WPS uploader, not a drop-in replacement
     * for it. There is no "deductions" field collected anywhere in this app
     * yet, so that column is always 0.00 and Net Wage equals the gross
     * (basic + housing + other) until deductions tracking exists.
     */
    public function exportWps(): Response|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);

        // Only members with a basic salary on file get a row — exporting someone with no
        // payroll data as a $0 wage line would look like a real (and wrong) zero-pay entry.
        $members = User::where('company_id', $companyId)->whereNotNull('basic_salary')->orderBy('name')->get();

        if ($members->isEmpty()) {
            return $this->redirectWithFlash('/app/team', 'error', t('user.team.no_payroll_data'));
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, [
            'Employer MOL Establishment ID', 'Employee National ID/Iqama', 'Employee Name', 'IBAN', 'Bank Name',
            'Payment Date', 'Basic Wage', 'Housing Allowance', 'Other Earnings', 'Deductions', 'Net Wage',
        ]);
        $paymentDate = now()->format('Y-m-d');
        foreach ($members as $m) {
            $deductions = 0.0;
            fputcsv($csv, [
                $company->mol_establishment_number ?? '',
                $m->national_id ?? '',
                $m->name,
                $m->bank_iban ?? '',
                $m->bank_name ?? '',
                $paymentDate,
                number_format((float) $m->basic_salary, 2, '.', ''),
                number_format((float) $m->housing_allowance, 2, '.', ''),
                number_format((float) $m->other_earnings, 2, '.', ''),
                number_format($deductions, 2, '.', ''),
                number_format($m->grossWage() - $deductions, 2, '.', ''),
            ]);
        }
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="wps-payroll-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    private function findOwned(int $id): User
    {
        $member = User::find($id);
        abort_if(!$member || $member->company_id !== Auth::user()->company_id, 404, 'Team member not found.');
        return $member;
    }

    /** Rejects a non-numeric or out-of-range value instead of silently casting/clamping it — see EstimateController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }
}
