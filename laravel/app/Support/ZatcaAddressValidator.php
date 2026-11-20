<?php

namespace App\Support;

/**
 * Format checks for the four ZATCA National Address / registration-number
 * fields that, until now, were trimmed and stored as-is with no validation
 * at all on either the company-settings save
 * (App\Http\Controllers\App\SettingsController::update()) or the client
 * save (App\Http\Controllers\App\ClientController::store()/update(), via
 * its private b2bData()). Centralized here, the same way
 * App\Support\PhoneNumber centralizes Saudi phone normalization, so both
 * controllers apply byte-for-byte the same four regexes instead of
 * maintaining copies that could drift apart.
 *
 * vat_number is deliberately NOT handled here — it already has its own
 * validation in App\Support\Zatca\ZatcaXmlGenerator::VAT_NUMBER_PATTERN,
 * and both controllers keep using that constant directly rather than
 * duplicating its regex a second time.
 *
 * Every field here stays optional: validate() only rejects a value that is
 * present but malformed, exactly like the pre-existing VAT check in
 * ClientController::b2bData() does for vat_number.
 */
class ZatcaAddressValidator
{
    /** ZATCA National Address building number: exactly 4 digits. */
    public const BUILDING_NUMBER_PATTERN = '/^\d{4}$/';

    /** ZATCA National Address postal code: exactly 5 digits. */
    public const POSTAL_CODE_PATTERN = '/^\d{5}$/';

    /** ZATCA National Address additional number: exactly 4 digits. */
    public const ADDITIONAL_NUMBER_PATTERN = '/^\d{4}$/';

    /** Saudi Commercial Registration number: exactly 10 digits (see resources/views/auth/register.blade.php's own "1010XXXXXX" placeholder). */
    public const CR_NUMBER_PATTERN = '/^\d{10}$/';

    /**
     * Checks the four already-trimmed fields and returns the first
     * translated error message found, or null when every non-empty field
     * matches its pattern.
     */
    public static function validate(string $buildingNumber, string $postalCode, string $additionalNumber, string $crNumber): ?string
    {
        if ($buildingNumber !== '' && preg_match(self::BUILDING_NUMBER_PATTERN, $buildingNumber) !== 1) {
            return t('user.settings.building_number_invalid');
        }
        if ($postalCode !== '' && preg_match(self::POSTAL_CODE_PATTERN, $postalCode) !== 1) {
            return t('user.settings.postal_code_invalid');
        }
        if ($additionalNumber !== '' && preg_match(self::ADDITIONAL_NUMBER_PATTERN, $additionalNumber) !== 1) {
            return t('user.settings.additional_number_invalid');
        }
        if ($crNumber !== '' && preg_match(self::CR_NUMBER_PATTERN, $crNumber) !== 1) {
            return t('user.settings.cr_number_invalid');
        }

        return null;
    }
}
