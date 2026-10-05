<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Billing\GatewayManager;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBillingWebhook;
use App\Models\BillingWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verifica a assinatura, grava o evento (único por gateway + id) e responde
 * rápido; o processamento é assíncrono. Reenvios do gateway são aceitos e ignorados.
 */
final class BillingWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayManager $gateways): JsonResponse
    {
        $event = $gateways->get($gateway)->parseWebhook($request);

        try {
            $record = BillingWebhookEvent::query()->create([
                'gateway' => $gateway,
                'event_id' => $event->id,
                'type' => $event->type->value,
                'payload' => [
                    'raw' => $event->payload,
                    'normalized' => [
                        'customer_id' => $event->customerId,
                        'subscription_id' => $event->subscriptionId,
                        'invoice_id' => $event->invoiceId,
                        'amount_cents' => $event->amountCents,
                        'due_at' => $event->dueAt?->toIso8601String(),
                        'method' => $event->method?->value,
                        'payment_url' => $event->paymentUrl,
                    ],
                ],
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessBillingWebhook::dispatch($record->id);

        return response()->json(['status' => 'accepted'], 202);
    }
}
