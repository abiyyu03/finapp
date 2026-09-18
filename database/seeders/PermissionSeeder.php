<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the MVP's fixed permission set (spec §3 STEP 3) and assign it to
     * roles per biz §24's Admin/Accountant/Viewer capability lists.
     */
    public function run(): void
    {
        $keys = [
            Permission::ACCOUNT_VIEW,
            Permission::ACCOUNT_CREATE,
            Permission::JOURNAL_VIEW,
            Permission::JOURNAL_CREATE,
            Permission::JOURNAL_POST,
            Permission::REPORT_VIEW,
            Permission::AUDIT_LOG_VIEW,
            Permission::USER_MANAGE,
            Permission::ROLE_MANAGE,
        ];

        $permissions = collect($keys)->mapWithKeys(
            fn ($key) => [$key => Permission::firstOrCreate(['key' => $key])]
        );

        $matrix = [
            Role::ADMIN => $keys, // Admin has all MVP permissions (spec STEP 3 DoD).
            Role::ACCOUNTANT => [
                Permission::ACCOUNT_VIEW,
                Permission::JOURNAL_VIEW,
                Permission::JOURNAL_CREATE,
                Permission::JOURNAL_POST,
                Permission::REPORT_VIEW,
            ],
            Role::VIEWER => [
                Permission::REPORT_VIEW,
            ],
        ];

        foreach ($matrix as $roleName => $roleKeys) {
            $role = Role::where('name', $roleName)->firstOrFail();
            $role->permissions()->sync(
                collect($roleKeys)->map(fn ($key) => $permissions[$key]->id)
            );
        }
    }
}
