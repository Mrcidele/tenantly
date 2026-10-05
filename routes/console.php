<?php

declare(strict_types=1);

use App\Jobs\PersistUsageCounters;
use App\Jobs\PurgeExpiredData;
use App\Jobs\VerifyCustomDomains;
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:enforce-deadlines')->hourly()->withoutOverlapping()->onOneServer();
Schedule::job(new PersistUsageCounters)->everyFiveMinutes()->onOneServer();
Schedule::job(new VerifyCustomDomains)->everyTenMinutes()->onOneServer();
Schedule::job(new VerifyCustomDomains(includeVerified: true))->dailyAt('03:30')->onOneServer();
Schedule::job(new PurgeExpiredData)->dailyAt('04:10')->onOneServer();
