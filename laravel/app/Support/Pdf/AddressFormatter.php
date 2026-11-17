<?php

namespace App\Support\Pdf;

/**
 * Pulls the structured Saudi-address columns (building_number, street_name,
 * district, city, postal_code, additional_number, country_code) that
 * Company and Client already share — see 2026_01_01_000002_create_companies_table.php
 * and 2026_09_11_000001_add_zatca_b2b_fields_to_clients_table.php — into one
 * flat, PDF-template-ready shape, so every invoice PDF layout can render a
 * real seller/buyer address block instead of just a name.
 *
 * Every field is optional: a company/client that hasn't filled in its
 * structured address yet (most rows created before this feature, or simply
 * left blank) yields an array with only the fields it actually has, never a
 * PHP notice or a blank/placeholder line in the rendered PDF.
 */
class AddressFormatter
{
    /**
     * The address fields in Daftari's own display order, mapped to the
     * translation key for each one's label. Shared by every PDF template
     * that renders a structured address, so they all use the exact same
     * wording/order instead of each re-inventing its own labels.
     *
     * @return array<string, string>
     */
    public static function labelKeys(): array
    {
        return [
            'building_number' => 'user.settings.building_number',
            'street' => 'user.settings.street_name',
            'district' => 'user.settings.district',
            'city' => 'common.city',
            'postal_code' => 'user.settings.postal_code',
            'additional_number' => 'user.settings.additional_number',
        ];
    }

    /**
     * @param  object|array|null  $model  A Company, Client, or anything else carrying the same column names.
     * @return array<string, string> Only the non-empty fields, plus 'country_code' when the model has one set.
     */
    public static function fromModel(mixed $model): array
    {
        if (! $model) {
            return [];
        }
        $get = function (string $field) use ($model): string {
            $value = is_array($model) ? ($model[$field] ?? null) : ($model->{$field} ?? null);

            return trim((string) $value);
        };

        $out = array_filter([
            'building_number' => $get('building_number'),
            'street' => $get('street_name'),
            'district' => $get('district'),
            'city' => $get('city'),
            'postal_code' => $get('postal_code'),
            'additional_number' => $get('additional_number'),
        ], fn ($value) => $value !== '');

        $countryCode = $get('country_code');
        if ($countryCode !== '') {
            $out['country_code'] = $countryCode;
        }

        return $out;
    }

    /** True once fromModel() found at least one non-empty address field — lets a template skip the whole address block for a company/client that never filled one in. */
    public static function hasAny(array $address): bool
    {
        return $address !== [];
    }

    /**
     * The country's display name for $countryCode in $locale, shaping-ready
     * (already passed through pdfText()) — 'common.saudi_arabia' for 'SA'
     * (the overwhelming majority of real rows), or the raw ISO code itself
     * for anything else, since this app has no fuller country-name lookup
     * table.
     */
    public static function countryName(string $countryCode, string $locale): string
    {
        $name = strtoupper($countryCode) === 'SA' ? tFor('common.saudi_arabia', $locale) : $countryCode;

        return pdfText($name, $locale);
    }

    /**
     * Every non-empty field of $address rendered as "Label: value" lines in
     * $locale (shaped/joined for Arabic via pdfText(), label text looked up
     * via tFor() so it is real, translatable, DB-overridable text — never a
     * raw hardcoded string) — the country line, if any, has no label, just
     * its resolved display name, matching Daftari's own layout.
     *
     * @return array<int, string>
     */
    public static function lines(array $address, string $locale): array
    {
        $lines = [];
        foreach (self::labelKeys() as $field => $labelKey) {
            if (! empty($address[$field])) {
                $lines[] = pdfText(tFor($labelKey, $locale), $locale).': '.pdfText($address[$field], $locale);
            }
        }
        if (! empty($address['country_code'])) {
            $lines[] = self::countryName($address['country_code'], $locale);
        }

        return $lines;
    }
}
