<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('user.manage');

        $memberships = $request->user()->activeCompany()
            ->memberships()
            ->with(['user', 'role'])
            ->latest()
            ->paginate(20);

        return view('users.index', ['memberships' => $memberships]);
    }

    public function create(Request $request)
    {
        Gate::authorize('user.manage');

        return view('users.create', ['roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('user.manage');

        $company = $request->user()->activeCompany();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->memberships()->create([
            'company_id' => $company->id,
            'role_id' => $data['role_id'],
        ]);

        return redirect()->route('users.index')->with('success', "{$user->name} was added to {$company->name}.");
    }
}
