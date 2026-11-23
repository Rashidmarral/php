<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Drafts a first-pass, client-facing "Project Update" (see ProjectUpdateController) from a
 * project's own recent activity — a short, plain-language progress note the PM always
 * reviews and can freely edit before it's ever saved, and certainly before it's ever
 * published to the client portal. Same conservative "AI proposes, a human explicitly
 * confirms" rule as every other AI feature in this app (AiTakeoffAnalyzer's
 * analyzeTakeoffPlan()/findMatchingSymbols()/detectScale(), AiEstimateGenerator's
 * generate()): this NEVER creates a ProjectUpdate row itself, it only returns text for
 * ProjectUpdateController::draftWithAi() to hand back to the form.
 *
 * Unlike AiTakeoffAnalyzer, this is a plain text-in/text-out call (no vision, no JSON to
 * parse back out) — the AI's own prose IS the output, so there's no parseXxx() here.
 *
 * Deliberately does no database work of its own: the controller gathers the project's
 * recent SiteLog/ProjectPhoto/ScheduleTask rows and hands them in as a plain array, the
 * same "gather data in the caller, call the API here" split AiEstimateGenerator uses
 * between EstimateController and buildTakeoffSummary()/buildHistoricalPricingSummary().
 */
class AiProjectUpdateDrafter
{
    /**
     * Test-only seam: when set, draft() calls this closure instead of making a real Claude
     * API call, and skips isConfigured() entirely — same shape/purpose/reset discipline as
     * AiTakeoffAnalyzer::$findMatchingSymbolsFake/$detectScaleFake (see those properties'
     * own docblocks). Always null in production; tests reset it to null in their own
     * tearDown() so it never leaks across test files.
     *
     * @var ?\Closure(array): ?string
     */
    public static ?\Closure $draftFake = null;

    public static function isConfigured(): bool
    {
        return AiEstimateGenerator::isConfigured();
    }

    /**
     * @param array{project_name:string,recent_site_logs:array<int,array{date:string,weather:?string,workers:?int,notes:?string}>,recent_photo_captions:array<int,string>,tasks_done:array<int,string>,tasks_in_progress:array<int,string>,tasks_upcoming:array<int,string>} $context Assembled entirely by the caller (ProjectUpdateController::draftWithAi()) — this class runs no queries of its own.
     * @return ?string Plain text, trimmed. Null on failure (see Setting::get('ai_last_error')).
     */
    public static function draft(array $context): ?string
    {
        if (self::$draftFake !== null) {
            return (self::$draftFake)($context);
        }

        if (!self::isConfigured()) {
            return null;
        }

        $apiKey = Setting::get('ai_api_key', '');
        $model = Setting::get('ai_model', '') ?: 'claude-sonnet-5';

        $systemPrompt = <<<PROMPT
You are helping a Saudi Arabian construction project manager write a short progress update
for their client, to post in the client's project portal. The client is not a construction
professional — write in warm, professional, plain language a non-technical homeowner or
business owner will understand easily.

You will be given the project's own recent activity: daily site log entries, photo
captions, and schedule task status. Write 2 to 4 short paragraphs summarizing what has been
happening on site, what's been completed, and what's coming up next.

Rules:
- Only mention things actually present in the supplied context below. Never invent
  progress, dates, costs, or details that aren't given to you.
- No internal jargon, no cost/budget figures, and no raw task-management language (never
  say "schedule_task" or "status: in_progress" — describe it the way you'd talk to a client).
- Keep it warm and professional, not overly formal, and keep it concise.
- Respond with plain text only — no markdown headings, no JSON, no commentary about these
  instructions.
PROMPT;

        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => self::buildContextSummary($context)]],
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

        return trim($text) ?: null;
    }

    /**
     * Assembles $context into a plain-text block for the user message — same "join whatever
     * sources are actually present" assembly style as
     * AiEstimateGenerator::buildTakeoffSummary()/buildHistoricalPricingSummary(), just text
     * sections instead of semicolon-joined summaries, since there's more than one kind of
     * source here.
     */
    private static function buildContextSummary(array $context): string
    {
        $parts = ['Project: ' . ($context['project_name'] ?? '')];

        $siteLogs = $context['recent_site_logs'] ?? [];
        if (!empty($siteLogs)) {
            $lines = array_map(function (array $log) {
                $bits = [$log['date'] ?? ''];
                if (!empty($log['weather'])) {
                    $bits[] = 'weather: ' . $log['weather'];
                }
                if (($log['workers'] ?? null) !== null) {
                    $bits[] = $log['workers'] . ' workers on site';
                }
                if (!empty($log['notes'])) {
                    $bits[] = $log['notes'];
                }
                return '- ' . implode(' — ', array_filter($bits));
            }, $siteLogs);
            $parts[] = "Recent site log entries:\n" . implode("\n", $lines);
        }

        $captions = $context['recent_photo_captions'] ?? [];
        if (!empty($captions)) {
            $parts[] = "Recent site photo captions:\n" . implode("\n", array_map(fn ($c) => '- ' . $c, $captions));
        }

        foreach ([
            'tasks_done' => 'Tasks completed',
            'tasks_in_progress' => 'Tasks currently in progress',
            'tasks_upcoming' => 'Tasks coming up next',
        ] as $key => $label) {
            $tasks = $context[$key] ?? [];
            if (!empty($tasks)) {
                $parts[] = "{$label}:\n" . implode("\n", array_map(fn ($t) => '- ' . $t, $tasks));
            }
        }

        return implode("\n\n", $parts);
    }
}
