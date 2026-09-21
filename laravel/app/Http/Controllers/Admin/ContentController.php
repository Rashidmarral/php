<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Support\Lang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A friendlier, page-scoped alternative to the flat Translations screen: lets the admin edit every
 * text block on a given marketing page (headline, section copy, FAQ answers, etc.) as plain text
 * fields — no HTML — grouped the way they actually appear on the page. Under the hood this reads/writes
 * the exact same Translation overrides as /admin/translations, so the site keeps rendering through the
 * normal modern templates; only the words change.
 */
class ContentController extends Controller
{
    public const PAGES = [
        'home' => ['label' => 'Homepage', 'url' => '/', 'prefixes' => ['hero.', 'platform.', 'platform2.', 'platform3.', 'how.', 'testi', 'faq', 'cta.']],
        'features' => ['label' => 'Features', 'url' => '/features', 'prefixes' => ['features.']],
        'about' => ['label' => 'About Us', 'url' => '/about', 'prefixes' => ['about.']],
        'pricing' => ['label' => 'Pricing', 'url' => '/pricing', 'prefixes' => ['pricing.', 'feature.']],
        'contact' => ['label' => 'Contact', 'url' => '/contact', 'prefixes' => ['contact.']],
        'support' => ['label' => 'Help Center', 'url' => '/support', 'prefixes' => ['support.']],
        'security' => ['label' => 'Security & Compliance', 'url' => '/security', 'prefixes' => ['security.']],
    ];

    public function index(): View
    {
        $enDefaults = Lang::fileDefaults('en');
        $counts = [];
        foreach (self::PAGES as $slug => $page) {
            $counts[$slug] = count($this->matchingKeys($enDefaults, $page['prefixes']));
        }

        return view('admin.content.index', ['pages' => self::PAGES, 'counts' => $counts]);
    }

    public function edit(string $page): View
    {
        abort_if(!isset(self::PAGES[$page]), 404, 'Unknown page.');
        $config = self::PAGES[$page];

        $enDefaults = Lang::fileDefaults('en');
        $arDefaults = Lang::fileDefaults('ar');
        $enOverrides = Translation::overridesFor('en');
        $arOverrides = Translation::overridesFor('ar');

        $keys = $this->matchingKeys($enDefaults, $config['prefixes']);
        sort($keys);

        $fields = [];
        foreach ($keys as $key) {
            $fields[] = [
                'key' => $key,
                'label' => $this->humanize($key),
                'en_value' => $enOverrides[$key] ?? ($enDefaults[$key] ?? ''),
                'ar_value' => $arOverrides[$key] ?? ($arDefaults[$key] ?? ''),
                'multiline' => strlen($enDefaults[$key] ?? '') > 90,
            ];
        }

        return view('admin.content.edit', ['page' => $page, 'config' => $config, 'fields' => $fields]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        abort_if(!isset(self::PAGES[$page]), 404, 'Unknown page.');
        $config = self::PAGES[$page];

        $enDefaults = Lang::fileDefaults('en');
        $validKeys = $this->matchingKeys($enDefaults, $config['prefixes']);

        $en = (array) $request->input('en', []);
        $ar = (array) $request->input('ar', []);
        foreach ($validKeys as $key) {
            if (array_key_exists($key, $en)) {
                Translation::upsert('en', $key, (string) $en[$key]);
            }
            if (array_key_exists($key, $ar)) {
                Translation::upsert('ar', $key, (string) $ar[$key]);
            }
        }

        return $this->redirectWithFlash("/admin/content/{$page}", 'success', t('admin.content.updated', ['page' => $config['label']]));
    }

    /** @return string[] */
    private function matchingKeys(array $enDefaults, array $prefixes): array
    {
        $keys = [];
        foreach (array_keys($enDefaults) as $key) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $keys[] = $key;
                    break;
                }
            }
        }
        return $keys;
    }

    private function humanize(string $key): string
    {
        $parts = explode('.', $key);
        array_shift($parts);
        $text = implode(' ', $parts);
        $text = preg_replace('/([a-z])([0-9])/i', '$1 $2', $text) ?? $text;
        $text = str_replace('_', ' ', $text);
        return ucfirst($text) ?: $key;
    }
}
