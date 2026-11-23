<?php

namespace App\Support;

/**
 * The canonical Saudi cities/governorates list behind the "City" dropdown on
 * the company-settings and client forms (resources/views/app/settings/index.blade.php,
 * resources/views/app/clients/form.blade.php) — covers all 13 regions, not
 * just the handful of largest cities, since a company or client can be
 * based anywhere in the Kingdom. Each entry carries both its English and
 * Arabic name so the combobox JS (public/assets/js/city-combobox.js) can
 * match either one while typing, and so a bilingual label can be rendered
 * directly in the <option> text.
 *
 * The `city` column on both `companies` and `clients` has always been a
 * plain VARCHAR — this list only changes how a value is picked in the UI,
 * never the column itself, so any value already stored (including one not
 * in this list) must keep working: see withExtra() below, used by both
 * forms to add a value that predates this list as an extra selectable
 * option instead of silently dropping it.
 */
class SaudiCities
{
    /**
     * @var array<int, array{en: string, ar: string}>
     */
    private const CITIES = [
        ['en' => 'Riyadh', 'ar' => 'الرياض'],
        ['en' => 'Jeddah', 'ar' => 'جدة'],
        ['en' => 'Makkah', 'ar' => 'مكة المكرمة'],
        ['en' => 'Madinah', 'ar' => 'المدينة المنورة'],
        ['en' => 'Dammam', 'ar' => 'الدمام'],
        ['en' => 'Khobar', 'ar' => 'الخبر'],
        ['en' => 'Dhahran', 'ar' => 'الظهران'],
        ['en' => 'Taif', 'ar' => 'الطائف'],
        ['en' => 'Tabuk', 'ar' => 'تبوك'],
        ['en' => 'Abha', 'ar' => 'أبها'],
        ['en' => 'Khamis Mushait', 'ar' => 'خميس مشيط'],
        ['en' => 'Jizan', 'ar' => 'جازان'],
        ['en' => 'Najran', 'ar' => 'نجران'],
        ['en' => 'Hail', 'ar' => 'حائل'],
        ['en' => 'Buraidah', 'ar' => 'بريدة'],
        ['en' => 'Unaizah', 'ar' => 'عنيزة'],
        ['en' => 'Al Kharj', 'ar' => 'الخرج'],
        ['en' => 'Yanbu', 'ar' => 'ينبع'],
        ['en' => 'Al Jubail', 'ar' => 'الجبيل'],
        ['en' => 'Al-Ahsa (Hofuf)', 'ar' => 'الأحساء (الهفوف)'],
        ['en' => 'Qatif', 'ar' => 'القطيف'],
        ['en' => 'Arar', 'ar' => 'عرعر'],
        ['en' => 'Sakaka', 'ar' => 'سكاكا'],
        ['en' => 'Al Baha', 'ar' => 'الباحة'],
        ['en' => 'Dawadmi', 'ar' => 'الدوادمي'],
        ['en' => 'Qurayyat', 'ar' => 'القريات'],
        ['en' => 'Rabigh', 'ar' => 'رابغ'],
        ['en' => 'Khafji', 'ar' => 'الخفجي'],
        ['en' => 'Ras Tanura', 'ar' => 'رأس تنورة'],
        ['en' => 'Qunfudhah', 'ar' => 'القنفذة'],
        ['en' => 'Bisha', 'ar' => 'بيشة'],
        ['en' => 'Al Majmaah', 'ar' => 'المجمعة'],
        ['en' => 'Afif', 'ar' => 'عفيف'],
        ['en' => 'Wadi al-Dawasir', 'ar' => 'وادي الدواسر'],
        ['en' => 'Turaif', 'ar' => 'طريف'],
        ['en' => 'Al Lith', 'ar' => 'الليث'],
        ['en' => 'Al Zulfi', 'ar' => 'الزلفي'],
        ['en' => 'Hafar Al-Batin', 'ar' => 'حفر الباطن'],
        ['en' => 'Rafha', 'ar' => 'رفحاء'],
        ['en' => 'Shaqra', 'ar' => 'شقراء'],
        ['en' => 'Al Rass', 'ar' => 'الرس'],
        ['en' => 'Badr', 'ar' => 'بدر'],
        ['en' => 'Duba', 'ar' => 'ضباء'],
        ['en' => 'Umluj', 'ar' => 'أملج'],
        ['en' => 'Al Wajh', 'ar' => 'الوجه'],
        ['en' => 'Farasan', 'ar' => 'فرسان'],
        ['en' => 'Sabya', 'ar' => 'صبيا'],
        ['en' => 'Sharurah', 'ar' => 'شرورة'],
        ['en' => 'Al Khurmah', 'ar' => 'الخرمة'],
        ['en' => 'Turbah', 'ar' => 'تربة'],
    ];

    /**
     * Alphabetically-sorted list of every canonical city, for rendering as
     * <option>s.
     *
     * @return array<int, array{en: string, ar: string}>
     */
    public static function all(): array
    {
        $cities = self::CITIES;
        usort($cities, fn ($a, $b) => strcmp($a['en'], $b['en']));

        return $cities;
    }

    /** Whether $value matches a canonical city's English name exactly (case-insensitive). */
    public static function has(string $value): bool
    {
        $needle = mb_strtolower(trim($value));
        if ($needle === '') {
            return false;
        }
        foreach (self::CITIES as $city) {
            if (mb_strtolower($city['en']) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * all() plus, when $current is non-empty and not already in the
     * canonical list (a legacy value saved before this dropdown existed,
     * or a one-off typed value), one extra trailing entry for it — so the
     * dropdown always has a selectable option matching whatever is
     * currently stored instead of silently blanking the field.
     *
     * @return array<int, array{en: string, ar: string}>
     */
    public static function withExtra(?string $current): array
    {
        $cities = self::all();
        $current = trim((string) $current);
        if ($current !== '' && ! self::has($current)) {
            $cities[] = ['en' => $current, 'ar' => ''];
        }

        return $cities;
    }
}
