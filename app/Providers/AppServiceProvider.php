<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Company Membership → Role → Permission → Action (spec §8). Every
        // key here checks the user's role in their *active* company, never
        // anything sent by the client.
        foreach ([
            Permission::ACCOUNT_VIEW,
            Permission::ACCOUNT_CREATE,
            Permission::JOURNAL_VIEW,
            Permission::JOURNAL_CREATE,
            Permission::JOURNAL_POST,
            Permission::REPORT_VIEW,
            Permission::AUDIT_LOG_VIEW,
            Permission::USER_MANAGE,
            Permission::ROLE_MANAGE,
        ] as $key) {
            Gate::define($key, fn (User $user) => $user->hasPermission($key));
        }
    }
}
