<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('readirect:create-admin {email} {--name=System Admin} {--password=}', function (string $email): int {
    $password = (string) ($this->option('password') ?: $this->secret('Admin password'));

    if ($password === '') {
        $this->error('Password is required.');

        return self::FAILURE;
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

    foreach (['student', 'teacher', 'school_admin', 'system_admin'] as $role) {
        Role::findOrCreate($role);
    }

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    Role::findByName('system_admin')->syncPermissions($permissions);

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => (string) $this->option('name'),
            'password' => Hash::make($password),
            'is_active' => true,
        ]
    );

    $user->assignRole('system_admin');

    $this->info("System admin ready: {$user->email}");

    return self::SUCCESS;
})->purpose('Create or update a ReaDirect system admin user');
