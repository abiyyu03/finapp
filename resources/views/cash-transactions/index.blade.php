@extends('layouts.admin')

@section('title', 'Cash Transactions')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> / <span class="text-slate-700">Cash Transactions</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Cash Transactions</h1>
            <p class="mt-1 text-sm text-slate-500">Each one generates its own journal — this is a convenience layer, not a separate ledger (biz §17).</p>
        </div>

        @can('journal.create')
            <div class="flex gap-2">
                <x-button as="a" href="{{ route('cash-transactions.create-out') }}" variant="secondary">Cash Out</x-button>
                <x-button as="a" href="{{ route('cash-transactions.create-in') }}" variant="primary">Cash In</x-button>
            </div>
        @endcan
    </div>

    <form method="GET" action="{{ route('cash-transactions.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="w-40">
            <x-select
                name="type"
                label="Type"
                :options="['CASH_IN' => 'Cash In', 'CASH_OUT' => 'Cash Out']"
                :selected="request('type')"
                placeholder="All types"
            />
        </div>
        <div class="w-40">
            <x-select
                name="status"
                label="Status"
                :options="['DRAFT' => 'Draft', 'POSTED' => 'Posted']"
                :selected="request('status')"
                placeholder="All statuses"
            />
        </div>
        <x-button type="submit" variant="secondary">Filter</x-button>
        @if (request('type') || request('status'))
            <x-button as="a" href="{{ route('cash-transactions.index') }}" variant="ghost">Clear</x-button>
        @endif
    </form>

    <x-table :headers="['Number', 'Type', 'Date', 'Cash Account', 'Counter Account', 'Amount', 'Status']">
        @forelse ($transactions as $transaction)
            <tr>
                <td class="px-4 py-2.5">
                    <a href="{{ route('cash-transactions.show', $transaction) }}" class="font-mono text-xs text-emerald-700 hover:underline">
                        {{ $transaction->transaction_number }}
                    </a>
                </td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$transaction->isCashIn() ? 'success' : 'warning'">{{ $transaction->transaction_type }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-slate-600">{{ $transaction->transaction_date->format('d M Y') }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $transaction->cashAccount->name }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $transaction->counterAccount->code }} — {{ $transaction->counterAccount->name }}</td>
                <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$transaction->amount" /></td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$transaction->isPosted() ? 'success' : 'neutral'">{{ $transaction->status }}</x-badge>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No cash transactions yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$transactions" class="mt-4" />
@endsection
