<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyContextTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Company $company, string $role = Role::ADMIN): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', $role)->value('id'),
        ]);

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_a_member_of_exactly_one_company_is_auto_selected_into_it(): void
    {
        $company = Company::create(['name' => 'Solo Co']);
        $user = $this->memberOf($company);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame($company->id, session('active_company_id'));
    }

    public function test_a_member_of_several_companies_without_an_active_one_is_sent_to_the_picker(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->memberOf($companyA);
        $user->memberships()->create([
            'company_id' => $companyB->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('company.select'));
    }

    public function test_a_user_cannot_switch_into_a_company_they_do_not_belong_to(): void
    {
        $ownCompany = Company::create(['name' => 'Own Co']);
        $otherCompany = Company::create(['name' => 'Someone Else Co']);

        $user = $this->memberOf($ownCompany);

        $this->actingAs($user)
            ->post(route('company.switch', $otherCompany))
            ->assertForbidden();

        $this->assertNotSame($otherCompany->id, session('active_company_id'));
    }

    public function test_switching_to_a_company_the_user_belongs_to_succeeds(): void
    {
        $companyA = Company::create(['name' => 'Company A']);
        $companyB = Company::create(['name' => 'Company B']);

        $user = $this->memberOf($companyA);
        $user->memberships()->create([
            'company_id' => $companyB->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);

        $this->actingAs($user)
            ->post(route('company.switch', $companyB))
            ->assertRedirect(route('dashboard'));

        $this->assertSame($companyB->id, session('active_company_id'));
    }

    public function test_a_user_with_no_company_membership_is_blocked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_user_can_log_in_and_land_on_the_dashboard(): void
    {
        $company = Company::create(['name' => 'Login Co']);
        $user = $this->memberOf($company);
        $user->forceFill(['password' => bcrypt('correct-password')])->save();

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $company = Company::create(['name' => 'Login Co']);
        $user = $this->memberOf($company);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
