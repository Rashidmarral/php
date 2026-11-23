<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Analyzes an uploaded takeoff plan image with Claude's vision support to pre-fill a first
 * draft of measurements (rooms, doors, windows, fixture counts, etc.) that the contractor
 * then reviews and adjusts with the existing manual takeoff tools.
 *
 * Also provides findMatchingSymbols() — "Autocount", a second, narrower vision call used by
 * TakeoffController::autocount() to find repeats of one plan symbol the user has boxed. See
 * that method's own docblock for how it mirrors STACK Construction Technologies' own feature
 * of the same name and name's conservative, review-required framing.
 *
 * Also provides describeRevisionChanges() — plan revision comparison, a third vision call
 * used by TakeoffController::compareRevision() once a page has been revised (see
 * TakeoffPage's own docblock for the version-tracking columns this builds on). See that
 * method's own docblock for its conservative framing.
 *
 * Also provides detectScale() — "Detect Scale", a fourth vision call used by
 * TakeoffController::detectScale() to read a plan's own printed graphic scale bar or a
 * labeled dimension line and propose a calibration, mirroring STACK's/PlanSwift's own "Auto
 * Scale" feature (confirmed market research, not guessed). See that method's own docblock
 * for its conservative framing and for why it only ever pre-fills the existing manual
 * calibrate() flow rather than replacing it.
 *
 * Reuses the same Anthropic config as AiEstimateGenerator (Admin > Platform Settings > AI
 * Generator) — there is no separate on/off switch for this feature. Unlike the estimate
 * generator, there is no sensible offline fallback for reading a plan image, so when AI
 * isn't configured this simply isn't available (see TakeoffController::aiAnalyze).
 */
class AiTakeoffAnalyzer
{
    /**
     * Test-only seam: when set, findMatchingSymbols() calls this closure instead of making a
     * real Claude API call, and skips isConfigured()/file-existence checks entirely — there is
     * no existing pattern anywhere in this codebase for mocking the raw curl_init() Anthropic
     * call this class makes (see TakeoffAutocountTest/TakeoffRevisionTest/
     * AiEstimateGeneratorTest's own docblocks), so TakeoffCrossSheetAutocountTest sets this to
     * exercise autocountAllPages()'s per-page success/failure handling without a live network
     * call or configuring AI settings at all. Always null in production — nothing outside a
     * test ever sets it — so this has zero effect on real behavior. Tests reset it to null in
     * their own tearDown() so it never leaks across test files.
     *
     * @var ?\Closure(string, string): ?array<int, array{x:float,y:float}>
     */
    public static ?\Closure $findMatchingSymbolsFake = null;

    /**
     * Test-only seam for detectScale() below, same shape/purpose/reset discipline as
     * $findMatchingSymbolsFake just above — set only by TakeoffScaleDetectionTest, always
     * null in production.
     *
     * @var ?\Closure(string): ?array{found:bool,x1?:float,y1?:float,x2?:float,y2?:float,real_length?:float,unit?:string,source_note?:string}
     */
    public static ?\Closure $detectScaleFake = null;

    public static function isConfigured(): bool
    {
        return AiEstimateGenerator::isConfigured();
    }

    /**
     * @param string $imagePath A single plan sheet's image path (TakeoffPage::$image_path), public_path()-relative (e.g. "/uploads/takeoffs/3/9/page-1.jpg"). Callers run this once per page (TakeoffController::aiAnalyze()/aiAnalyzeAll()) — this method itself only ever sees one image.
     * @return ?array<int, array{type:string,label:string,value:float,note:string}> Null on failure (see Setting::get('ai_last_error')).
     */
    public static function analyzeTakeoffPlan(string $imagePath): ?array
    {
        if (!self::isConfigured()) {
            return null;
        }

        $fullPath = public_path($imagePath);
        if (!is_file($fullPath)) {
            Setting::set('ai_last_error', 'Plan image file not found on disk.');
            return null;
        }

        $mediaTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mediaType = $mediaTypes[$ext] ?? 'image/jpeg';

        $imageData = @file_get_contents($fullPath);
        if ($imageData === false) {
            Setting::set('ai_last_error', 'Could not read plan image file.');
            return null;
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are a construction takeoff assistant helping a Saudi Arabian contractor read a floor plan
or site drawing image. Identify what you can actually see on the plan: rooms/spaces, doors,
windows, and any other repeated fixtures or symbols a construction plan typically shows
(electrical points, plumbing fixtures, columns, etc.).

Rules:
- Only report a numeric length/area for a room or space when printed dimension text is visible
  on the drawing supporting that number (e.g. a "4.20m x 3.50m" label, or a printed area figure).
  Never estimate or guess a dimension you cannot actually read off the image.
- If a room has no visible printed dimensions, still report it, but as a "count" item (value 1)
  rather than inventing a length or area.
- For doors, windows, and repeated fixtures/symbols, count how many instances you can see and
  report a single "count" item per symbol type with the total.
- All lengths/areas are in meters / square meters.

Respond with ONLY valid JSON (no markdown fences, no commentary): an array of objects, each
shaped exactly like:
{"type":"length"|"area"|"count","label":"short name","value":number,"note":"how you arrived at this, e.g. 'printed as 4.20m x 3.50m' or 'counted from plan symbols'"}
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($imageData)]],
                    ['type' => 'text', 'text' => 'Analyze this construction plan and list what you can identify, following the rules in your instructions.'],
                ],
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            Setting::set('ai_last_error', $curlError ?: "HTTP {$httpCode}: " . substr((string) $response, 0, 300));
            return null;
        }

        $body = json_decode($response, true);
        $text = $body['content'][0]['text'] ?? null;
        if (!$text) {
            Setting::set('ai_last_error', 'Unexpected API response shape.');
            return null;
        }

        return self::parseItems($text);
    }

    /**
     * "Autocount" — mirrors STACK Construction Technologies' own repeated-symbol-detection
     * feature (confirmed market research, not guessed): the user boxes ONE instance of a
     * repeated plan symbol (a door swing, a window tag, a fixture icon) and this hunts for
     * other visually-matching instances elsewhere on the same sheet. Like STACK's own tool,
     * this is image-pattern matching via Claude vision, NOT reliable object/symbol
     * recognition — see TakeoffController::autocount()'s review step, which (like STACK's
     * own Found/Missed/Wrong-Shape review) never auto-accepts a single detection. Just as
     * analyzeTakeoffPlan() above is told never to invent a dimension it can't read, this is
     * told never to invent a match count — honestly reporting zero when there genuinely are
     * none is the correct, expected result for a sheet with no repeats of the boxed symbol.
     *
     * @param string $pageImagePath The full plan sheet to search, public_path()-relative exactly like analyzeTakeoffPlan()'s $imagePath (e.g. TakeoffPage::$image_path).
     * @param string $cropImagePath The cropped reference symbol — an absolute filesystem path to a temp file (not a TakeoffPage, so no public_path()-relative convention applies to it). The caller owns this file and must delete it; this method only ever reads it.
     * @return ?array<int, array{x:float,y:float}> Each match's approximate center point, in the full page image's own pixel coordinates. Empty array means "configured correctly, searched, found nothing" — a legitimate result. Null on failure (see Setting::get('ai_last_error')).
     */
    public static function findMatchingSymbols(string $pageImagePath, string $cropImagePath): ?array
    {
        if (self::$findMatchingSymbolsFake !== null) {
            return (self::$findMatchingSymbolsFake)($pageImagePath, $cropImagePath);
        }

        if (!self::isConfigured()) {
            return null;
        }

        $fullPagePath = public_path($pageImagePath);
        if (!is_file($fullPagePath) || !is_file($cropImagePath)) {
            Setting::set('ai_last_error', 'Plan image or reference crop file not found on disk.');
            return null;
        }

        $pageData = @file_get_contents($fullPagePath);
        $cropData = @file_get_contents($cropImagePath);
        if ($pageData === false || $cropData === false) {
            Setting::set('ai_last_error', 'Could not read plan image or reference crop file.');
            return null;
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are helping a Saudi Arabian contractor use "Autocount" on a construction plan sheet — the
same pattern-matching idea STACK Construction Technologies' Takeoff tool uses: the user has
boxed ONE instance of a repeated plan symbol (e.g. a door swing, a window tag, an electrical
outlet icon), shown to you first as a small reference crop. Your job is to find every OTHER
instance of that same visual symbol anywhere in the second image (the full plan sheet),
allowing for reasonable size and rotation variance (the symbol may be flipped or mirrored
elsewhere on the plan, as door swings often are).

Rules:
- Only report a match you can actually see — a genuine visual match to the reference symbol's
  shape and line pattern, not just "something plausible might be near here."
- It is correct and expected to report zero matches when the sheet genuinely has no other
  instances of this symbol. Never invent an approximate count to seem more useful.
- Do not re-report the reference instance itself as a match, only OTHER instances elsewhere on
  the sheet.
- Report each match's approximate center point as pixel coordinates within the full plan sheet
  image, exactly as that image is sized (not the small reference crop).

Respond with ONLY valid JSON (no markdown fences, no commentary): an array of objects, each
shaped exactly like:
{"x":number,"y":number}
An empty array [] is a valid and often correct response.
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Reference symbol (the one instance already found — find other matches to this elsewhere on the full sheet below):'],
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::mediaTypeFor($cropImagePath), 'data' => base64_encode($cropData)]],
                    ['type' => 'text', 'text' => 'Full plan sheet — search this entire image for every other instance of the reference symbol above:'],
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::mediaTypeFor($fullPagePath), 'data' => base64_encode($pageData)]],
                ],
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            Setting::set('ai_last_error', $curlError ?: "HTTP {$httpCode}: " . substr((string) $response, 0, 300));
            return null;
        }

        $body = json_decode($response, true);
        $text = $body['content'][0]['text'] ?? null;
        if (!$text) {
            Setting::set('ai_last_error', 'Unexpected API response shape.');
            return null;
        }

        return self::parseMatches($text);
    }

    /**
     * "Detect Scale" — mirrors STACK/PlanSwift's own "Auto Scale" feature (confirmed market
     * research, not guessed): instead of the user manually clicking two points on a known-
     * length line and typing its real length (TakeoffController::calibrate()'s own manual
     * flow, which this NEVER bypasses — see that method's own docblock), this reads the
     * plan sheet itself for something that already states its own real-world length: either
     * a printed graphic/bar scale (a short ruled line with tick marks and a label like
     * "0 ... 5.00 m"), or, failing that, a clearly labeled dimension line elsewhere on the
     * sheet (e.g. a wall printed as "4.20 m" with its own dimension line/arrows). Reports
     * back the pixel coordinates of that reference's two end points, in the full image's own
     * pixel space — the exact same coordinate convention findMatchingSymbols() above already
     * uses successfully for match points — plus the real-world length and unit it represents.
     *
     * Same conservative "never guess what you can't actually see" philosophy as every other
     * method in this file: explicitly told to report {"found":false} rather than inventing a
     * scale reference when the sheet genuinely has none legible. The caller
     * (TakeoffController::detectScale()) only ever uses a successful result to PRE-FILL the
     * existing manual calibration UI (the two canvas points + the real-length input) — it
     * never saves a calibration itself, and the user still reviews the drawn line and the
     * pre-filled number and clicks the existing "Save scale" button themselves.
     *
     * @param string $imagePath A single plan sheet's image path (TakeoffPage::$image_path), public_path()-relative, same convention as analyzeTakeoffPlan()'s $imagePath.
     * @return ?array{found:bool,x1?:float,y1?:float,x2?:float,y2?:float,real_length?:float,unit?:string,source_note?:string} {"found":false} is a legitimate, non-error result when no legible scale bar or labeled dimension exists on the sheet. Null on failure (see Setting::get('ai_last_error')).
     */
    public static function detectScale(string $imagePath): ?array
    {
        if (self::$detectScaleFake !== null) {
            return (self::$detectScaleFake)($imagePath);
        }

        if (!self::isConfigured()) {
            return null;
        }

        $fullPath = public_path($imagePath);
        if (!is_file($fullPath)) {
            Setting::set('ai_last_error', 'Plan image file not found on disk.');
            return null;
        }

        $imageData = @file_get_contents($fullPath);
        if ($imageData === false) {
            Setting::set('ai_last_error', 'Could not read plan image file.');
            return null;
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are helping a Saudi Arabian contractor set up the scale on a construction plan sheet
image, the same idea as STACK Construction Technologies' and PlanSwift's own "Auto Scale"
features. Look for ONE of the following, whichever is more clearly legible on this sheet:
1. A printed graphic/bar scale — a short ruled line with tick marks and a labeled
   real-world length (e.g. a line reading "0 ... 5.00 m" or "0 ... 10m").
2. A clearly labeled dimension line elsewhere on the sheet — a printed measurement with its
   own dimension line and arrows (e.g. a wall printed as "4.20 m").

Rules:
- Only report a scale reference you can actually see printed on the sheet, with a real
  legible label. Never invent or estimate a scale reference, or a length for one, that isn't
  clearly printed — honestly reporting that none was found is the correct, expected result
  for a sheet with no legible scale bar or labeled dimension.
- Report the two end points of whichever reference you found as pixel coordinates within
  this image, exactly as it is sized.
- The real-world length is whatever number is printed next to the reference (e.g. 5.00 for
  "5.00 m"), and the unit is whatever is printed with it (e.g. "m", "ft").

Respond with ONLY valid JSON (no markdown fences, no commentary), shaped exactly like one of:
{"found":true,"x1":number,"y1":number,"x2":number,"y2":number,"real_length":number,"unit":"m","source_note":"short description of what was found, e.g. 'graphic scale bar at bottom of sheet, labeled 5.00 m'"}
{"found":false}
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::mediaTypeFor($fullPath), 'data' => base64_encode($imageData)]],
                    ['type' => 'text', 'text' => 'Find this plan sheet\'s printed scale reference, following the rules in your instructions.'],
                ],
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            Setting::set('ai_last_error', $curlError ?: "HTTP {$httpCode}: " . substr((string) $response, 0, 300));
            return null;
        }

        $body = json_decode($response, true);
        $text = $body['content'][0]['text'] ?? null;
        if (!$text) {
            Setting::set('ai_last_error', 'Unexpected API response shape.');
            return null;
        }

        return self::parseScaleDetection($text);
    }

    /**
     * Plan revision comparison — sends BOTH a page's previous and current plan images to
     * Claude vision in one call and asks for a short, plain-language list of concrete,
     * visible differences between them (a new/moved/removed wall, door, window, room, or a
     * changed printed dimension). Backs TakeoffController::compareRevision(), used once a
     * page has been revised (TakeoffPage::$supersedes_id set — see reviseUpload()).
     *
     * Same conservative, "never guess, only report what you can actually see" philosophy as
     * analyzeTakeoffPlan() above: this is explicitly told to say "no obvious differences
     * detected" rather than inventing a change when the two images look the same, and
     * findings are always presented to the user as AI-assisted and worth double-checking
     * against the actual drawings, never as an authoritative diff.
     *
     * @param string $oldImagePath The superseded page's image path (TakeoffPage::$image_path), public_path()-relative exactly like analyzeTakeoffPlan()'s $imagePath.
     * @param string $newImagePath The current page's image path, same convention.
     * @return ?array<int, string> Each entry is one short plain-language finding. Null on failure (see Setting::get('ai_last_error')).
     */
    public static function describeRevisionChanges(string $oldImagePath, string $newImagePath): ?array
    {
        if (!self::isConfigured()) {
            return null;
        }

        $fullOldPath = public_path($oldImagePath);
        $fullNewPath = public_path($newImagePath);
        if (!is_file($fullOldPath) || !is_file($fullNewPath)) {
            Setting::set('ai_last_error', 'Plan image file not found on disk.');
            return null;
        }

        $oldData = @file_get_contents($fullOldPath);
        $newData = @file_get_contents($fullNewPath);
        if ($oldData === false || $newData === false) {
            Setting::set('ai_last_error', 'Could not read plan image file.');
            return null;
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are helping a Saudi Arabian contractor compare two versions of the same construction plan
sheet — an original and a revised version (e.g. after an architect's addendum or a client change
request). The first image is the OLD (previous) version; the second image is the NEW (current)
version.

Rules:
- Only report a difference you can actually see — a concrete, visible change such as a new,
  moved, or removed wall, door, window, or room, or a printed dimension that reads differently
  between the two images.
- If the two images look the same, or any differences are too minor/ambiguous to describe
  concretely, respond with exactly one finding: "No obvious differences detected." Never invent
  a change to seem more useful — honestly reporting no differences is the correct, expected
  result when a sheet genuinely wasn't changed.
- Keep each finding short and in plain language (one sentence), the kind a contractor could
  immediately act on, e.g. "A new door opening appears on the north wall" or "The living room
  width changed from 4.20m to 4.60m".

Respond with ONLY valid JSON (no markdown fences, no commentary): an array of strings, each one
plain-language finding.
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'OLD (previous) version of this plan sheet:'],
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::mediaTypeFor($fullOldPath), 'data' => base64_encode($oldData)]],
                    ['type' => 'text', 'text' => 'NEW (current) version of this plan sheet — describe what visibly changed compared to the OLD version above:'],
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => self::mediaTypeFor($fullNewPath), 'data' => base64_encode($newData)]],
                ],
            ]],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || !$response) {
            Setting::set('ai_last_error', $curlError ?: "HTTP {$httpCode}: " . substr((string) $response, 0, 300));
            return null;
        }

        $body = json_decode($response, true);
        $text = $body['content'][0]['text'] ?? null;
        if (!$text) {
            Setting::set('ai_last_error', 'Unexpected API response shape.');
            return null;
        }

        return self::parseFindings($text);
    }

    /** @return ?array<int, string> */
    public static function parseFindings(string $text): ?array
    {
        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        $findings = [];
        foreach ($parsed as $f) {
            if (!is_string($f) && !is_numeric($f)) {
                continue;
            }
            $finding = trim((string) $f);
            if ($finding !== '') {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /** @return ?array<int, array{x:float,y:float}> */
    public static function parseMatches(string $text): ?array
    {
        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        $points = [];
        foreach ($parsed as $p) {
            if (!is_array($p) || !is_numeric($p['x'] ?? null) || !is_numeric($p['y'] ?? null)) {
                continue;
            }
            $points[] = ['x' => (float) $p['x'], 'y' => (float) $p['y']];
        }

        return $points;
    }

    /** @return ?array{found:bool,x1?:float,y1?:float,x2?:float,y2?:float,real_length?:float,unit?:string,source_note?:string} */
    public static function parseScaleDetection(string $text): ?array
    {
        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        if (empty($parsed['found'])) {
            return ['found' => false];
        }

        $coords = ['x1', 'y1', 'x2', 'y2', 'real_length'];
        foreach ($coords as $key) {
            if (!is_numeric($parsed[$key] ?? null)) {
                return ['found' => false];
            }
        }
        if ((float) $parsed['real_length'] <= 0) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'x1' => (float) $parsed['x1'],
            'y1' => (float) $parsed['y1'],
            'x2' => (float) $parsed['x2'],
            'y2' => (float) $parsed['y2'],
            'real_length' => (float) $parsed['real_length'],
            'unit' => trim((string) ($parsed['unit'] ?? '')) ?: 'm',
            'source_note' => trim((string) ($parsed['source_note'] ?? '')),
        ];
    }

    /** Same jpg/jpeg/png/webp → media-type mapping analyzeTakeoffPlan() uses inline, shared here since findMatchingSymbols() needs it for two files instead of one. */
    private static function mediaTypeFor(string $path): string
    {
        $mediaTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $mediaTypes[$ext] ?? 'image/jpeg';
    }

    /** @return ?array<int, array{type:string,label:string,value:float,note:string}> */
    public static function parseItems(string $text): ?array
    {
        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        $items = [];
        foreach ($parsed as $i) {
            if (!is_array($i)) {
                continue;
            }
            $value = (float) ($i['value'] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $type = in_array($i['type'] ?? '', ['length', 'area', 'count'], true) ? $i['type'] : 'count';
            $items[] = [
                'type' => $type,
                'label' => trim((string) ($i['label'] ?? ucfirst($type))) ?: ucfirst($type),
                'value' => $value,
                'note' => trim((string) ($i['note'] ?? '')),
            ];
        }

        return $items;
    }
}
