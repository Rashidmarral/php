<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Lang;
use App\Models\Translation;

class AdminTranslationController extends Controller
{
    public function index(): void
    {
        $search = trim((string) $this->input('q', ''));

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

        $this->view('admin/translations/index', [
            'pageTitle' => 'Translations',
            'rows' => $rows,
            'search' => $search,
            'total' => count($keys),
        ], 'layouts/admin');
    }

    public function update(): void
    {
        $this->verifyCsrf();
        $key = trim((string) $this->input('key'));
        if ($key === '') {
            self::redirect('/admin/translations');
        }

        Translation::upsert('en', $key, (string) $this->input('en_value', ''));
        Translation::upsert('ar', $key, (string) $this->input('ar_value', ''));

        $this->flash('success', 'Translation "' . $key . '" saved.');
        self::redirect('/admin/translations' . $this->searchQuery());
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $key = trim((string) $this->input('new_key'));
        if ($key === '' || !preg_match('/^[a-zA-Z0-9_.\-]+$/', $key)) {
            $this->flash('error', 'Enter a valid key using letters, numbers, dots, dashes and underscores only.');
            self::redirect('/admin/translations');
        }

        Translation::upsert('en', $key, (string) $this->input('new_en_value', ''));
        Translation::upsert('ar', $key, (string) $this->input('new_ar_value', ''));

        $this->flash('success', 'Translation key "' . $key . '" added — use it in a custom page or template as t(\'' . $key . '\').');
        self::redirect('/admin/translations');
    }

    public function reset(): void
    {
        $this->verifyCsrf();
        $key = trim((string) $this->input('key'));
        if ($key !== '') {
            Translation::deleteKey($key);
            $this->flash('success', 'Translation "' . $key . '" reset to default.');
        }
        self::redirect('/admin/translations' . $this->searchQuery());
    }

    private function searchQuery(): string
    {
        $search = trim((string) $this->input('q', ''));
        return $search !== '' ? '?q=' . urlencode($search) : '';
    }
}
