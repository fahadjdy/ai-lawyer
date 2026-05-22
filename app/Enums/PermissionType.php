<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

/**
 * Granular permission catalogue. Policies map abilities to these values and the
 * RolePermissionSeeder grants subsets of them to each {@see RoleType}.
 */
enum PermissionType: string
{
    use HasEnumHelpers;

    // Cases
    case ViewCases = 'cases.view';
    case CreateCases = 'cases.create';
    case UpdateCases = 'cases.update';
    case DeleteCases = 'cases.delete';
    case AssignCases = 'cases.assign';

    // Clients
    case ViewClients = 'clients.view';
    case CreateClients = 'clients.create';
    case UpdateClients = 'clients.update';
    case DeleteClients = 'clients.delete';

    // Hearings
    case ViewHearings = 'hearings.view';
    case ManageHearings = 'hearings.manage';

    // Documents & Evidence
    case ViewDocuments = 'documents.view';
    case ManageDocuments = 'documents.manage';
    case ViewEvidence = 'evidence.view';
    case ManageEvidence = 'evidence.manage';

    // Tasks
    case ViewTasks = 'tasks.view';
    case ManageTasks = 'tasks.manage';

    // Templates / Library
    case ViewTemplates = 'templates.view';
    case ManageTemplates = 'templates.manage';

    // Firm administration
    case ManageTeam = 'team.manage';
    case ViewAuditLogs = 'audit.view';
    case ManageSettings = 'settings.manage';

    public function label(): string
    {
        return ucwords(str_replace(['.', '_'], ' ', $this->value));
    }

    public function color(): string
    {
        return 'slate';
    }
}
