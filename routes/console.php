<?php

declare(strict_types=1);

use App\Jobs\PersistUsageCounters;
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:enforce-deadlines')->hourly()->withoutOverlapping()->onOneServer();
Schedule::job(new PersistUsageCounters)->everyFiveMinutes()->onOneServer();
