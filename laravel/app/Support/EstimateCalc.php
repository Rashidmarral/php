<?php

namespace App\Support;

/**
 * Shared markup/tax math for estimates, used by every creation path (blank form,
 * template gallery, AI generator) so the numbers are computed identically everywhere.
 *
 * subtotal is the raw cost total (sum of item qty * unit_cost). Markup is applied on
 * top of cost to get the sell price shown to the client; tax is applied on top of the
 * sell price. Client-facing documents (PDF, share links) only ever see the sell
 * subtotal — the cost/margin split stays internal to the company panel.
 */
class EstimateCalc
{
    /** @return array{markup_amount: float, sell_subtotal: float, tax_amount: float, total: float} */
    public static function compute(float $subtotal, float $markupPercent, float $taxPercent): array
    {
        $markupAmount = $subtotal * $markupPercent / 100;
        $sellSubtotal = $subtotal + $markupAmount;
        $taxAmount = $sellSubtotal * $taxPercent / 100;
        $total = $sellSubtotal + $taxAmount;

        return [
            'markup_amount' => round($markupAmount, 2),
            'sell_subtotal' => round($sellSubtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }
}
