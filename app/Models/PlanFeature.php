<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plan_id
 * @property string $feature
 * @property string $value
 */
#[Fillable(['feature', 'value'])]
class PlanFeature extends Model
{
    use HasUuids, UsesCentralConnection;
}
