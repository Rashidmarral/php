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
 * Reuses the same Anthropic config as AiEstimateGenerator (Admin > Platform Settings > AI
 * Generator) — there is no separate on/off switch for this feature. Unlike the estimate
 * generator, there is no sensible offline fallback for reading a plan image, so when AI
 * isn't configured this simply isn't available (see TakeoffController::aiAnalyze).
 */
class AiTakeoffAnalyzer
{
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
