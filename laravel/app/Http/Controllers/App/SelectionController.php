<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Selection;
use App\Models\SelectionOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Company-side CRUD for Selections (allowance tracking): a budgeted allowance for
 * something not yet finalized, with a handful of priced options the client picks from
 * in the portal (see Portal\PortalController::selections()/chooseOption()). Mirrors
 * MaterialController's ownership-scoping/validation style; create()/store() and
 * edit()/update() parse the nested options the same array-field way
 * AssemblyController::store()/update() parses its own nested items, including the same
 * "delete then recreate" approach since the plain add/remove-row JS gives a row no
 * stable identity to match against on save.
 */
class SelectionController extends Controller
{
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public function index(int $projectId): View
    {
        $project = $this->findOwnedProject($projectId);
        $selections = Selection::where('project_id', $project->id)
            ->with('options')
            ->orderBy('category')->orderBy('name')
            ->get();

        $grouped = [];
        foreach ($selections as $selection) {
            $grouped[$selection->category][] = [
                ...$selection->toArray(),
                'options' => $selection->options->toArray(),
                'chosen_option' => $selection->chosenOption()?->toArray(),
                'variance' => $selection->variance(),
            ];
        }

        return view('app.selections.index', [
            'project' => $project->toArray(),
            'grouped' => $grouped,
            'statuses' => Selection::STATUSES,
        ]);
    }

    public function create(int $projectId): View
    {
        $project = $this->findOwnedProject($projectId);

        return view('app.selections.form', [
            'project' => $project->toArray(),
            'selection' => null,
            'options' => [],
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        [$fields, $error] = $this->fromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/projects/'.$project->id.'/selections/create', 'error', $error);
        }
        [$options, $error] = $this->optionsFromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/projects/'.$project->id.'/selections/create', 'error', $error);
        }

        $selection = Selection::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            ...$fields,
        ]);
        foreach ($options as $option) {
            SelectionOption::create(['selection_id' => $selection->id, ...$option]);
        }

        $this->flash('success', t('user.selections.added'));

        return redirect('/app/projects/'.$project->id.'/selections');
    }

    public function edit(int $id): View
    {
        $selection = $this->findOwned($id);
        $project = $this->findOwnedProject($selection->project_id);

        return view('app.selections.form', [
            'project' => $project->toArray(),
            'selection' => $selection->toArray(),
            'options' => $selection->options()->get()->toArray(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $selection = $this->findOwned($id);

        [$fields, $error] = $this->fromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/selections/'.$id.'/edit', 'error', $error);
        }
        [$options, $error] = $this->optionsFromInput($request);
        if ($error) {
            return $this->redirectWithFlash('/app/selections/'.$id.'/edit', 'error', $error);
        }

        $selection->update($fields);

        // A row's image is cleaned up from disk the moment it's no longer referenced by
        // any of the new options — either because its row was dropped by the
        // remove-row JS, or because a fresh image replaced it inside optionsFromInput()
        // (that old path was already deleted there, so this is a harmless no-op for it).
        $keptImagePaths = array_filter(array_column($options, 'image_path'));
        foreach ($selection->options as $existingOption) {
            if ($existingOption->image_path && ! in_array($existingOption->image_path, $keptImagePaths, true)) {
                $this->deleteImageFile($existingOption->image_path);
            }
        }
        SelectionOption::where('selection_id', $selection->id)->delete();
        foreach ($options as $option) {
            SelectionOption::create(['selection_id' => $selection->id, ...$option]);
        }

        $this->flash('success', t('user.selections.updated'));

        return redirect('/app/projects/'.$selection->project_id.'/selections');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $selection = $this->findOwned($id);
        $projectId = $selection->project_id;

        foreach ($selection->options as $option) {
            if ($option->image_path) {
                $this->deleteImageFile($option->image_path);
            }
        }
        SelectionOption::where('selection_id', $selection->id)->delete();
        $selection->delete();

        $this->flash('success', t('user.selections.removed'));

        return redirect('/app/projects/'.$projectId.'/selections');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $selection = $this->findOwned($id);

        $status = $request->input('status');
        if (! array_key_exists($status, Selection::STATUSES)) {
            return $this->redirectWithFlash('/app/projects/'.$selection->project_id.'/selections', 'error', t('user.selections.invalid_status'));
        }

        $selection->update(['status' => $status]);

        return $this->redirectWithFlash('/app/projects/'.$selection->project_id.'/selections', 'success', t('user.selections.status_updated'));
    }

    /**
     * Shared by store()/update(): validates and normalizes the selection's own fields.
     * Rejects (rather than silently casting) anything that doesn't parse, same
     * discipline as MaterialController::fromInput().
     *
     * @return array{0: array<string, mixed>, 1: ?string} [fields, error]
     */
    private function fromInput(Request $request): array
    {
        $category = trim((string) $request->input('category'));
        if ($category === '') {
            return [[], t('user.selections.category_required')];
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return [[], t('user.selections.name_required')];
        }

        $allowance = $this->validatedNumberOrDefault($request->input('allowance_amount', 0), 0, 9999999999.99, 0.0);
        if ($allowance === null) {
            return [[], t('user.selections.allowance_invalid')];
        }

        $dueDate = trim((string) $request->input('due_date', ''));
        if (! $this->isValidOptionalDate($dueDate)) {
            return [[], t('user.selections.due_date_invalid')];
        }

        return [[
            'category' => mb_substr($category, 0, 100),
            'name' => mb_substr($name, 0, 150),
            'name_ar' => mb_substr(trim((string) $request->input('name_ar', '')), 0, 150),
            'description' => mb_substr(trim((string) $request->input('description', '')), 0, 5000) ?: null,
            'description_ar' => mb_substr(trim((string) $request->input('description_ar', '')), 0, 5000) ?: null,
            'allowance_amount' => $allowance,
            'due_date' => $dueDate ?: null,
        ], null];
    }

    /**
     * Parses the nested option_*[] arrays the create/edit form submits. A blank name
     * row is silently skipped (an empty trailing row left by the add-row JS), but at
     * least one real option is required. option_existing_image[]/option_existing_chosen[]
     * carry a row's current image_path/is_chosen forward across the "delete then
     * recreate" save when no new image is uploaded for that row — see form.blade.php.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: ?string} [options, error]
     */
    private function optionsFromInput(Request $request): array
    {
        $names = $request->input('option_name', []);
        $namesAr = $request->input('option_name_ar', []);
        $descriptions = $request->input('option_description', []);
        $prices = $request->input('option_price', []);
        $existingImages = $request->input('option_existing_image', []);
        $existingChosen = $request->input('option_existing_chosen', []);
        $images = $request->file('option_image', []);

        $options = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $price = $this->validatedNumberOrDefault($prices[$i] ?? 0, 0, 9999999999.99, 0.0);
            if ($price === null) {
                return [[], t('user.selections.option_price_invalid')];
            }

            $imagePath = trim((string) ($existingImages[$i] ?? ''));
            $uploaded = $images[$i] ?? null;
            if ($uploaded && $uploaded->isValid()) {
                $mime = $uploaded->getMimeType();
                if (! isset(self::ALLOWED_IMAGE_TYPES[$mime])) {
                    return [[], t('user.selections.option_image_type_invalid')];
                }
                if ($uploaded->getSize() > self::MAX_IMAGE_BYTES) {
                    return [[], t('user.selections.option_image_too_large')];
                }
                // Replacing an existing image: the old file is no longer referenced by
                // anything once this save completes, so it's removed right away — same
                // "swap, then clean up the old file" rule as InvoiceTemplateController.
                if ($imagePath !== '') {
                    $this->deleteImageFile($imagePath);
                }
                $filename = 'selection-'.bin2hex(random_bytes(6)).'.'.self::ALLOWED_IMAGE_TYPES[$mime];
                $uploaded->move(public_path('uploads/selections'), $filename);
                $imagePath = "/uploads/selections/{$filename}";
            }

            $options[] = [
                'name' => mb_substr($name, 0, 150),
                'name_ar' => mb_substr(trim((string) ($namesAr[$i] ?? '')), 0, 150),
                'description' => mb_substr(trim((string) ($descriptions[$i] ?? '')), 0, 2000) ?: null,
                'price' => $price,
                'image_path' => $imagePath ?: null,
                'is_chosen' => (bool) ($existingChosen[$i] ?? false),
                'sort_order' => count($options),
            ];
        }

        if (empty($options)) {
            return [[], t('user.selections.option_required')];
        }

        return [$options, null];
    }

    private function deleteImageFile(string $path): void
    {
        $file = public_path($path);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function findOwned(int $id): Selection
    {
        $selection = Selection::find($id);
        abort_if(! $selection || $selection->company_id !== Auth::user()->company_id, 404, 'Selection not found.');

        return $selection;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(! $project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');

        return $project;
    }

    /** Rejects a non-numeric or out-of-range value — see MaterialController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
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

    /** Same shape as ProjectController::isValidOptionalDate() — a blank value is valid (optional), anything else must be a real calendar date in Y-m-d form. */
    private function isValidOptionalDate(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
