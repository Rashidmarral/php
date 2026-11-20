<?php

/**
 * Daily background jobs — subscription auto-renewal, trial-ending reminders, and
 * compliance-document expiry reminders. This app has no built-in job scheduler, so this
 * script must be invoked by a real server cron job, once a day, e.g.:
 *
 *   0 3 * * * php /path/to/buildxact-saudi/cron/daily_tasks.php >> /path/to/storage/logs/cron.log 2>&1
 *
 * Safe to run more than once a day — every action here checks state before acting
 * (a subscription already renewed today won't be charged again, a reminder already sent
 * won't be re-sent), so an extra run just does nothing on the parts that are already done.
 *
 * Usage: php cron/daily_tasks.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\Moyasar;
use App\Core\Notifications;
use App\Models\Company;
use App\Models\ComplianceDocument;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;

$pdo = Database::pdo();
$today = date('Y-m-d');
$maxRetries = 3;

// ---- 1. Auto-renew subscriptions whose period has ended and a saved card is on file ----
$dueSubscriptions = $pdo->prepare(
    "SELECT * FROM subscriptions WHERE status = 'active' AND current_period_end <= ? AND moyasar_card_token IS NOT NULL AND moyasar_card_token != ''"
);
$dueSubscriptions->execute([$today]);
$renewed = 0;
$failed = 0;

foreach ($dueSubscriptions->fetchAll() as $sub) {
    $company = Company::find((int) $sub['company_id']);
    $plan = Plan::find((int) $sub['plan_id']);
    if (!$company || !$plan) {
        continue;
    }

    $amount = (float) ($sub['billing_cycle'] === 'yearly' ? $plan['price_yearly'] : $plan['price_monthly']);
    $result = Moyasar::chargeToken($sub['moyasar_card_token'], (int) round($amount * 100), $plan['name'] . ' plan renewal');
    $ok = $result && ($result['status'] ?? '') === 'paid';

    if ($ok) {
        $nextPeriodEnd = date('Y-m-d', strtotime($sub['billing_cycle'] === 'yearly' ? '+1 year' : '+30 days', strtotime($sub['current_period_end'])));
        Subscription::update($sub['id'], ['current_period_end' => $nextPeriodEnd, 'retry_count' => 0]);
        if ($company['status'] === 'past_due') {
            Company::update($company['id'], ['status' => 'active']);
        }
        Payment::create([
            'company_id' => $company['id'],
            'subscription_id' => $sub['id'],
            'plan_id' => $plan['id'],
            'billing_cycle' => $sub['billing_cycle'],
            'amount' => $amount,
            'currency' => 'SAR',
            'method' => 'moyasar',
            'reference' => (string) ($result['id'] ?? ''),
            'status' => 'paid',
        ]);
        Notifications::subscriptionRenewed($company, $amount);
        $renewed++;
    } else {
        $retryCount = (int) $sub['retry_count'] + 1;
        Subscription::update($sub['id'], ['retry_count' => $retryCount]);
        if ($retryCount >= $maxRetries) {
            Subscription::update($sub['id'], ['status' => 'past_due']);
            Company::update($company['id'], ['status' => 'past_due']);
        }
        Notifications::renewalFailed($company, $retryCount);
        $failed++;
    }
}
echo "Renewals: {$renewed} succeeded, {$failed} failed.\n";

// ---- 2. Remind trials ending in the next 3 days (once per company) ----
$reminderCutoff = date('Y-m-d', strtotime('+3 days'));
$endingTrials = $pdo->prepare(
    "SELECT * FROM companies WHERE status = 'trial' AND trial_ends_at IS NOT NULL AND trial_ends_at <= ? AND trial_ends_at >= ? AND (trial_reminder_sent_at IS NULL OR trial_reminder_sent_at = '')"
);
$endingTrials->execute([$reminderCutoff, $today]);
$trialReminders = 0;
foreach ($endingTrials->fetchAll() as $company) {
    $daysLeft = max(0, (int) ceil((strtotime($company['trial_ends_at']) - strtotime($today)) / 86400));
    Notifications::trialEndingSoon($company, $daysLeft);
    Company::update($company['id'], ['trial_reminder_sent_at' => date('Y-m-d H:i:s')]);
    $trialReminders++;
}
echo "Trial-ending reminders sent: {$trialReminders}.\n";

// ---- 3. Remind companies about compliance documents expiring within 30 days (once per document) ----
$docCutoff = date('Y-m-d', strtotime('+30 days'));
$expiringDocsStmt = $pdo->prepare(
    "SELECT * FROM compliance_documents WHERE expiry_date IS NOT NULL AND expiry_date != '' AND expiry_date <= ? AND (reminder_sent_at IS NULL OR reminder_sent_at = '')"
);
$expiringDocsStmt->execute([$docCutoff]);
$expiringDocs = $expiringDocsStmt->fetchAll();
$docReminders = 0;
foreach ($expiringDocs as $doc) {
    $company = Company::find((int) $doc['company_id']);
    if (!$company) {
        continue;
    }
    Notifications::complianceDocumentExpiring($company, $doc);
    ComplianceDocument::update($doc['id'], ['reminder_sent_at' => date('Y-m-d H:i:s')]);
    $docReminders++;
}
echo "Compliance document reminders sent: {$docReminders}.\n";

echo "Daily tasks complete.\n";
