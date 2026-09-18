<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    /**
     * MVP has a fixed role set (ADMIN/ACCOUNTANT/VIEWER — spec §3 STEP 3),
     * so this screen is read-only: what each role can do, not a role/
     * permission editor.
     */
    public function index()
    {
        Gate::authorize('role.manage');

        return view('roles.index', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
        ]);
    }
}
