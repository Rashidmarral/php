<?php

namespace App\Support;

/**
 * BuildXact's own starter looks a company can clone into an editable
 * App\Models\InvoiceTemplate row, one per document type. Adapted in concept
 * (breadth of presets, per-document-type scoping, preset-then-customize
 * workflow) from the Daftari reference product's own preset gallery, but
 * built entirely around this app's own navy (#16233f) + amber (#f0932b)
 * brand identity and named for the Saudi construction-industry companies
 * this app serves — none of Daftari's preset names or hex values are reused.
 *
 * `layout` is the structural family a later rendering stage branches on
 * (see the invoice_templates migration's comment for the 'card'/'bilingual'/
 * 'letterhead' naming decision). `preset_key` mirrors the array key and is
 * what a created InvoiceTemplate row stores to remember which preset it
 * started from (see InvoiceTemplate::$guarded / the migration's preset_key
 * column comment) — a template can still be freely customized afterward.
 */
class InvoiceTemplatePresets
{
    public static function all(): array
    {
        return [
            'site_standard' => [
                'preset_key' => 'site_standard',
                'name' => 'Site Standard',
                'name_ar' => 'المعيار الميداني',
                'description' => 'A clean, dependable layout for everyday estimates and invoices.',
                'description_ar' => 'تصميم نظيف وموثوق للعروض والفواتير اليومية.',
                'accent_color' => '#16233f',
                'layout' => 'card',
            ],
            'riyadh_corporate' => [
                'preset_key' => 'riyadh_corporate',
                'name' => 'Riyadh Corporate',
                'name_ar' => 'الرياض المؤسسي',
                'description' => 'A polished, corporate look for head-office and enterprise clients.',
                'description_ar' => 'مظهر مؤسسي أنيق يناسب المكتب الرئيسي وكبار العملاء.',
                'accent_color' => '#0b3d5c',
                'layout' => 'card',
            ],
            'contractor_bold' => [
                'preset_key' => 'contractor_bold',
                'name' => 'Contractor Bold',
                'name_ar' => 'جريء للمقاولين',
                'description' => 'A confident, high-contrast layout that stands out on site.',
                'description_ar' => 'تصميم جريء وعالي التباين يبرز في الموقع.',
                'accent_color' => '#f0932b',
                'layout' => 'card',
            ],
            'desert_minimal' => [
                'preset_key' => 'desert_minimal',
                'name' => 'Desert Minimal',
                'name_ar' => 'الصحراء البسيط',
                'description' => 'A quiet, minimal layout that lets the numbers speak for themselves.',
                'description_ar' => 'تصميم بسيط وهادئ يترك الأرقام تتحدث عن نفسها.',
                'accent_color' => '#a9855f',
                'layout' => 'card',
            ],
            'quarry_slate' => [
                'preset_key' => 'quarry_slate',
                'name' => 'Quarry Slate',
                'name_ar' => 'أردواز المحجر',
                'description' => 'A cool, structured layout with a slate-gray accent.',
                'description_ar' => 'تصميم منظم بلون رمادي أردوازي هادئ.',
                'accent_color' => '#4a5560',
                'layout' => 'card',
            ],
            'foundation_charcoal' => [
                'preset_key' => 'foundation_charcoal',
                'name' => 'Foundation Charcoal',
                'name_ar' => 'الأساس الفحمي',
                'description' => 'A grounded, high-contrast charcoal layout for formal submissions.',
                'description_ar' => 'تصميم فحمي راسخ وعالي التباين للمستندات الرسمية.',
                'accent_color' => '#2f2f2f',
                'layout' => 'card',
            ],
            'heritage_bilingual' => [
                'preset_key' => 'heritage_bilingual',
                'name' => 'Heritage Bilingual',
                'name_ar' => 'التراث ثنائي اللغة',
                'description' => 'A bordered, fully bilingual layout for ZATCA-facing documents.',
                'description_ar' => 'تصميم بحدود وثنائي اللغة بالكامل للمستندات الموجهة لهيئة الزكاة والضريبة والجمارك.',
                'accent_color' => '#8a6d3b',
                'layout' => 'bilingual',
            ],
            'company_letterhead' => [
                'preset_key' => 'company_letterhead',
                'name' => 'Company Letterhead',
                'name_ar' => 'ترويسة الشركة',
                'description' => 'Prints your own company letterhead as the page background.',
                'description_ar' => 'يطبع ترويسة شركتك الخاصة كخلفية للصفحة.',
                'accent_color' => '#16233f',
                'layout' => 'letterhead',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
