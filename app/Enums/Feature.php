<?php

declare(strict_types=1);

namespace App\Enums;

/** Funcionalidades liberadas por plano. */
enum Feature: string
{
    case ApiAccess = 'api_access';
    case CustomDomains = 'custom_domains';
    case AuditLog = 'audit_log';
    case DataExport = 'data_export';
    case PrioritySupport = 'priority_support';
}
