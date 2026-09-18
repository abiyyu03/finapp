@extends('layouts.admin')

@section('title', 'Chart of Accounts')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> / <span class="text-slate-700">Chart of Accounts</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Chart of Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">Accounts used to record this company's transactions.</p>
        </div>

        @can('account.create')
            <x-button as="a" href="{{ route('accounts.create') }}" variant="primary">New Account</x-button>
        @endcan
    </div>

    <x-table :headers="['Code', 'Name', 'Type', 'Parent', 'Status', '']">
        @forelse ($accounts as $account)
            <tr>
                <td class="px-4 py-2.5 font-mono text-xs text-slate-600">{{ $account->code }}</td>
                <td class="px-4 py-2.5 text-slate-800">{{ $account->name }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ $account->type }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $account->parent?->name ?? '—' }}</td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$account->isActive() ? 'success' : 'neutral'">{{ $account->status }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-right">
                    @can('account.create')
                        <a href="{{ route('accounts.edit', $account) }}" class="text-emerald-700 hover:underline">Edit</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No accounts yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$accounts" class="mt-4" />
@endsection
