<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Analyzes an uploaded takeoff plan image with Claude's vision support to pre-fill a first
 * draft of measurements (rooms, doors, windows, fixture counts, etc.) that the contractor
 * then reviews and adjusts with the existing manual takeoff tools.
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
     * @param string $imagePath The takeoff's plan_image_path, public_path()-relative (e.g. "/uploads/takeoffs/3/abc.jpg").
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
