<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAccountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $cashGl;

    private Account $revenueGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Cash Co']);
        $this->cashGl = Account::create(['company_id' => $this->company->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $this->revenueGl = Account::create(['company_id' => $this->company->id, 'code' => '4000', 'name' => 'Revenue', 'type' => Account::REVENUE]);
    }

    private function memberWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $this->company->id,
            'role_id' => Role::where('name', $role)->value('id'),
        ]);
        $this->actingAs($user)->get(route('dashboard'));

        return $user;
    }

    public function test_admin_can_create_a_cash_account_linked_to_an_asset_account(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $this->post(route('cash-accounts.store'), [
            'name' => 'Main Cash',
            'account_id' => $this->cashGl->id,
            'status' => CashAccount::ACTIVE,
        ])->assertRedirect(route('cash-accounts.index'));

        $this->assertDatabaseHas('cash_accounts', [
            'company_id' => $this->company->id,
            'name' => 'Main Cash',
            'account_id' => $this->cashGl->id,
        ]);
    }

    public function test_a_cash_account_cannot_be_linked_to_a_non_asset_account(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $this->post(route('cash-accounts.store'), [
            'name' => 'Bad Link',
            'account_id' => $this->revenueGl->id,
            'status' => CashAccount::ACTIVE,
        ])->assertSessionHasErrors('account_id');

        $this->assertDatabaseMissing('cash_accounts', ['name' => 'Bad Link']);
    }

    public function test_a_cash_account_cannot_be_linked_to_another_companys_account(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignAsset = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);

        $this->memberWithRole(Role::ADMIN);

        $this->post(route('cash-accounts.store'), [
            'name' => 'Cross Co Cash',
            'account_id' => $foreignAsset->id,
            'status' => CashAccount::ACTIVE,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_accountant_can_view_but_not_create_cash_accounts(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->get(route('cash-accounts.index'))->assertOk();
        $this->get(route('cash-accounts.create'))->assertForbidden();
        $this->post(route('cash-accounts.store'), [
            'name' => 'Petty Cash',
            'account_id' => $this->cashGl->id,
            'status' => CashAccount::ACTIVE,
        ])->assertForbidden();
    }

    public function test_a_cash_account_can_be_deactivated(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $cashAccount = $this->company->cashAccounts()->create([
            'name' => 'Petty Cash', 'account_id' => $this->cashGl->id, 'status' => CashAccount::ACTIVE,
        ]);

        $this->put(route('cash-accounts.update', $cashAccount), [
            'name' => 'Petty Cash',
            'account_id' => $this->cashGl->id,
            'status' => CashAccount::INACTIVE,
        ])->assertRedirect(route('cash-accounts.index'));

        $this->assertSame(CashAccount::INACTIVE, $cashAccount->fresh()->status);
    }

    public function test_a_company_cannot_edit_another_companys_cash_account(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherAsset = Account::create(['company_id' => $otherCompany->id, 'code' => '1000', 'name' => 'Cash', 'type' => Account::ASSET]);
        $foreignCashAccount = $otherCompany->cashAccounts()->create([
            'name' => 'Their Cash', 'account_id' => $otherAsset->id, 'status' => CashAccount::ACTIVE,
        ]);

        $this->memberWithRole(Role::ADMIN);

        $this->get(route('cash-accounts.edit', $foreignCashAccount))->assertNotFound();
    }
}
