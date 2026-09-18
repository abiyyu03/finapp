<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every module route now sits behind `auth` + `active-company` (STEP 2),
     * so exercising the admin shell means acting as a real member of
     * exactly one company.
     */
    private function actingAsCompanyMember(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $company = Company::create(['name' => 'Test Co']);
        $user = User::factory()->create();
        $user->memberships()->create([
            'company_id' => $company->id,
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);

        return tap($user, fn ($u) => $this->actingAs($u));
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_dashboard_renders_the_admin_layout_and_sidebar(): void
    {
        $this->actingAsCompanyMember();

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('FinApp');
        $response->assertSeeInOrder([
            'Dashboard',
            'Accounting',
            'Chart of Accounts',
            'Journal Entries',
            'Cash &amp; Bank',
            'Cash Accounts',
            'Bank Accounts',
            'Cash Transactions',
            'Reports',
            'General Ledger',
            'Profit &amp; Loss',
            'Balance Sheet',
            'Administration',
            'Users',
            'Roles &amp; Permissions',
            'Audit Logs',
        ], false);
    }

    public function test_every_sidebar_destination_resolves_to_a_real_route(): void
    {
        $this->actingAsCompanyMember();

        foreach ([
            'accounts.index',
            'journals.index',
            'cash-accounts.index',
            'bank-accounts.index',
            'cash-transactions.index',
            'reports.general-ledger',
            'reports.profit-loss',
            'reports.balance-sheet',
            'users.index',
            'roles.index',
            'audit-logs.index',
        ] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_active_nav_link_is_highlighted_for_the_current_route(): void
    {
        $this->actingAsCompanyMember();

        $response = $this->get(route('accounts.index'));

        $response->assertOk();
        // The active nav-link variant carries the emerald highlight classes.
        $response->assertSee('bg-emerald-50 font-medium text-emerald-700', false);
    }

    public function test_flash_success_message_renders_through_the_layout(): void
    {
        $this->actingAsCompanyMember();

        $response = $this->withSession(['success' => 'Saved successfully.'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Saved successfully.');
    }

    public function test_flash_error_message_renders_through_the_layout(): void
    {
        $this->actingAsCompanyMember();

        $response = $this->withSession(['error' => 'Something went wrong.'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Something went wrong.');
    }

    public function test_validation_errors_render_through_the_layout(): void
    {
        $this->actingAsCompanyMember();

        $bag = new ViewErrorBag;
        $bag = $bag->put('default', new MessageBag(['amount' => ['The amount field is required.']]));

        $response = $this->withSession(['errors' => $bag])->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Please fix the following:');
        $response->assertSee('The amount field is required.');
    }

    public function test_input_component_shows_field_level_validation_error(): void
    {
        $bag = new ViewErrorBag;
        $bag = $bag->put('default', new MessageBag(['amount' => ['The amount field is required.']]));

        $view = $this->blade(
            '<x-input name="amount" label="Amount" />',
            ['errors' => $bag],
        );

        $view->assertSee('The amount field is required.');
        $view->assertSee('border-red-300', false);
    }

    public function test_badge_component_renders_the_requested_variant(): void
    {
        $view = $this->blade('<x-badge variant="success">POSTED</x-badge>');

        $view->assertSee('POSTED');
        $view->assertSee('bg-emerald-50', false);
    }

    public function test_confirmation_modal_component_renders_with_alpine_bindings(): void
    {
        $view = $this->blade(
            '<x-confirm-modal name="demo" title="Delete this?" description="This cannot be undone." />',
        );

        $view->assertSee('Delete this?');
        $view->assertSee('open-modal.window', false);
    }
}
