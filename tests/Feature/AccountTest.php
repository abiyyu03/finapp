<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(Company $company): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);

        $this->actingAs($user)->get(route('dashboard'));

        return $user;
    }

    public function test_admin_can_create_an_account(): void
    {
        $company = Company::create(['name' => 'Co A']);
        $this->admin($company);

        $response = $this->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Cash',
            'type' => Account::ASSET,
            'status' => Account::ACTIVE,
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('accounts', [
            'company_id' => $company->id,
            'code' => '1000',
            'name' => 'Cash',
        ]);
    }

    public function test_account_code_must_be_unique_per_company_but_may_repeat_across_companies(): void
    {
        $companyA = Company::create(['name' => 'Co A']);
        $companyB = Company::create(['name' => 'Co B']);

        Account::create(['company_id' => $companyA->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        Account::create(['company_id' => $companyB->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);

        $this->assertDatabaseCount('accounts', 2);

        $this->admin($companyA);

        $this->post(route('accounts.store'), [
            'code' => '1000',
            'name' => 'Duplicate Cash',
            'type' => Account::ASSET,
            'status' => Account::ACTIVE,
        ])->assertSessionHasErrors('code');
    }

    public function test_invalid_account_type_is_rejected(): void
    {
        $company = Company::create(['name' => 'Co A']);
        $this->admin($company);

        $this->post(route('accounts.store'), [
            'code' => '9999',
            'name' => 'Bad Type',
            'type' => 'NOT_A_TYPE',
            'status' => Account::ACTIVE,
        ])->assertSessionHasErrors('type');
    }

    public function test_a_company_cannot_edit_another_companys_account(): void
    {
        $companyA = Company::create(['name' => 'Co A']);
        $companyB = Company::create(['name' => 'Co B']);

        $foreignAccount = Account::create([
            'company_id' => $companyB->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET,
        ]);

        $this->admin($companyA);

        $this->get(route('accounts.edit', $foreignAccount))->assertNotFound();
        $this->put(route('accounts.update', $foreignAccount), [
            'code' => '1000', 'name' => 'Hijacked', 'type' => Account::ASSET, 'status' => Account::ACTIVE,
        ])->assertNotFound();

        $this->assertSame('Cash', $foreignAccount->fresh()->name);
    }

    public function test_accountant_cannot_create_accounts(): void
    {
        $company = Company::create(['name' => 'Co A']);
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', Role::ACCOUNTANT)->value('id'),
        ]);
        $this->actingAs($user)->get(route('dashboard'));

        $this->get(route('accounts.create'))->assertForbidden();
        $this->post(route('accounts.store'), [
            'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET, 'status' => Account::ACTIVE,
        ])->assertForbidden();
    }

    public function test_an_account_can_be_deactivated_through_the_edit_form(): void
    {
        $company = Company::create(['name' => 'Co A']);
        $this->admin($company);

        $account = Account::create([
            'company_id' => $company->id, 'code' => '5000', 'name' => 'Electricity Expense', 'type' => Account::EXPENSE,
        ]);

        $this->put(route('accounts.update', $account), [
            'code' => '5000', 'name' => 'Electricity Expense', 'type' => Account::EXPENSE, 'status' => Account::INACTIVE,
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame(Account::INACTIVE, $account->fresh()->status);
    }
}
