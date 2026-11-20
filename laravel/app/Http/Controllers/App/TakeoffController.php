<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Material;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Support\AiTakeoffAnalyzer;
use App\Support\TakeoffPdfConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TakeoffController extends Controller
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

    /** An Autocount reference box smaller than this on either side can't plausibly be a cropped symbol icon. */
    private const AUTOCOUNT_MIN_BOX_PX = 10;

    /** An Autocount reference box covering more than this fraction of the page's area is a room, not a single repeated symbol — see clampedAutocountBox(). */
    private const AUTOCOUNT_MAX_BOX_AREA_FRACTION = 0.5;

    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $takeoffs = DB::table('takeoffs as t')
            ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
            ->where('t.company_id', $companyId)
            ->orderByDesc('t.created_at')
            ->select('t.*', 'p.name as project_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.takeoffs.index', ['takeoffs' => $takeoffs]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        return view('app.takeoffs.create', [
            'projects' => Project::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
        ]);
    }

    /**
     * Accepts either a single image (wrapped as one page, exactly like before this takeoff
     * tool supported multi-page plan sets) or a PDF of up to TakeoffPdfConverter::MAX_PAGES
     * pages (converted server-side into one page per sheet). Either way, every page this
     * takeoff ends up with is a TakeoffPage row — takeoffs.plan_image_path etc. are left null
     * for a takeoff created here; see Takeoff's own docblock.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $name = trim((string) $request->input('name'));

        if ($name === '') {
            return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.name_required'));
        }

        $uploaded = $request->file('plan_image');
        $isPdf = false;

        if ($uploaded && $uploaded->isValid()) {
            $mime = $uploaded->getMimeType();
            if ($mime === 'application/pdf' || strtolower((string) $uploaded->getClientOriginalExtension()) === 'pdf') {
                $isPdf = true;
                if ($uploaded->getSize() > self::MAX_PDF_BYTES) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_max_size'));
                }
                if (!TakeoffPdfConverter::isAvailable()) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_conversion_unavailable'));
                }
            } elseif (isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
                if ($uploaded->getSize() > self::MAX_IMAGE_BYTES) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.image_max_size'));
                }
            } else {
                return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.image_type_invalid'));
            }
        }

        $projectId = $request->input('project_id') ?: null;
        if ($projectId) {
            $project = Project::find((int) $projectId);
            if (!$project || $project->company_id !== $companyId) {
                $projectId = null;
            }
        }

        $takeoff = Takeoff::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'name' => mb_substr($name, 0, 150),
        ]);

        if ($uploaded && $uploaded->isValid()) {
            $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");

            if ($isPdf) {
                $result = TakeoffPdfConverter::convert($uploaded->getRealPath(), $destDir);
                if (!$result['success']) {
                    $takeoff->delete();
                    @rmdir($destDir);
                    if ($result['error'] === 'too_many_pages') {
                        return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_too_many_pages', ['max' => TakeoffPdfConverter::MAX_PAGES]));
                    }
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_conversion_failed'));
                }

                $pageNumber = 1;
                foreach ($result['files'] as $filename) {
                    TakeoffPage::create([
                        'takeoff_id' => $takeoff->id,
                        'page_number' => $pageNumber,
                        'image_path' => "/uploads/takeoffs/{$companyId}/{$takeoff->id}/{$filename}",
                        'scale_px_per_unit' => 1,
                        'scale_unit' => 'm',
                    ]);
                    $pageNumber++;
                }
            } else {
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $ext = self::ALLOWED_IMAGE_MIMES[$uploaded->getMimeType()];
                $filename = 'page-1.' . $ext;
                $uploaded->move($destDir, $filename);

                TakeoffPage::create([
                    'takeoff_id' => $takeoff->id,
                    'page_number' => 1,
                    'image_path' => "/uploads/takeoffs/{$companyId}/{$takeoff->id}/{$filename}",
                    'scale_px_per_unit' => 1,
                    'scale_unit' => 'm',
                ]);
            }
        }

        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    /**
     * Renders the canvas for one page of the takeoff — the one named by ?page_id=, or the
     * first page when that's missing/invalid/absent (which is every single-image takeoff,
     * since it only ever has one page). The measurements list and its total are scoped to
     * that one page; grandTotalCost is the whole takeoff's total across every page, for the
     * page picker / convert-to-estimate context.
     */
    public function show(Request $request, int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        // The page picker/thumbnail strip only ever lists CURRENT pages — a sheet with a
        // revision history (see TakeoffPage's own docblock) otherwise shows up twice, once
        // per version, which would be confusing. A specific OLD (superseded) revision is
        // still reachable read-only via ?page_id=, just not listed here.
        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->where('is_current', true)->orderBy('page_number')->get();

        $currentPage = null;
        $requestedPageId = (int) $request->query('page_id', 0);
        if ($requestedPageId) {
            $currentPage = TakeoffPage::where('takeoff_id', $takeoff->id)->where('id', $requestedPageId)->first();
        }
        if (!$currentPage) {
            $currentPage = $pages->first();
        }

        // The chain of every revision of the sheet being viewed (oldest first), and whether
        // the one actually being viewed is a superseded (read-only, historical) revision —
        // see show.blade.php for how both drive the "Revision history" panel and the
        // read-only lockout of the measuring tools on an old revision.
        $versionChain = $currentPage ? array_map(fn (TakeoffPage $p) => $p->toArray(), $currentPage->versionChain()) : [];
        $isViewingOldRevision = $currentPage && !$currentPage->is_current;
        $canCompareRevision = $currentPage && $currentPage->supersedes_id !== null;
        // The page a freshly-uploaded revision should attach to / the page "Upload Revised
        // Plan" posts against — always the CURRENT row of this sheet's chain, even while
        // viewing an old revision (there is never more than one current row per chain).
        $currentVersionPage = $currentPage
            ? (collect($versionChain)->firstWhere('is_current', true) ?? $currentPage->toArray())
            : null;

        $pageMeasurements = $currentPage
            ? TakeoffMeasurement::where('takeoff_page_id', $currentPage->id)->orderBy('id')->get()
            : collect();
        $labelsById = $pageMeasurements->pluck('label', 'id');

        // A cutout's own value/total_cost only exist to reduce its parent's net value — it is
        // never its own line item in the page total (see convertToEstimate() for the same
        // exclusion). The parent row's value/total_cost are already the adjusted net figures,
        // so they need no special-casing here.
        $measurements = $pageMeasurements->map(function (TakeoffMeasurement $m) use ($labelsById) {
            $row = $m->toArray();
            $row['parent_label'] = $m->is_cutout && $m->parent_measurement_id
                ? $labelsById->get($m->parent_measurement_id)
                : null;
            $row['cutout_total'] = $m->gross_value !== null ? (float) $m->gross_value - (float) $m->value : null;
            return $row;
        })->all();
        $totalCost = array_sum(array_map(fn ($m) => $m['is_cutout'] ? 0.0 : (float) $m['total_cost'], $measurements));
        $grandTotalCost = (float) TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('is_cutout', false)->sum('total_cost');

        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $takeoff->company_id)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.takeoffs.show', [
            'takeoff' => $takeoff->toArray(),
            'pages' => $pages->toArray(),
            'currentPage' => $currentPage?->toArray(),
            'measurements' => $measurements,
            'totalCost' => $totalCost,
            'grandTotalCost' => $grandTotalCost,
            'materials' => $materials,
            'assemblies' => $this->assembliesForPicker($takeoff->company_id),
            'aiConfigured' => AiTakeoffAnalyzer::isConfigured(),
            'versionChain' => $versionChain,
            'isViewingOldRevision' => $isViewingOldRevision,
            'canCompareRevision' => $canCompareRevision,
            'currentVersionPage' => $currentVersionPage,
        ]);
    }

    /**
     * The company's active assemblies with their items eager-loaded, for the "link an
     * assembly" dropdown in the pending-measurement panel (show.blade.php) — the same data
     * EstimateController::assembliesWithItemsFor() serves the Estimate-side picker, plus
     * each assembly's computed_unit_cost (sum of qty_per_unit * unit_cost across its items —
     * cost per 1 unit of base_uom) so the page can auto-fill the unit-cost field without a
     * round trip.
     */
    private function assembliesForPicker(int $companyId): array
    {
        return Assembly::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('items')
            ->orderBy('name')
            ->get()
            ->map(function (Assembly $a) {
                $row = $a->toArray();
                $row['computed_unit_cost'] = round($a->items->sum(fn ($it) => (float) $it->qty_per_unit * (float) $it->unit_cost), 2);
                return $row;
            })
            ->all();
    }

    /**
     * "Upload Revised Plan" — mirrors DocumentController::uploadVersion()'s exact shape,
     * just adapted to a takeoff page instead of a document (see TakeoffPage's own docblock
     * for the identical version/supersedes_id/is_current columns): creates a NEW TakeoffPage
     * row pointing supersedes_id at $pageId, with version = old version + 1, is_current =
     * true, and the SAME page_number/takeoff_id as the page being revised; marks the old row
     * is_current = false. Both writes happen inside one DB::transaction, exactly like the
     * Document precedent.
     *
     * $pageId's own existing measurements are deliberately left exactly where they are —
     * attached to the OLD, now-superseded row — never migrated or re-projected onto the new
     * page's coordinate space, since the revised drawing may have shifted things and a
     * wrong auto-carried-over pin would be actively misleading (see this batch's own
     * project notes). The new page starts with zero measurements; the user re-measures
     * against the new drawing with the ordinary tools.
     */
    public function reviseUpload(Request $request, int $id, int $pageId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $old = $this->findOwnedPage($takeoff, $pageId);
        $redirectUrl = '/app/takeoffs/' . $takeoff->id . '?page_id=' . $old->id;

        $uploaded = $request->file('plan_image');
        if (!$uploaded || !$uploaded->isValid()) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.revision_image_required'));
        }

        // Same per-image validation rules as store()'s own image branch (not duplicated
        // differently) — jpeg/png/webp, MAX_IMAGE_BYTES.
        $mime = $uploaded->getMimeType();
        if (!isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.image_type_invalid'));
        }
        if ($uploaded->getSize() > self::MAX_IMAGE_BYTES) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.image_max_size'));
        }

        $companyId = $takeoff->company_id;
        $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $ext = self::ALLOWED_IMAGE_MIMES[$mime];
        $newVersion = (int) $old->version + 1;
        $filename = 'page-' . $old->page_number . '-v' . $newVersion . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $uploaded->move($destDir, $filename);
        $imagePath = "/uploads/takeoffs/{$companyId}/{$takeoff->id}/{$filename}";

        $new = null;
        DB::transaction(function () use (&$new, $old, $takeoff, $imagePath, $newVersion) {
            $new = TakeoffPage::create([
                'takeoff_id' => $takeoff->id,
                'page_number' => $old->page_number,
                'image_path' => $imagePath,
                // Carried over from the old page as a sensible starting default — the user
                // can recalibrate the new sheet with the existing Calibrate Scale tool if
                // the revised drawing was reprinted at a different scale.
                'scale_px_per_unit' => $old->scale_px_per_unit,
                'scale_unit' => $old->scale_unit,
                'version' => $newVersion,
                'supersedes_id' => $old->id,
                'is_current' => true,
            ]);

            $old->update(['is_current' => false]);
        });

        $this->flash('success', t('user.takeoffs.revision_uploaded'));
        return redirect('/app/takeoffs/' . $takeoff->id . '?page_id=' . $new->id);
    }

    /**
     * Plan revision comparison — available only when $pageId has something to compare
     * against (its own supersedes_id, set once a revision has been uploaded via
     * reviseUpload() above). Sends both the old and new page images to Claude vision in one
     * call via AiTakeoffAnalyzer::describeRevisionChanges() (see that method's own docblock
     * for its conservative framing) and hands back its plain-language findings for
     * show.blade.php to render as a simple, clearly AI-assisted list — never a saved
     * measurement or any other persisted row.
     */
    public function compareRevision(int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        if (!$page->supersedes_id) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.compare_no_previous_version')], 422);
        }
        $old = TakeoffPage::find($page->supersedes_id);
        if (!$old || $old->takeoff_id !== $takeoff->id) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.compare_no_previous_version')], 422);
        }

        if (!AiTakeoffAnalyzer::isConfigured()) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.ai_not_configured')], 422);
        }

        $findings = AiTakeoffAnalyzer::describeRevisionChanges($old->image_path, $page->image_path);

        if ($findings === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.ai_failed', ['reason' => $lastError])], 422);
        }

        return response()->json(['ok' => true, 'findings' => $findings]);
    }

    public function calibrate(Request $request, int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        // Defense in depth, same posture as autocount()'s own comment below — the UI never
        // offers this tool on a superseded (historical, read-only) revision, but the server
        // rejects it too rather than relying solely on the client hiding the button.
        if (!$page->is_current) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')], 422);
        }

        $pixelDistance = $this->validatedNumber($request->input('pixel_distance', 0), 0.01, 999999999);
        $realLength = $this->validatedNumber($request->input('real_length', 0), 0.01, 999999999);

        if ($pixelDistance === null || $realLength === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid calibration values.'], 422);
        }

        $page->update(['scale_px_per_unit' => $pixelDistance / $realLength]);
        return response()->json(['ok' => true, 'scale' => $pixelDistance / $realLength]);
    }

    /**
     * "Autocount" — mirrors STACK Construction Technologies' own repeated-symbol-detection
     * feature (see AiTakeoffAnalyzer::findMatchingSymbols()'s own docblock for the full
     * picture): the client draws a box around ONE instance of a repeated plan symbol (2-click
     * rectangle, same interaction pattern as calibrate() above), and this crops that box out
     * of the page's own image file with GD, sends both the crop and the full page to Claude
     * vision, and hands back a list of candidate match points — never saved as a measurement
     * here. This is pattern matching, not reliable detection, so the client is the one that
     * puts every point through a Found/Excluded review before a human decides to save any of
     * them (via the ordinary addMeasurement() above, type=count) — this endpoint itself never
     * writes a TakeoffMeasurement.
     */
    public function autocount(Request $request, int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        // Defense in depth, same posture as calibrate()'s own guard — a superseded
        // (historical, read-only) revision is never offered this tool in the UI, but the
        // server rejects it too.
        if (!$page->is_current) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')], 422);
        }

        // Box validation is deliberately checked before isConfigured() below — it's a cheap,
        // page-specific, deterministic check with nothing to do with whether AI is set up
        // platform-wide, so a badly-drawn box is reported as such even when AI isn't
        // configured yet (the client already disables this tool entirely in that case; this
        // is defense in depth, not the primary gate).
        $fullImagePath = public_path($page->image_path);
        $imageSize = is_file($fullImagePath) ? @getimagesize($fullImagePath) : false;
        if ($imageSize === false) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.autocount_image_missing')], 422);
        }
        [$imgWidth, $imgHeight] = $imageSize;

        $box = $this->clampedAutocountBox($request, $imgWidth, $imgHeight);
        if ($box === null) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.autocount_box_invalid')], 422);
        }

        if (!AiTakeoffAnalyzer::isConfigured()) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.ai_not_configured')], 422);
        }

        $cropPath = $this->cropAutocountReference($fullImagePath, $box, $takeoff->company_id, $takeoff->id);
        if ($cropPath === null) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.autocount_crop_failed')], 422);
        }

        try {
            $matches = AiTakeoffAnalyzer::findMatchingSymbols($page->image_path, $cropPath);
        } finally {
            // Transient AI input file, same cleanup discipline as every other temp file this
            // tool creates for a single AI call — removed whether the call succeeded or not.
            @unlink($cropPath);
        }

        if ($matches === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.ai_failed', ['reason' => $lastError])], 422);
        }

        return response()->json(['ok' => true, 'points' => $matches]);
    }

    /**
     * Validates the client-drawn Autocount reference box and clamps it into the page image's
     * actual pixel bounds (an off-canvas click, a box that starts at a negative coordinate,
     * etc. is clamped rather than rejected outright) — then rejects the clamped result only
     * if it's too small to plausibly be a cropped symbol icon, or covers too much of the page
     * to be a single repeated symbol rather than a room. Returns null for either rejection.
     *
     * @return ?array{x:int,y:int,width:int,height:int}
     */
    private function clampedAutocountBox(Request $request, int $imgWidth, int $imgHeight): ?array
    {
        $x = $this->validatedNumber($request->input('x', 0), -999999, 999999);
        $y = $this->validatedNumber($request->input('y', 0), -999999, 999999);
        $width = $this->validatedNumber($request->input('width', 0), 0.01, 999999);
        $height = $this->validatedNumber($request->input('height', 0), 0.01, 999999);

        if ($x === null || $y === null || $width === null || $height === null) {
            return null;
        }

        $left = max(0.0, min($x, (float) $imgWidth));
        $top = max(0.0, min($y, (float) $imgHeight));
        $right = max(0.0, min($x + $width, (float) $imgWidth));
        $bottom = max(0.0, min($y + $height, (float) $imgHeight));

        $clampedWidth = $right - $left;
        $clampedHeight = $bottom - $top;

        if ($clampedWidth < self::AUTOCOUNT_MIN_BOX_PX || $clampedHeight < self::AUTOCOUNT_MIN_BOX_PX) {
            return null;
        }
        if ($clampedWidth * $clampedHeight > $imgWidth * $imgHeight * self::AUTOCOUNT_MAX_BOX_AREA_FRACTION) {
            return null;
        }

        return [
            'x' => (int) round($left),
            'y' => (int) round($top),
            'width' => (int) round($clampedWidth),
            'height' => (int) round($clampedHeight),
        ];
    }

    /**
     * Crops $box out of $fullImagePath with GD and saves the result as a temp JPEG under
     * this takeoff's own company-scoped uploads directory (uploads/takeoffs/{company}/
     * {takeoff}/) — the same directory every other file this takeoff tool saves lives under.
     * The caller MUST delete the returned path once done with it (see autocount()'s finally
     * block) — this is a transient AI-input file, never a stored page or measurement asset.
     * Returns null on any GD failure (extension missing, unreadable/corrupt source image,
     * directory or file write failure) rather than throwing.
     */
    private function cropAutocountReference(string $fullImagePath, array $box, int $companyId, int $takeoffId): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecrop')) {
            return null;
        }

        $raw = @file_get_contents($fullImagePath);
        $source = $raw !== false ? @imagecreatefromstring($raw) : false;
        if ($source === false) {
            return null;
        }

        $cropped = @imagecrop($source, $box);
        imagedestroy($source);
        if ($cropped === false) {
            return null;
        }

        $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoffId}");
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            imagedestroy($cropped);
            return null;
        }

        $cropPath = $destDir . '/autocount-ref-' . uniqid() . '.jpg';
        $saved = @imagejpeg($cropped, $cropPath, 90);
        imagedestroy($cropped);

        return $saved ? $cropPath : null;
    }

    public function addMeasurement(Request $request, int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        // Defense in depth, same posture as calibrate()/autocount()'s own guards — a
        // superseded (historical, read-only) revision is never offered any measuring tool
        // in the UI, but the server rejects a direct write to one too.
        if (!$page->is_current) {
            return response()->json(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')], 422);
        }

        $type = (string) $request->input('type');
        $label = mb_substr(trim((string) $request->input('label')) ?: ucfirst($type), 0, 150);
        $value = $this->validatedNumber($request->input('value', 0), 0.001, 999999999.999);
        $unitCost = $this->validatedNumberOrDefault($request->input('unit_cost', 0), 0, 99999999.99, 0.0);
        $points = (string) $request->input('points', '[]');
        $isCutout = $request->boolean('is_cutout');

        if (!in_array($type, ['length', 'area', 'count'], true) || $value === null || $unitCost === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid measurement.'], 422);
        }

        $parent = null;
        if ($isCutout) {
            if ($type !== 'area') {
                return response()->json(['ok' => false, 'error' => 'Only an area measurement can be a cutout.'], 422);
            }

            $parentId = (int) $request->input('parent_measurement_id', 0);
            $parent = $parentId ? TakeoffMeasurement::find($parentId) : null;

            // Same-company (via the already-owned takeoff) and same-page checks — a cutout
            // can only reduce an area measurement that lives on this exact page.
            if (!$parent || $parent->takeoff_id !== $takeoff->id || $parent->takeoff_page_id !== $page->id) {
                return response()->json(['ok' => false, 'error' => 'Cutout parent measurement not found on this page.'], 404);
            }
            if ($parent->type !== 'area' || $parent->is_cutout) {
                return response()->json(['ok' => false, 'error' => 'A cutout can only be attached to an area measurement.'], 422);
            }
        }

        // An assembly only makes sense scaled against a continuous measurement (the length
        // of a wall, the area of a floor) — a point count has no sensible "per-unit" to
        // scale, and a cutout is never its own line item (see convertToEstimate()), so
        // neither can carry one.
        $assembly = null;
        $assemblyId = (int) $request->input('assembly_id', 0);
        if ($assemblyId) {
            if ($isCutout) {
                return response()->json(['ok' => false, 'error' => 'A cutout cannot have an assembly linked.'], 422);
            }
            if ($type !== 'length' && $type !== 'area') {
                return response()->json(['ok' => false, 'error' => 'An assembly can only be linked to a length or area measurement.'], 422);
            }
            $assembly = Assembly::where('id', $assemblyId)->where('company_id', $takeoff->company_id)->with('items')->first();
            if (!$assembly) {
                return response()->json(['ok' => false, 'error' => 'Assembly not found.'], 404);
            }
        }

        // When an assembly is linked, its items' cost — not whatever was submitted in the
        // unit_cost field (read-only on the client while an assembly is selected) — is the
        // authoritative per-unit cost, computed the same way assembliesForPicker() does.
        if ($assembly) {
            $unitCost = round((float) $assembly->items->sum(fn ($it) => (float) $it->qty_per_unit * (float) $it->unit_cost), 2);
        }

        $unit = $type === 'length' ? $page->scale_unit : ($type === 'area' ? $page->scale_unit . '²' : 'ea');

        $measurement = TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'takeoff_page_id' => $page->id,
            'parent_measurement_id' => $isCutout ? $parent->id : null,
            'is_cutout' => $isCutout,
            'assembly_id' => $assembly?->id,
            'type' => $type,
            'label' => $label,
            'points_json' => $points,
            'value' => $value,
            'unit' => $unit,
            'unit_cost' => $unitCost,
            'total_cost' => $value * $unitCost,
        ]);

        if ($isCutout && $parent) {
            $this->recomputeParentForCutouts($parent);
        }

        return response()->json(['ok' => true, 'id' => $measurement->id]);
    }

    /**
     * Recomputes $parent's net value/total_cost from its gross_value (captured the first time
     * a cutout is attached) minus the sum of all of its remaining cutouts' values. Called
     * after creating or deleting a cutout. If $parent has no cutouts left, this restores
     * value/total_cost exactly to gross_value.
     */
    private function recomputeParentForCutouts(TakeoffMeasurement $parent): void
    {
        if ($parent->gross_value === null) {
            $parent->gross_value = $parent->value;
        }

        $cutoutSum = (float) TakeoffMeasurement::where('parent_measurement_id', $parent->id)
            ->where('is_cutout', true)
            ->sum('value');

        $net = max(0.0, (float) $parent->gross_value - $cutoutSum);
        $parent->value = $net;
        $parent->total_cost = $net * (float) $parent->unit_cost;
        $parent->save();
    }

    /**
     * Runs one page's image through Claude's vision support and creates a first-pass
     * TakeoffMeasurement row for each item it identifies — plain "length"/"area"/"count"
     * rows, indistinguishable from manually-drawn ones, so the contractor reviews and
     * edits them with the same tools used in show.blade.php.
     */
    public function aiAnalyze(int $id, int $pageId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);
        $redirectUrl = '/app/takeoffs/' . $takeoff->id . '?page_id=' . $page->id;

        // Defense in depth, same posture as calibrate()/autocount()/addMeasurement()'s own
        // guards — a superseded (historical, read-only) revision is never offered this
        // action in the UI, but the server rejects a direct call too.
        if (!$page->is_current) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.revision_old_action_blocked'));
        }

        if (!AiTakeoffAnalyzer::isConfigured()) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_not_configured'));
        }

        $items = AiTakeoffAnalyzer::analyzeTakeoffPlan($page->image_path);

        if ($items === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_failed', ['reason' => $lastError]));
        }
        if (empty($items)) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_no_measurements'));
        }

        $count = $this->createMeasurementsFromAi($takeoff, $page, $items);

        $this->flash('success', t('user.takeoffs.ai_measurements_suggested', ['count' => $count]));
        return redirect($redirectUrl);
    }

    /**
     * Same analysis as aiAnalyze(), run sequentially across every page in the set, with each
     * detected item's label tagged with the page it came from so a 10+ page review list
     * stays legible. A page whose analysis fails outright (AiTakeoffAnalyzer returns null)
     * is skipped rather than aborting the rest of the set.
     */
    public function aiAnalyzeAll(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        // Only the CURRENT revision of each sheet — a superseded page is historical
        // reference only (see TakeoffPage's own docblock) and is never re-analyzed or
        // written to here, same filtering as the page picker in show().
        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->where('is_current', true)->orderBy('page_number')->get();

        if ($pages->isEmpty()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.image_required_for_ai'));
        }
        if (!AiTakeoffAnalyzer::isConfigured()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.ai_not_configured'));
        }

        $totalCount = 0;
        $failures = 0;
        foreach ($pages as $page) {
            $items = AiTakeoffAnalyzer::analyzeTakeoffPlan($page->image_path);
            if ($items === null || empty($items)) {
                $failures++;
                continue;
            }
            $totalCount += $this->createMeasurementsFromAi($takeoff, $page, $items, true);
        }

        if ($totalCount === 0) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.ai_no_measurements'));
        }

        $message = $failures > 0
            ? t('user.takeoffs.ai_measurements_suggested_partial', ['count' => $totalCount, 'failed' => $failures])
            : t('user.takeoffs.ai_measurements_suggested', ['count' => $totalCount]);

        $this->flash('success', $message);
        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    /** Shared by aiAnalyze() and aiAnalyzeAll() — creates one TakeoffMeasurement per detected item on $page. */
    private function createMeasurementsFromAi(Takeoff $takeoff, TakeoffPage $page, array $items, bool $tagPageInLabel = false): int
    {
        $materials = Material::where('company_id', $takeoff->company_id)->get(['name', 'category', 'unit_cost']);

        $count = 0;
        foreach ($items as $item) {
            $type = $item['type'];
            $unit = $type === 'length' ? $page->scale_unit : ($type === 'area' ? $page->scale_unit . '²' : 'ea');
            $label = $item['label'];
            if ($tagPageInLabel) {
                $label = t('user.takeoffs.page_label', ['number' => $page->page_number]) . ': ' . $label;
            }
            if ($item['note'] !== '') {
                $label .= ' (AI: ' . $item['note'] . ')';
            }
            $label = mb_substr($label, 0, 150);
            $unitCost = $this->suggestUnitCost($item['label'], $materials);

            TakeoffMeasurement::create([
                'takeoff_id' => $takeoff->id,
                'takeoff_page_id' => $page->id,
                'type' => $type,
                'label' => $label,
                'points_json' => '[]',
                'value' => $item['value'],
                'unit' => $unit,
                'unit_cost' => $unitCost,
                'total_cost' => $item['value'] * $unitCost,
            ]);
            $count++;
        }
        return $count;
    }

    /** Cheap case-insensitive substring match between the AI's label and the company's material library — 0 when nothing matches, so the user fills it in themselves. */
    private function suggestUnitCost(string $label, $materials): float
    {
        $needle = strtolower(trim($label));
        if ($needle === '') {
            return 0.0;
        }
        foreach ($materials as $m) {
            $name = strtolower((string) $m->name);
            $category = strtolower((string) $m->category);
            if ($name !== '' && (str_contains($needle, $name) || str_contains($name, $needle))) {
                return (float) $m->unit_cost;
            }
            if ($category !== '' && str_contains($needle, $category)) {
                return (float) $m->unit_cost;
            }
        }
        return 0.0;
    }

    public function deleteMeasurement(int $id, int $measurementId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $measurement = TakeoffMeasurement::find($measurementId);
        $redirectUrl = '/app/takeoffs/' . $takeoff->id;
        if ($measurement && $measurement->takeoff_id === $takeoff->id) {
            // Redirect back to the page the deleted measurement belonged to, so the user
            // isn't bounced back to page 1 while reviewing a later page's measurements.
            if ($measurement->takeoff_page_id) {
                $redirectUrl .= '?page_id=' . $measurement->takeoff_page_id;
            }

            // Defense in depth, same posture as calibrate()/addMeasurement()'s own guards —
            // a measurement attached to a superseded (historical, read-only) page must
            // remain exactly as it was; the UI never offers a delete button for one, and
            // the server rejects a direct call too.
            $onPage = $measurement->takeoff_page_id ? TakeoffPage::find($measurement->takeoff_page_id) : null;
            if ($onPage && !$onPage->is_current) {
                $this->flash('error', t('user.takeoffs.revision_old_action_blocked'));
                return redirect($redirectUrl);
            }

            if ($measurement->is_cutout) {
                $parentId = $measurement->parent_measurement_id;
                $measurement->delete();
                $parent = $parentId ? TakeoffMeasurement::find($parentId) : null;
                if ($parent) {
                    $this->recomputeParentForCutouts($parent);
                }
            } else {
                // Deleting a measurement that may itself be a cutout's parent: drop its
                // cutouts too, rather than leaving them pointing at a measurement that no
                // longer exists.
                TakeoffMeasurement::where('parent_measurement_id', $measurement->id)->where('is_cutout', true)->delete();
                $measurement->delete();
            }
        }
        return redirect($redirectUrl);
    }

    /** Pulls every measurement across every page of the takeoff — a measurement's takeoff_id is shared by all of its pages, so this already spans the whole multi-page set with no change needed. */
    public function convertToEstimate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        // Cutouts are never their own line item — their only effect is already baked into
        // their parent's net value/total_cost (see recomputeParentForCutouts()).
        $measurements = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('is_cutout', false)
            ->with('assembly.items')
            ->orderBy('id')->get();

        if ($measurements->isEmpty()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.measurement_required_for_conversion'));
        }

        $total = (float) $measurements->sum('total_cost');

        $estimate = Estimate::create([
            'company_id' => $takeoff->company_id,
            'project_id' => $takeoff->project_id,
            'client_id' => null,
            'title' => $takeoff->name . ' — Takeoff Estimate',
            'status' => 'draft',
            'total' => $total,
        ]);

        foreach ($measurements as $m) {
            // A measurement with no linked assembly keeps today's exact 1:1 behavior — one
            // EstimateItem, unchanged — for full backward compatibility with every existing
            // takeoff. A loosely-coupled assembly_id whose Assembly has since been deleted
            // falls back to the same 1:1 behavior rather than erroring.
            if ($m->assembly && $m->assembly->items->isNotEmpty()) {
                $this->addAssemblyLineItems($estimate->id, $m);
                continue;
            }

            EstimateItem::create([
                'estimate_id' => $estimate->id,
                'description' => $m->label . ' (' . number_format((float) $m->value, 2) . ' ' . $m->unit . ')',
                'qty' => $m->value,
                'unit_cost' => $m->unit_cost,
                'total' => $m->total_cost,
            ]);
        }

        $this->flash('success', t('user.takeoffs.converted'));
        return redirect('/app/estimates/' . $estimate->id);
    }

    /**
     * Expands $m's linked assembly into one EstimateItem per AssemblyItem, each scaled by
     * $m's own measured value — qty = round(qty_per_unit * value, 2), the exact same
     * scaling math app.partials.assembly-picker's client-side JS uses for the Estimate-side
     * "Insert Assembly" flow (confirmBtn handler: Math.round(it.qtyPerUnit * appliedQty *
     * 100) / 100), just run server-side here since there's no client-side picker step at
     * conversion time. Every expanded item is tagged with $m->label as its section_title —
     * the same column EstimateController::store() uses for the line-items table's "Section
     * (optional)" headers, carried on every item in the group (not only the first) so
     * show.blade.php's lastSection-change header logic groups them correctly.
     */
    private function addAssemblyLineItems(int $estimateId, TakeoffMeasurement $m): void
    {
        foreach ($m->assembly->items as $ai) {
            $qty = round((float) $ai->qty_per_unit * (float) $m->value, 2);
            EstimateItem::create([
                'estimate_id' => $estimateId,
                'description' => $ai->description,
                'description_ar' => $ai->description_ar,
                'section_title' => $m->label,
                'item_type' => $ai->item_type,
                'qty' => $qty,
                'uom' => $ai->uom,
                'unit_cost' => $ai->unit_cost,
                'total' => $qty * (float) $ai->unit_cost,
                'cost_code_id' => $ai->cost_code_id,
            ]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        TakeoffMeasurement::where('takeoff_id', $takeoff->id)->delete();

        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->get();
        foreach ($pages as $page) {
            $file = public_path($page->image_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        TakeoffPage::where('takeoff_id', $takeoff->id)->delete();

        // Vestigial legacy column — covered by a backfilled page for any takeoff that went
        // through the takeoff_pages migration, but this keeps destroy() correct even if an
        // old row somehow has no page of its own.
        if ($takeoff->plan_image_path) {
            $file = public_path($takeoff->plan_image_path);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $companyTakeoffDir = public_path("uploads/takeoffs/{$takeoff->company_id}/{$takeoff->id}");
        if (is_dir($companyTakeoffDir)) {
            @rmdir($companyTakeoffDir); // only succeeds once empty
        }

        $takeoff->delete();
        $this->flash('success', t('user.takeoffs.deleted'));
        return redirect('/app/takeoffs');
    }

    private function findOwned(int $id): Takeoff
    {
        $takeoff = Takeoff::find($id);
        abort_if(!$takeoff || $takeoff->company_id !== Auth::user()->company_id, 404, 'Takeoff not found.');
        return $takeoff;
    }

    private function findOwnedPage(Takeoff $takeoff, int $pageId): TakeoffPage
    {
        $page = TakeoffPage::find($pageId);
        abort_if(!$page || $page->takeoff_id !== $takeoff->id, 404, 'Takeoff page not found.');
        return $page;
    }

    /** Rejects a non-numeric or out-of-range value — see EstimateController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }
}
