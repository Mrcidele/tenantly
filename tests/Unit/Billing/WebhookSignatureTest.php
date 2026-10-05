<?php

declare(strict_types=1);

use App\Billing\Gateways\StripeGateway;

it('valida a assinatura do Stripe com tolerância de tempo', function (): void {
    $payload = '{"id":"evt_1"}';
    $t = time();
    $sig = hash_hmac('sha256', $t.'.'.$payload, 'whsec');

    expect(StripeGateway::validSignature($payload, "t={$t},v1={$sig}", 'whsec', 300))->toBeTrue()
        ->and(StripeGateway::validSignature($payload, "t={$t},v1={$sig}", 'outro', 300))->toBeFalse()
        ->and(StripeGateway::validSignature($payload.' ', "t={$t},v1={$sig}", 'whsec', 300))->toBeFalse();

    $old = $t - 600;
    $oldSig = hash_hmac('sha256', $old.'.'.$payload, 'whsec');
    expect(StripeGateway::validSignature($payload, "t={$old},v1={$oldSig}", 'whsec', 300))->toBeFalse();
});
