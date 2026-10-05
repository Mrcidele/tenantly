<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Boleto = 'boleto';
    case Card = 'card';
}
