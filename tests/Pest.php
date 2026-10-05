<?php

declare(strict_types=1);

use Tests\Support\RefreshDatabaseAsMigrator;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabaseAsMigrator::class)
    ->in('Feature', 'Isolation');

pest()->extend(TestCase::class)->in('Unit');
