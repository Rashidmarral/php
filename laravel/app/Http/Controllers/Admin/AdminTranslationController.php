<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Support\Lang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTranslationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $enDefaults = Lang::fileDefaults('en');
        $arDefaults = Lang::fileDefaults('ar');
        $enOverrides = Translation::overridesFor('en');
        $arOverrides = Translation::overridesFor('ar');

        $keys = array_unique(array_merge(array_keys($enDefaults), array_keys($arDefaults), array_keys($enOverrides), array_keys($arOverrides)));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            if ($search !== '' && stripos($key, $search) === false
                && stripos($enDefaults[$key] ?? '', $search) === false
                && stripos($enOverrides[$key] ?? '', $search) === false) {
                continue;
            }
            $rows[] = [
                'key' => $key,
                'en_default' => $enDefaults[$key] ?? '',
                'ar_default' => $arDefaults[$key] ?? '',
                'en_value' => $enOverrides[$key] ?? ($enDefaults[$key] ?? ''),
                'ar_value' => $arOverrides[$key] ?? ($arDefaults[$key] ?? ''),
                'is_custom' => !isset($enDefaults[$key]) && !isset($arDefaults[$key]),
                'is_overridden' => isset($enOverrides[$key]) || isset($arOverrides[$key]),
            ];
        }

        return view('admin.translations.index', [
            'rows' => $rows,
            'search' => $search,
            'total' => count($keys),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $key = trim((string) $request->input('key'));
        if ($key === '') {
            return redirect('/admin/translations');
        }

        Translation::upsert('en', $key, (string) $request->input('en_value', ''));
        Translation::upsert('ar', $key, (string) $request->input('ar_value', ''));

        $this->flash('success', t('admin.translations.saved', ['key' => $key]));
        return redirect('/admin/translations' . $this->searchQuery($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $key = trim((string) $request->input('new_key'));
        if ($key === '' || !preg_match('/^[a-zA-Z0-9_.\-]+$/', $key)) {
            return $this->redirectWithFlash('/admin/translations', 'error', t('admin.translations.invalid_key'));
        }

        Translation::upsert('en', $key, (string) $request->input('new_en_value', ''));
        Translation::upsert('ar', $key, (string) $request->input('new_ar_value', ''));

        return $this->redirectWithFlash('/admin/translations', 'success', t('admin.translations.key_added', ['key' => $key]));
    }

    public function reset(Request $request): RedirectResponse
    {
        $key = trim((string) $request->input('key'));
        if ($key !== '') {
            Translation::deleteKey($key);
            $this->flash('success', t('admin.translations.reset_to_default', ['key' => $key]));
        }
        return redirect('/admin/translations' . $this->searchQuery($request));
    }

    private function searchQuery(Request $request): string
    {
        $search = trim((string) $request->input('q', ''));
        return $search !== '' ? '?q=' . urlencode($search) : '';
    }
}
