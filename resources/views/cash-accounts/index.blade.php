@extends('layouts.admin')

@section('title', 'Cash Accounts')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> / <span class="text-slate-700">Cash Accounts</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Cash Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">Physical cash drawers, each linked to an ASSET account.</p>
        </div>

        @can('account.create')
            <x-button as="a" href="{{ route('cash-accounts.create') }}" variant="primary">New Cash Account</x-button>
        @endcan
    </div>

    <x-table :headers="['Name', 'Linked Account', 'Status', '']">
        @forelse ($cashAccounts as $cashAccount)
            <tr>
                <td class="px-4 py-2.5 text-slate-800">{{ $cashAccount->name }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $cashAccount->account->code }} — {{ $cashAccount->account->name }}</td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$cashAccount->isActive() ? 'success' : 'neutral'">{{ $cashAccount->status }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-right">
                    @can('account.create')
                        <a href="{{ route('cash-accounts.edit', $cashAccount) }}" class="text-emerald-700 hover:underline">Edit</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No cash accounts yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$cashAccounts" class="mt-4" />
@endsection
