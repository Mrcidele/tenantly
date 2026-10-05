<?php

declare(strict_types=1);

return [

    // fake | asaas | stripe
    'gateway' => env('BILLING_GATEWAY', 'fake'),

    // Plano usado no início do trial.
    'default_plan' => env('BILLING_DEFAULT_PLAN', 'starter'),

    // Dias de tolerância após o vencimento antes de suspender.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    'gateways' => [
        'fake' => [
            'webhook_secret' => env('FAKE_BILLING_WEBHOOK_SECRET', 'fake-secret'),
        ],
        'asaas' => [
            'base_url' => env('ASAAS_BASE_URL', 'https://api-sandbox.asaas.com/v3'),
            'api_key' => env('ASAAS_API_KEY'),
            'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        ],
        'stripe' => [
            'base_url' => 'https://api.stripe.com/v1',
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'webhook_tolerance' => 300,
            // code do plano => price id no Stripe
            'prices' => [],
        ],
    ],

];
