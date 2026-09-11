<?php

namespace App\Console\Commands;

use App\Models\BankGuarantee;
use App\Models\Company;
use App\Models\ComplianceDocument;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TeamMemberDocument;
use App\Models\User;
use App\Support\Moyasar;
use App\Support\Notifications;
use Illuminate\Console\Command;

/**
 * Daily background jobs — subscription auto-renewal, trial-ending reminders,
 * compliance-document expiry reminders, team-member-document (iqama, health
 * certificate, etc.) expiry reminders, and bank-guarantee/bond expiry reminders.
 * Scheduled once a day (see routes/console.php).
 *
 * Safe to run more than once a day — every action here checks state before acting
 * (a subscription already renewed today won't be charged again, a reminder already sent
 * won't be re-sent), so an extra run just does nothing on the parts that are already done.
 */
class RunDailyTasks extends Command
{
    protected $signature = 'app:daily-tasks';

    protected $description = 'Subscription auto-renewal, trial-ending reminders, and compliance-document expiry reminders';

    private const MAX_RETRIES = 3;

    public function handle(): int
    {
        $today = now()->format('Y-m-d');

        // ---- 1. Auto-renew subscriptions whose period has ended and a saved card is on file ----
        $dueSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', $today)
            ->whereNotNull('moyasar_card_token')
            ->where('moyasar_card_token', '!=', '')
            ->get();

        $renewed = 0;
        $failed = 0;

        foreach ($dueSubscriptions as $sub) {
            $company = Company::find($sub->company_id);
            $plan = Plan::find($sub->plan_id);
            if (!$company || !$plan) {
                continue;
            }

            $amount = (float) ($sub->billing_cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);
            $result = Moyasar::chargeToken($sub->moyasar_card_token, (int) round($amount * 100), $plan->name . ' plan renewal');
            $ok = $result && ($result['status'] ?? '') === 'paid';

            if ($ok) {
                $nextPeriodEnd = $sub->current_period_end->copy()->add($sub->billing_cycle === 'yearly' ? '1 year' : '30 days');
                $sub->update(['current_period_end' => $nextPeriodEnd, 'retry_count' => 0]);
                if ($company->status === 'past_due') {
                    $company->update(['status' => 'active']);
                }
                Payment::create([
                    'company_id' => $company->id,
                    'subscription_id' => $sub->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $sub->billing_cycle,
                    'amount' => $amount,
                    'currency' => 'SAR',
                    'method' => 'moyasar',
                    'reference' => (string) ($result['id'] ?? ''),
                    'status' => 'paid',
                ]);
                Notifications::subscriptionRenewed($company, $amount);
                $renewed++;
            } else {
                $retryCount = $sub->retry_count + 1;
                $sub->update(['retry_count' => $retryCount]);
                if ($retryCount >= self::MAX_RETRIES) {
                    $sub->update(['status' => 'past_due']);
                    $company->update(['status' => 'past_due']);
                }
                Notifications::renewalFailed($company, $retryCount);
                $failed++;
            }
        }
        $this->info("Renewals: {$renewed} succeeded, {$failed} failed.");

        // ---- 2. Remind trials ending in the next 3 days (once per company) ----
        $reminderCutoff = now()->addDays(3)->format('Y-m-d');
        $endingTrials = Company::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $reminderCutoff)
            ->where('trial_ends_at', '>=', $today)
            ->whereNull('trial_reminder_sent_at')
            ->get();

        $trialReminders = 0;
        foreach ($endingTrials as $company) {
            $daysLeft = max(0, (int) ceil((strtotime($company->trial_ends_at->format('Y-m-d')) - strtotime($today)) / 86400));
            Notifications::trialEndingSoon($company, $daysLeft);
            $company->update(['trial_reminder_sent_at' => now()]);
            $trialReminders++;
        }
        $this->info("Trial-ending reminders sent: {$trialReminders}.");

        // ---- 3. Remind companies about compliance documents expiring within 30 days (once per document) ----
        $docCutoff = now()->addDays(30)->format('Y-m-d');
        $expiringDocs = ComplianceDocument::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $docCutoff)
            ->whereNull('reminder_sent_at')
            ->get();

        $docReminders = 0;
        foreach ($expiringDocs as $doc) {
            $company = Company::find($doc->company_id);
            if (!$company) {
                continue;
            }
            Notifications::complianceDocumentExpiring($company, $doc);
            $doc->update(['reminder_sent_at' => now()]);
            $docReminders++;
        }
        $this->info("Compliance document reminders sent: {$docReminders}.");

        // ---- 4. Remind companies about team-member documents (iqama, health certificate, etc.)
        //         expiring within 30 days (once per document) ----
        $memberDocCutoff = now()->addDays(30)->format('Y-m-d');
        $expiringMemberDocs = TeamMemberDocument::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $memberDocCutoff)
            ->whereNull('reminder_sent_at')
            ->get();

        $memberDocReminders = 0;
        foreach ($expiringMemberDocs as $doc) {
            $company = Company::find($doc->company_id);
            $member = User::find($doc->user_id);
            if (!$company || !$member) {
                continue;
            }
            Notifications::teamMemberDocumentExpiring($company, $member, $doc);
            $doc->update(['reminder_sent_at' => now()]);
            $memberDocReminders++;
        }
        $this->info("Team member document reminders sent: {$memberDocReminders}.");

        // ---- 5. Remind companies about active bank guarantees/bonds expiring within 30 days
        //         (once per guarantee) — released, claimed, or already-expired ones don't need renewing ----
        $guaranteeCutoff = now()->addDays(30)->format('Y-m-d');
        $expiringGuarantees = BankGuarantee::where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $guaranteeCutoff)
            ->whereNull('reminder_sent_at')
            ->get();

        $guaranteeReminders = 0;
        foreach ($expiringGuarantees as $guarantee) {
            $company = Company::find($guarantee->company_id);
            if (!$company) {
                continue;
            }
            Notifications::bankGuaranteeExpiring($company, $guarantee);
            $guarantee->update(['reminder_sent_at' => now()]);
            $guaranteeReminders++;
        }
        $this->info("Bank guarantee reminders sent: {$guaranteeReminders}.");

        $this->info('Daily tasks complete.');
        return self::SUCCESS;
    }
}
