<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the MVP's fixed role set (spec §3 / biz §24).
     */
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::ACCOUNTANT, Role::VIEWER] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}
