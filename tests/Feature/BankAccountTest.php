<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Account $bankGl;

    private Account $revenueGl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->company = Company::create(['name' => 'Bank Co']);
        $this->bankGl = Account::create(['company_id' => $this->company->id, 'code' => '1100', 'name' => 'Bank', 'type' => Account::ASSET]);
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

    public function test_admin_can_create_a_bank_account_linked_to_an_asset_account(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $this->post(route('bank-accounts.store'), [
            'bank_name' => 'BCA',
            'account_name' => 'PT Demo Company',
            'account_number' => '1234567890',
            'account_id' => $this->bankGl->id,
            'status' => BankAccount::ACTIVE,
        ])->assertRedirect(route('bank-accounts.index'));

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $this->company->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
        ]);
    }

    public function test_a_bank_account_cannot_be_linked_to_a_non_asset_account(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $this->post(route('bank-accounts.store'), [
            'bank_name' => 'BCA',
            'account_name' => 'PT Demo Company',
            'account_number' => '1234567890',
            'account_id' => $this->revenueGl->id,
            'status' => BankAccount::ACTIVE,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_a_bank_account_cannot_be_linked_to_another_companys_account(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $foreignAsset = Account::create(['company_id' => $otherCompany->id, 'code' => '1100', 'name' => 'Bank', 'type' => Account::ASSET]);

        $this->memberWithRole(Role::ADMIN);

        $this->post(route('bank-accounts.store'), [
            'bank_name' => 'BCA',
            'account_name' => 'PT Demo Company',
            'account_number' => '1234567890',
            'account_id' => $foreignAsset->id,
            'status' => BankAccount::ACTIVE,
        ])->assertSessionHasErrors('account_id');
    }

    public function test_accountant_can_view_but_not_create_bank_accounts(): void
    {
        $this->memberWithRole(Role::ACCOUNTANT);

        $this->get(route('bank-accounts.index'))->assertOk();
        $this->get(route('bank-accounts.create'))->assertForbidden();
    }

    public function test_the_index_page_shows_a_masked_account_number_not_the_full_one(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $this->company->bankAccounts()->create([
            'bank_name' => 'BCA', 'account_name' => 'PT Demo Company',
            'account_number' => '1234567890', 'account_id' => $this->bankGl->id, 'status' => BankAccount::ACTIVE,
        ]);

        $response = $this->get(route('bank-accounts.index'));

        $response->assertOk();
        $response->assertDontSee('1234567890');
        $response->assertSee('••••••7890');
    }

    public function test_the_edit_page_shows_the_full_number_so_it_can_be_corrected(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $bankAccount = $this->company->bankAccounts()->create([
            'bank_name' => 'BCA', 'account_name' => 'PT Demo Company',
            'account_number' => '1234567890', 'account_id' => $this->bankGl->id, 'status' => BankAccount::ACTIVE,
        ]);

        $this->get(route('bank-accounts.edit', $bankAccount))->assertSee('1234567890', false);
    }

    public function test_account_number_is_hidden_from_array_serialization(): void
    {
        $bankAccount = $this->company->bankAccounts()->create([
            'bank_name' => 'BCA', 'account_name' => 'PT Demo Company',
            'account_number' => '1234567890', 'account_id' => $this->bankGl->id, 'status' => BankAccount::ACTIVE,
        ]);

        $this->assertArrayNotHasKey('account_number', $bankAccount->toArray());
    }

    public function test_a_bank_account_can_be_deactivated(): void
    {
        $this->memberWithRole(Role::ADMIN);

        $bankAccount = $this->company->bankAccounts()->create([
            'bank_name' => 'BCA', 'account_name' => 'PT Demo Company',
            'account_number' => '1234567890', 'account_id' => $this->bankGl->id, 'status' => BankAccount::ACTIVE,
        ]);

        $this->put(route('bank-accounts.update', $bankAccount), [
            'bank_name' => 'BCA', 'account_name' => 'PT Demo Company',
            'account_number' => '1234567890', 'account_id' => $this->bankGl->id, 'status' => BankAccount::INACTIVE,
        ])->assertRedirect(route('bank-accounts.index'));

        $this->assertSame(BankAccount::INACTIVE, $bankAccount->fresh()->status);
    }

    public function test_a_company_cannot_edit_another_companys_bank_account(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co']);
        $otherAsset = Account::create(['company_id' => $otherCompany->id, 'code' => '1100', 'name' => 'Bank', 'type' => Account::ASSET]);
        $foreignBankAccount = $otherCompany->bankAccounts()->create([
            'bank_name' => 'Mandiri', 'account_name' => 'Other Co', 'account_number' => '999',
            'account_id' => $otherAsset->id, 'status' => BankAccount::ACTIVE,
        ]);

        $this->memberWithRole(Role::ADMIN);

        $this->get(route('bank-accounts.edit', $foreignBankAccount))->assertNotFound();
    }
}
