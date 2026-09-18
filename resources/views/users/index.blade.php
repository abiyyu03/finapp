@extends('layouts.admin')

@section('title', 'Users')

@section('breadcrumb')
    <span class="text-slate-400">Administration</span> / <span class="text-slate-700">Users</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Users</h1>
            <p class="mt-1 text-sm text-slate-500">Members of {{ auth()->user()->activeCompany()->name }}.</p>
        </div>

        <x-button as="a" href="{{ route('users.create') }}" variant="primary">Add User</x-button>
    </div>

    <x-table :headers="['Name', 'Email', 'Role', 'Member since']">
        @forelse ($memberships as $membership)
            <tr>
                <td class="px-4 py-2.5 text-slate-800">{{ $membership->user->name }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ $membership->user->email }}</td>
                <td class="px-4 py-2.5">
                    <x-badge variant="info">{{ $membership->role->name }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-slate-500">{{ $membership->created_at->format('d M Y') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No users yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$memberships" class="mt-4" />
@endsection
