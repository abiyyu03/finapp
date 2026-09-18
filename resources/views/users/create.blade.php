@extends('layouts.admin')

@section('title', 'Add User')

@section('breadcrumb')
    <span class="text-slate-400">Administration</span> /
    <a href="{{ route('users.index') }}" class="text-slate-400 hover:text-slate-600">Users</a> /
    <span class="text-slate-700">Add</span>
@endsection

@section('content')
    <div class="mx-auto max-w-lg py-6">
        <h1 class="text-lg font-semibold text-slate-900">Add User</h1>
        <p class="mt-1 text-sm text-slate-500">
            Creates a new account and adds it to {{ auth()->user()->activeCompany()->name }} with the role you choose.
        </p>

        <form method="POST" action="{{ route('users.store') }}" class="mt-4 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <x-input name="name" label="Name" />
            <x-input name="email" label="Email" type="email" />
            <x-input name="password" label="Password" type="password" hint="At least 8 characters." />
            <x-select
                name="role_id"
                label="Role"
                :options="$roles->pluck('name', 'id')->all()"
                placeholder="Select a role"
            />

            <div class="flex justify-end gap-3 pt-2">
                <x-button as="a" href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Add User</x-button>
            </div>
        </form>
    </div>
@endsection
