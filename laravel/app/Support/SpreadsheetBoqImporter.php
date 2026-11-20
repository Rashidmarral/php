<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

/**
 * Parses an uploaded .xlsx/.xls/.csv spreadsheet of Bill-of-Quantities-shaped
 * line items for either of this app's two independent BOQ systems:
 * EstimateController (quoting-side EstimateItem rows) and BoqController
 * (contract-side BoqItem rows) — see those controllers' own docblocks for why
 * the two are kept separate rather than merged.
 *
 * This class only PARSES and VALIDATES rows; it never touches the database —
 * each controller maps parse()'s clean 'valid' rows onto its own model fields
 * and appends them (never replaces existing lines), reusing the exact same
 * per-field rules its manual add-item action already applies.
 *
 * Column headers are matched case-insensitively and independent of
 * punctuation/spacing (so "Item No.", "item no", and "Item  No" all match).
 * 'section' and 'item_number' (BOQ) / 'type' (Estimate) are optional; every
 * other recognized column is expected but a missing value is not on its own
 * an error (see qty/unit_price handling below).
 *
 * Judgment calls (documented here since both importers share them):
 * - A row that is entirely blank (every recognized cell empty) is silently
 *   skipped — it's not a data row, so it's not a validation failure either.
 * - A blank qty or unit price cell on an otherwise non-blank row defaults to
 *   0 rather than being rejected — a contractor commonly drops in a
 *   description first and prices it later. A cell that has *something* in it
 *   that isn't a valid non-negative number (text, a negative value) IS a row
 *   error: silently coercing "abc" or "-5" to 0 would hide a real mistake.
 * - Thousands separators (","), stray whitespace, and numbers already handed
 *   over as strings are all accepted, e.g. "1,234.50".
 * - 'section' carries forward from the last row that specified one (blank on
 *   the rows below it), mirroring the section_title carry-forward already
 *   used by EstimateController::store()/update()'s $lastSection.
 */
class SpreadsheetBoqImporter
{
    /** Data rows beyond this are rejected as a single whole-file error rather than silently truncated. */
    public const MAX_ROWS = 500;

    /** Matches TicketAttachment's 10MB ticket-attachment cap order of magnitude, kept tighter since this is a small tabular file, not a photo/PDF. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED_EXT = ['xlsx', 'xls', 'csv'];

    /** Exactly EstimateController's allowed item_type values — a value outside this list defaults to 'material', it's never a row error, matching store()/update()'s own `in_array(...) ? ... : 'material'` rule. */
    private const ITEM_TYPES = ['material', 'labor', 'equipment', 'subcontractor', 'other'];

    /** @var array<string, string[]> maps our internal field name to every header spelling (already normalizeHeader()'d) that's recognized for it. */
    private const HEADER_ALIASES = [
        'section' => ['section', 'section title'],
        'item_number' => ['item no', 'item number', 'itemno'],
        'description' => ['description', 'desc'],
        'uom' => ['uom', 'unit', 'unit of measure'],
        'qty' => ['qty', 'quantity'],
        'unit_price' => ['unit price', 'unit cost', 'rate'],
        'type' => ['type', 'item type'],
    ];

    /**
     * @param  'estimate'|'boq'  $target  Which system's rules to apply: 'boq' recognizes item_number, 'estimate' recognizes type.
     * @return array{error: ?string, valid: array<int, array{section: string, item_number: string, description: string, uom: string, qty: float, unit_price: float, type: string}>, errors: array<int, array{row: int, reason: string}>}
     */
    public static function parse(UploadedFile $file, string $target): array
    {
        $empty = ['error' => null, 'valid' => [], 'errors' => []];

        if (!$file->isValid()) {
            return [...$empty, 'error' => 'The file failed to upload. Please try again.'];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return [...$empty, 'error' => 'The file must be smaller than 5MB.'];
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return [...$empty, 'error' => 'Unsupported file type. Please upload an .xlsx, .xls, or .csv file.'];
        }

        try {
            $rows = self::readRows($file->getRealPath(), $ext);
        } catch (Throwable $e) {
            return [...$empty, 'error' => 'Could not read this file. Make sure it is a valid, unprotected Excel or CSV file.'];
        }

        if (empty($rows)) {
            return [...$empty, 'error' => 'This file has no rows.'];
        }

        $columns = self::mapHeaderRow(array_shift($rows));
        if (!isset($columns['description'])) {
            return [...$empty, 'error' => 'Could not find a "Description" column. Download the template to see the expected headers.'];
        }

        if (count($rows) > self::MAX_ROWS) {
            return [...$empty, 'error' => 'This file has more than ' . self::MAX_ROWS . ' rows. Please split it into smaller files and import them one at a time.'];
        }

        $valid = [];
        $errors = [];
        $lastSection = '';
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // +1 for 0-index, +1 for the header row already shifted off.
            $cell = fn (string $field): string => trim((string) ($row[$columns[$field] ?? -1] ?? ''));

            if (self::isBlankRow($row)) {
                continue;
            }

            $description = $cell('description');
            if ($description === '') {
                $errors[] = ['row' => $rowNumber, 'reason' => 'Description is required.'];

                continue;
            }
            $maxDescriptionLength = $target === 'boq' ? 500 : 255;
            if (mb_strlen($description) > $maxDescriptionLength) {
                $errors[] = ['row' => $rowNumber, 'reason' => "Description is too long (max {$maxDescriptionLength} characters)."];

                continue;
            }

            $qty = self::parseNonNegativeNumber($cell('qty'));
            if ($qty === false) {
                $errors[] = ['row' => $rowNumber, 'reason' => 'Qty must be a non-negative number.'];

                continue;
            }
            $unitPrice = self::parseNonNegativeNumber($cell('unit_price'));
            if ($unitPrice === false) {
                $errors[] = ['row' => $rowNumber, 'reason' => 'Unit price must be a non-negative number.'];

                continue;
            }

            $section = $cell('section');
            if ($section !== '') {
                $lastSection = $section;
            }

            $itemNumber = $target === 'boq' ? $cell('item_number') : '';
            if (mb_strlen($itemNumber) > 30) {
                $errors[] = ['row' => $rowNumber, 'reason' => 'Item No. is too long (max 30 characters).'];

                continue;
            }

            $uom = $cell('uom');
            $maxUomLength = $target === 'boq' ? 30 : 20;
            if (mb_strlen($uom) > $maxUomLength) {
                $errors[] = ['row' => $rowNumber, 'reason' => "UOM is too long (max {$maxUomLength} characters)."];

                continue;
            }

            $type = $target === 'estimate'
                ? (in_array(strtolower($cell('type')), self::ITEM_TYPES, true) ? strtolower($cell('type')) : 'material')
                : 'material';

            $valid[] = [
                'section' => $lastSection,
                'item_number' => $itemNumber,
                'description' => $description,
                'uom' => $uom,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'type' => $type,
            ];
        }

        return ['error' => null, 'valid' => $valid, 'errors' => $errors];
    }

    /** @return array<int, array<int, mixed>> Every sheet row (including the header) as a plain 0-indexed array of cell values. */
    private static function readRows(string $path, string $ext): array
    {
        if ($ext === 'csv') {
            $reader = new Csv;
            $reader->setDelimiter(self::detectCsvDelimiter($path));
        } else {
            $reader = IOFactory::createReaderForFile($path);
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheet(0);

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private static function detectCsvDelimiter(string $path): string
    {
        $firstLine = (string) (@fgets(fopen($path, 'r')) ?: '');

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    /** @return array<string, int> internal field name => 0-indexed column position. */
    private static function mapHeaderRow(array $headerRow): array
    {
        $normalizedToField = [];
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedToField[$alias] = $field;
            }
        }

        $columns = [];
        foreach ($headerRow as $index => $header) {
            $normalized = self::normalizeHeader((string) $header);
            if (isset($normalizedToField[$normalized]) && !isset($columns[$normalizedToField[$normalized]])) {
                $columns[$normalizedToField[$normalized]] = $index;
            }
        }

        return $columns;
    }

    private static function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', ' ', $header) ?? $header;

        return trim($header);
    }

    private static function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Parses a spreadsheet cell into a non-negative float, tolerant of thousands
     * separators and surrounding whitespace. Returns false (never a number) for
     * blank cells so callers can special-case "missing" separately (they treat
     * it as 0), and for anything non-numeric or negative — both are row errors.
     */
    private static function parseNonNegativeNumber(string $raw): float|false
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }
        $normalized = str_replace([',', ' '], '', $raw);
        if (! is_numeric($normalized)) {
            return false;
        }
        $value = (float) $normalized;

        return $value < 0 ? false : $value;
    }

    /** @return string[] Column headers for the downloadable template, in the exact order/spelling parse() recognizes. */
    public static function templateHeaders(string $target): array
    {
        return $target === 'boq'
            ? ['Section', 'Item No.', 'Description', 'UOM', 'Qty', 'Unit Price']
            : ['Section', 'Description', 'Type', 'UOM', 'Qty', 'Unit Cost'];
    }

    /** @return string[] One example data row matching templateHeaders()'s columns. */
    public static function templateExampleRow(string $target): array
    {
        return $target === 'boq'
            ? ['1.0 Concrete Works', '1.1', 'Supply and pour reinforced concrete footings', 'm3', '25', '450.00']
            : ['1.0 Site Prep & Foundations', 'Excavation and site clearance', 'labor', 'm2', '120', '35.00'];
    }
}
