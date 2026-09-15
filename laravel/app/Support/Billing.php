<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;

/** Shared subscription-activation logic used by the user-panel BillingController and the admin company/payment overrides. */
class Billing
{
    public static function activatePlan(int $companyId, Plan $plan, string $cycle, ?string $cardToken = null): void
    {
        Company::whereKey($companyId)->update(['plan_id' => $plan->id, 'status' => 'active']);

        Subscription::create([
            'company_id' => $companyId,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'status' => 'active',
            'current_period_end' => now()->add($cycle === 'yearly' ? '1 year' : '30 days'),
            'moyasar_card_token' => $cardToken,
        ]);
    }
}
