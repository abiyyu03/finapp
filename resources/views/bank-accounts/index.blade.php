@extends('layouts.admin')

@section('title', 'Bank Accounts')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> / <span class="text-slate-700">Bank Accounts</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Bank Accounts</h1>
            <p class="mt-1 text-sm text-slate-500">Each linked to an ASSET account. Account numbers are masked here.</p>
        </div>

        @can('account.create')
            <x-button as="a" href="{{ route('bank-accounts.create') }}" variant="primary">New Bank Account</x-button>
        @endcan
    </div>

    <x-table :headers="['Bank', 'Account Name', 'Account Number', 'Linked Account', 'Status', '']">
        @forelse ($bankAccounts as $bankAccount)
            <tr>
                <td class="px-4 py-2.5 text-slate-800">{{ $bankAccount->bank_name }}</td>
                <td class="px-4 py-2.5 text-slate-600">{{ $bankAccount->account_name }}</td>
                <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{{ $bankAccount->maskedAccountNumber() }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $bankAccount->account->code }} — {{ $bankAccount->account->name }}</td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$bankAccount->isActive() ? 'success' : 'neutral'">{{ $bankAccount->status }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-right">
                    @can('account.create')
                        <a href="{{ route('bank-accounts.edit', $bankAccount) }}" class="text-emerald-700 hover:underline">Edit</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No bank accounts yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$bankAccounts" class="mt-4" />
@endsection
