<?php

namespace App\Support;

use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use App\Models\Setting;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use Illuminate\Support\Facades\DB;

/**
 * Generates estimate line items from a plain-language project description, optionally
 * together with up to 5 uploaded photos of the site/room/plan (analyzed with Claude's
 * vision support, the same technique as AiTakeoffAnalyzer — see
 * generateViaAnthropicWithImages()), grounded in two further, purely additive sources of
 * real data layered into the same system prompt:
 *   - the selected project's own real digital-takeoff measurements (buildTakeoffSummary()),
 *     excluding cutouts, which are never a real-world quantity of their own — see
 *     TakeoffController::convertToEstimate()'s identical exclusion;
 *   - this company's own historical average unit costs per cost code, from its past
 *     estimates (buildHistoricalPricingSummary()), strictly scoped to the calling company.
 *
 * When an Anthropic API key is configured (Admin > Platform Settings > AI Generator),
 * calls the real Claude API for a tailored breakdown. With no key configured, falls
 * back to keyword-matching the description against the built-in template library so
 * the feature still produces something useful with zero setup — the same zero-config
 * fallback pattern used elsewhere in this app (e.g. WhatsApp wa.me links). There is no
 * sensible offline fallback for an uploaded photo, so a photo that couldn't be analyzed
 * is simply dropped and the fallback note says so honestly. The template fallback also
 * never uses the takeoff/historical-pricing context — it's a dumb keyword-matcher by
 * design, see generateViaTemplateFallback()'s own docblock.
 */
class AiEstimateGenerator
{
    public static function isConfigured(): bool
    {
        return Setting::get('ai_enabled') === '1' && Setting::get('ai_api_key', '') !== '';
    }

    /**
     * @param string[] $imagePaths Up to 5 optional uploaded photo/plan images, each
     *   public_path()-relative (e.g. "/uploads/ai-estimate-tmp/3/abc.jpg"), analyzed
     *   together with the description when AI is configured. There is no offline fallback
     *   for reading a photo (same stance as AiTakeoffAnalyzer), so when AI isn't configured
     *   or the call fails, the photos are simply not used — the note says so honestly
     *   rather than pretending they were analyzed.
     * @param ?int $projectId Optional selected project — when it has real (non-cutout)
     *   takeoff measurements, they're summarized and sent as authoritative ground truth
     *   (see buildTakeoffSummary()). Purely additive: no project, or a project with no
     *   takeoffs, behaves exactly as before this was added.
     * @param ?int $companyId The calling user's company — used ONLY to scope the historical
     *   pricing lookup (see buildHistoricalPricingSummary()) to that company's own past
     *   estimates. Never omit this for a real request or another company's pricing data
     *   could leak; null simply skips historical grounding (e.g. for callers that don't
     *   have a company context).
     * @return array{title:string,items:array,source:string,note:?string}
     */
    public static function generate(string $description, array $imagePaths = [], ?int $projectId = null, ?int $companyId = null): array
    {
        $imagePaths = array_values(array_filter($imagePaths, fn ($p) => is_string($p) && $p !== ''));

        if (self::isConfigured()) {
            $takeoffSummary = self::buildTakeoffSummary($projectId);
            $historicalPricing = $companyId !== null ? self::buildHistoricalPricingSummary($companyId) : null;

            $result = !empty($imagePaths)
                ? self::generateViaAnthropicWithImages($description, $imagePaths, $takeoffSummary, $historicalPricing)
                : self::generateViaAnthropic($description, $takeoffSummary, $historicalPricing);
            if ($result !== null) {
                return $result;
            }
            // AI was configured but the call itself failed — say so, don't claim
            // there's no provider configured (the real reason is in ai_last_error).
            $fallback = self::generateViaTemplateFallback($description, true);
            if (!empty($imagePaths)) {
                $fallback['note'] = t('user.estimates.ai_note_photo_call_failed', ['reason' => Setting::get('ai_last_error', 'unknown error')]);
            }
            return $fallback;
        }

        $fallback = self::generateViaTemplateFallback($description);
        if (!empty($imagePaths)) {
            $fallback['note'] = t('user.estimates.ai_note_photo_not_configured');
        }
        return $fallback;
    }

    /**
     * $projectId's real takeoff measurements (label, net value, unit, type), one per
     * semicolon-separated entry, e.g. "Living Room floor: 24.50 m² (area); Door: 3.00 ea
     * (count)" — sent in the system prompt as authoritative real-world data the AI must
     * use for any corresponding line item rather than guessing. Cutout measurements are
     * excluded: they only ever reduce their parent's own net value (TakeoffMeasurement's
     * `value` column is already net of its cutouts — see the
     * 2026_11_19_000200_add_cutout_fields_to_takeoff_measurements_table migration) and are
     * never a real-world quantity of their own, mirroring
     * TakeoffController::convertToEstimate()'s identical `where('is_cutout', false)`.
     * Returns null for no project, or a project with no takeoffs/measurements — the caller
     * must treat that exactly like no grounding data was ever requested.
     */
    public static function buildTakeoffSummary(?int $projectId): ?string
    {
        if (!$projectId) {
            return null;
        }

        $takeoffIds = Takeoff::where('project_id', $projectId)->pluck('id');
        if ($takeoffIds->isEmpty()) {
            return null;
        }

        $measurements = TakeoffMeasurement::whereIn('takeoff_id', $takeoffIds)
            ->where('is_cutout', false)
            ->orderBy('id')
            ->get(['label', 'value', 'unit', 'type']);

        if ($measurements->isEmpty()) {
            return null;
        }

        return $measurements
            ->map(fn ($m) => sprintf('%s: %s %s (%s)', $m->label, number_format((float) $m->value, 2), $m->unit, $m->type))
            ->implode('; ');
    }

    /**
     * This company's own historical average unit cost per cost code, from its past
     * EstimateItem rows — strictly scoped to $companyId via the owning Estimate's
     * company_id, since this must never leak another company's pricing data. Only cost
     * codes used on 2+ past items are included (avoids anchoring the AI on a single
     * noisy outlier), capped at the 15 most-frequently-used, most-used first. Returns
     * null for a brand-new company with no usable history — purely additive, never an
     * error, and the prompt is simply unaffected.
     */
    public static function buildHistoricalPricingSummary(int $companyId): ?string
    {
        $rows = DB::table('estimate_items as ei')
            ->join('estimates as e', 'e.id', '=', 'ei.estimate_id')
            ->join('cost_codes as cc', 'cc.id', '=', 'ei.cost_code_id')
            ->where('e.company_id', $companyId)
            ->whereNotNull('ei.cost_code_id')
            ->groupBy('ei.cost_code_id', 'cc.code', 'cc.description')
            ->havingRaw('count(*) >= 2')
            ->orderByDesc('uses')
            ->limit(15)
            ->selectRaw('cc.code as code, cc.description as description, count(*) as uses, avg(ei.unit_cost) as avg_cost')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows
            ->map(fn ($r) => sprintf('%s %s — %s SAR/unit (avg of %d past jobs)', $r->code, $r->description, number_format((float) $r->avg_cost, 2), $r->uses))
            ->implode('; ');
    }

    /**
     * Appended to either Anthropic system prompt (text-only or with photos) when a
     * takeoff summary and/or historical pricing context is available. Purely additive —
     * returns '' when both are null, leaving the existing prompt untouched.
     */
    private static function buildContextAddendum(?string $takeoffSummary, ?string $historicalPricing): string
    {
        $parts = [];
        if ($takeoffSummary !== null) {
            $parts[] = 'AUTHORITATIVE REAL-WORLD MEASUREMENTS — these were taken directly from this '
                . "project's own digital takeoff survey. For any line item that corresponds to one of "
                . 'them, use this EXACT quantity rather than guessing from the description or any photo: '
                . $takeoffSummary;
        }
        if ($historicalPricing !== null) {
            $parts[] = "THIS COMPANY'S OWN HISTORICAL UNIT COSTS — real average unit costs this company "
                . 'has actually paid on its own past jobs. Prefer these real figures over a generic Saudi '
                . 'market guess whenever a generated line item corresponds to one of them: '
                . $historicalPricing;
        }

        return $parts === [] ? '' : "\n\n" . implode("\n\n", $parts);
    }

    private static function generateViaAnthropic(string $description, ?string $takeoffSummary = null, ?string $historicalPricing = null): ?array
    {
        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are a construction estimating assistant for a Saudi Arabian contractor. Given a project
description, produce a realistic, itemized cost breakdown priced in Saudi Riyal (SAR).

Respond with ONLY valid JSON (no markdown fences, no commentary) in this exact shape:
{"title":"short project title","items":[{"section_title":"e.g. 1.0 Demolition","description":"line item description","type":"material or labor","qty":number,"uom":"sqm|m3|lm|each|lot|hr|ton|point|kg","unit_cost":number}]}

Group items into 2-5 logical sections. Use realistic Saudi market unit costs. Produce 8-20 line items total.
PROMPT;
        $systemPrompt .= self::buildContextAddendum($takeoffSummary, $historicalPricing);

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $description]],
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
            CURLOPT_TIMEOUT => 45,
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

        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || empty($parsed['items'])) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        $items = [];
        foreach ($parsed['items'] as $i) {
            $items[] = [
                'section_title' => (string) ($i['section_title'] ?? 'Line items'),
                'description' => (string) ($i['description'] ?? ''),
                'item_type' => in_array($i['type'] ?? '', ['material', 'labor'], true) ? $i['type'] : 'material',
                'qty' => (float) ($i['qty'] ?? 1),
                'uom' => (string) ($i['uom'] ?? 'each'),
                'unit_cost' => (float) ($i['unit_cost'] ?? 0),
            ];
        }

        return [
            'title' => (string) ($parsed['title'] ?? 'AI-generated estimate'),
            'items' => $items,
            'source' => 'ai',
            'note' => null,
        ];
    }

    /**
     * Same as generateViaAnthropic() but also sends up to 5 uploaded photos of the actual
     * site/room/plan alongside the text description, one {type: image, ...} content block
     * per photo (all before the final text block, in the same message — Claude's vision
     * support accepts several images in one message) — same image-encoding technique as
     * AiTakeoffAnalyzer::analyzeTakeoffPlan(), just looped over N files now.
     */
    private static function generateViaAnthropicWithImages(string $description, array $imagePaths, ?string $takeoffSummary = null, ?string $historicalPricing = null): ?array
    {
        $mediaTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        $imageBlocks = [];
        foreach ($imagePaths as $imagePath) {
            $fullPath = public_path($imagePath);
            if (!is_file($fullPath)) {
                Setting::set('ai_last_error', 'Photo file not found on disk.');
                return null;
            }

            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mediaType = $mediaTypes[$ext] ?? 'image/jpeg';

            $imageData = @file_get_contents($fullPath);
            if ($imageData === false) {
                Setting::set('ai_last_error', 'Could not read photo file.');
                return null;
            }

            $imageBlocks[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($imageData)]];
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are a construction estimating assistant for a Saudi Arabian contractor. You will receive one
or more photos of the actual site, room, or plan alongside a text description. Use what is visible
in the photos — existing condition, approximate scale/quantities, visible damage, room layout —
together with the text description to produce a more accurate itemized cost breakdown priced in
Saudi Riyal (SAR).

Respond with ONLY valid JSON (no markdown fences, no commentary) in this exact shape:
{"title":"short project title","items":[{"section_title":"e.g. 1.0 Demolition","description":"line item description","type":"material or labor","qty":number,"uom":"sqm|m3|lm|each|lot|hr|ton|point|kg","unit_cost":number}]}

Group items into 2-5 logical sections. Use realistic Saudi market unit costs. Produce 8-20 line items total.
PROMPT;
        $systemPrompt .= self::buildContextAddendum($takeoffSummary, $historicalPricing);

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ...$imageBlocks,
                    ['type' => 'text', 'text' => $description !== '' ? $description : 'See attached photo(s).'],
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

        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || empty($parsed['items'])) {
            Setting::set('ai_last_error', 'Could not parse AI response as JSON.');
            return null;
        }

        $items = [];
        foreach ($parsed['items'] as $i) {
            $items[] = [
                'section_title' => (string) ($i['section_title'] ?? 'Line items'),
                'description' => (string) ($i['description'] ?? ''),
                'item_type' => in_array($i['type'] ?? '', ['material', 'labor'], true) ? $i['type'] : 'material',
                'qty' => (float) ($i['qty'] ?? 1),
                'uom' => (string) ($i['uom'] ?? 'each'),
                'unit_cost' => (float) ($i['unit_cost'] ?? 0),
            ];
        }

        return [
            'title' => (string) ($parsed['title'] ?? 'AI-generated estimate'),
            'items' => $items,
            'source' => 'ai',
            'note' => null,
        ];
    }

    /**
     * @param bool $aiCallFailed True when this is being used because a configured
     *   AI provider's call failed (bad key, rate limit, network error, unparseable
     *   response — see generateViaAnthropic()), rather than because no provider is
     *   configured at all. Changes the returned note so the user isn't told "no
     *   provider configured" when one is configured but broken.
     */
    private static function generateViaTemplateFallback(string $description, bool $aiCallFailed = false): array
    {
        $templates = EstimateTemplate::where('is_active', true)->orderBy('sort_order')->get();
        $words = array_filter(preg_split('/[^a-z0-9]+/i', strtolower($description)) ?: []);

        $bestTemplate = null;
        $bestScore = -1;
        foreach ($templates as $t) {
            $haystack = strtolower($t->name_en . ' ' . $t->description_en . ' ' . $t->building_type);
            $score = 0;
            foreach ($words as $w) {
                if (strlen($w) > 2 && str_contains($haystack, $w)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTemplate = $t;
            }
        }

        if (!$bestTemplate) {
            return ['title' => t('user.estimates.ai_fallback_title'), 'items' => [], 'source' => 'ai_fallback', 'note' => t('user.estimates.ai_note_no_templates')];
        }

        $templateItems = EstimateTemplateItem::where('template_id', $bestTemplate->id)->orderBy('sort_order')->orderBy('id')->get();
        $items = $templateItems->map(fn ($ti) => [
            'section_title' => $ti->section_number . ' ' . $ti->section_title_en,
            'description' => $ti->description_en,
            'item_type' => $ti->item_type,
            'qty' => (float) $ti->default_qty,
            'uom' => $ti->uom,
            'unit_cost' => (float) $ti->unit_cost,
        ])->all();

        $note = $aiCallFailed
            ? t('user.estimates.ai_note_call_failed', ['reason' => Setting::get('ai_last_error', 'unknown error')])
            : t('user.estimates.ai_note_not_configured', ['template' => $bestTemplate->name_en]);

        return [
            'title' => $bestTemplate->name_en,
            'items' => $items,
            'source' => 'ai_fallback',
            'note' => $note,
        ];
    }
}
