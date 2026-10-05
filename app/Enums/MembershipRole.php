<?php

declare(strict_types=1);

namespace App\Enums;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => array_values(array_filter(
                Permission::cases(),
                static fn (Permission $permission): bool => ! in_array($permission, [Permission::ManageBilling, Permission::DeleteOrganization], true),
            )),
            self::Member => [
                Permission::ViewProjects, Permission::ManageProjects,
                Permission::ViewMembers, Permission::UploadFiles,
            ],
            self::Viewer => [Permission::ViewProjects, Permission::ViewMembers],
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dono',
            self::Admin => 'Administrador',
            self::Member => 'Membro',
            self::Viewer => 'Leitor',
        };
    }

    /**
     * Papéis que este papel pode atribuir a outros.
     *
     * @return list<self>
     */
    public function assignable(): array
    {
        return match ($this) {
            self::Owner => self::cases(),
            self::Admin => [self::Admin, self::Member, self::Viewer],
            default => [],
        };
    }
}
