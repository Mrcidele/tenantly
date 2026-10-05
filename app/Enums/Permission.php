<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case ViewProjects = 'projects.view';
    case ManageProjects = 'projects.manage';
    case ViewMembers = 'members.view';
    case ManageMembers = 'members.manage';
    case UploadFiles = 'files.upload';
    case ManageSettings = 'settings.manage';
    case ManageDomains = 'domains.manage';
    case ManageBilling = 'billing.manage';
    case ViewAuditLog = 'audit.view';
    case ManageApiTokens = 'api-tokens.manage';
    case ExportData = 'data.export';
    case DeleteOrganization = 'organization.delete';
}
