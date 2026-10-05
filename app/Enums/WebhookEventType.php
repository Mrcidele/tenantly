<?php

declare(strict_types=1);

namespace App\Enums;

/** Eventos normalizados, independentes do gateway. */
enum WebhookEventType: string
{
    case InvoiceCreated = 'invoice.created';
    case InvoicePaid = 'invoice.paid';
    case InvoiceOverdue = 'invoice.overdue';
    case SubscriptionCanceled = 'subscription.canceled';
    case Ignored = 'ignored';
}
