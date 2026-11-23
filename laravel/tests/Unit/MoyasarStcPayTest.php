<?php

namespace Tests\Unit;

use App\Support\Moyasar;
use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

/**
 * Asserts the exact JSON shape Moyasar::buildStcPayPayload() produces for a real,
 * distinct `source.type: "stcpay"` payment — the request body that would be POSTed to
 * https://api.moyasar.com/v1/payments. This is a pure function (no curl, no network, no
 * credentials), so it's verifiable here even though this sandbox can't reach Moyasar's
 * real API to confirm the server accepts it.
 */
class MoyasarStcPayTest extends TestCase
{
    public function test_payload_shape_matches_moyasar_stcpay_source(): void
    {
        $normalized = PhoneNumber::normalizeSaudi('0501234567');
        $this->assertSame('966501234567', $normalized);

        $payload = Moyasar::buildStcPayPayload(
            15000,
            'Pro plan (monthly)',
            $normalized,
            'https://example.test/app/billing/moyasar-callback?plan=pro&cycle=monthly'
        );

        $this->assertSame(15000, $payload['amount']);
        $this->assertSame('SAR', $payload['currency']);
        $this->assertSame('Pro plan (monthly)', $payload['description']);
        $this->assertSame('https://example.test/app/billing/moyasar-callback?plan=pro&cycle=monthly', $payload['callback_url']);

        $this->assertSame([
            'type' => 'stcpay',
            'mobile' => '+966501234567',
            'cashier' => 'Pro plan (monthly)',
        ], $payload['source']);
    }

    public function test_payload_mobile_is_e164_with_plus_prefix_for_a_966_prefixed_input(): void
    {
        $normalized = PhoneNumber::normalizeSaudi('+966 56 123 4567');
        $payload = Moyasar::buildStcPayPayload(1000, 'Invoice INV-1', $normalized, 'https://example.test/callback');

        $this->assertSame('+966561234567', $payload['source']['mobile']);
        $this->assertSame('stcpay', $payload['source']['type']);
    }

    public function test_cashier_is_truncated_to_forty_characters(): void
    {
        $longDescription = str_repeat('x', 80);
        $payload = Moyasar::buildStcPayPayload(1000, $longDescription, '966501234567', 'https://example.test/callback');

        $this->assertSame(40, strlen($payload['source']['cashier']));
    }

    public function test_create_stc_pay_payment_for_company_refuses_an_unnormalizable_mobile_without_calling_the_api(): void
    {
        // An empty/garbage mobile fails PhoneNumber::normalizeSaudi() and the create-payment
        // call must return null before ever attempting a curl request — asserted here via the
        // per-company entry point so this plain PHPUnit test needs no Laravel/DB bootstrap
        // (the platform-level createStcPayPayment() reads its secret from the Setting model,
        // which this test harness doesn't connect to a database).
        $company = ['moyasar_secret_key' => 'sk_test_fake'];
        $result = Moyasar::createStcPayPaymentForCompany($company, 1000, 'Invoice INV-1', 'not-a-phone-number', 'https://example.test/callback');
        $this->assertNull($result);
    }
}
