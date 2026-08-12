<?php

namespace App\Support;

use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use App\Models\Setting;

/**
 * Generates estimate line items from a plain-language project description.
 *
 * When an Anthropic API key is configured (Admin > Platform Settings > AI Generator),
 * calls the real Claude API for a tailored breakdown. With no key configured, falls
 * back to keyword-matching the description against the built-in template library so
 * the feature still produces something useful with zero setup — the same zero-config
 * fallback pattern used elsewhere in this app (e.g. WhatsApp wa.me links).
 */
class AiEstimateGenerator
{
    public static function isConfigured(): bool
    {
        return Setting::get('ai_enabled') === '1' && Setting::get('ai_api_key', '') !== '';
    }

    /** @return array{title:string,items:array,source:string,note:?string} */
    public static function generate(string $description): array
    {
        if (self::isConfigured()) {
            $result = self::generateViaAnthropic($description);
            if ($result !== null) {
                return $result;
            }
        }
        return self::generateViaTemplateFallback($description);
    }

    private static function generateViaAnthropic(string $description): ?array
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

    private static function generateViaTemplateFallback(string $description): array
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
            return ['title' => 'New estimate', 'items' => [], 'source' => 'ai_fallback', 'note' => 'No templates available to suggest from — add line items manually.'];
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

        return [
            'title' => $bestTemplate->name_en,
            'items' => $items,
            'source' => 'ai_fallback',
            'note' => 'No AI provider is configured yet (Admin > Platform Settings > AI Generator), so this draft was suggested from the closest matching template — "' . $bestTemplate->name_en . '". Review and adjust before sending.',
        ];
    }
}
