<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
