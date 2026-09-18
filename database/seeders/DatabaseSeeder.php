<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Demo data only — no real company data is invented here. This gives
     * STEP 2 something to log into: one company, one ADMIN user.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);

        $company = Company::firstOrCreate(['name' => 'Demo Company']);

        $this->callWith(AccountSeeder::class, ['company' => $company]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@finapp.test'],
            ['name' => 'Demo Admin', 'password' => 'password'],
        );

        $admin->memberships()->firstOrCreate([
            'company_id' => $company->id,
        ], [
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);
    }
}
