<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['student', 'teacher', 'school_admin', 'system_admin'] as $role) {
            Role::findOrCreate($role);
        }

        $permissions = [
            'admin.dashboard.view',
            'admin.schools.manage',
            'admin.teachers.manage',
            'admin.learners.manage',
            'admin.content.manage',
            'admin.rules.manage',
            'admin.agents.manage',
            'admin.prompts.manage',
            'admin.audit.view',
            'admin.system.view',
            'admin.testing.access',
            'admin.testing.debug',
            'admin.testing.jump',
            'admin.testing.sandbox',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findByName('system_admin')->syncPermissions($permissions);
        Role::findByName('school_admin')->syncPermissions([
            'admin.dashboard.view',
            'admin.learners.manage',
            'admin.content.manage',
            'admin.audit.view',
            'admin.system.view',
        ]);
    }
}
