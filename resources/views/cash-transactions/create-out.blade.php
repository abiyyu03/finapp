@extends('layouts.admin')

@section('title', 'Cash Out')

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> /
    <a href="{{ route('cash-transactions.index') }}" class="text-slate-400 hover:text-slate-600">Cash Transactions</a> /
    <span class="text-slate-700">Cash Out</span>
@endsection

@section('content')
    <div class="mx-auto max-w-xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">Cash Out</h1>
        <p class="mt-1 text-sm text-slate-500">Money paid out. Generates: Debit the counter account, Credit the cash account.</p>

        <form method="POST" action="{{ route('cash-transactions.store-out') }}" class="mt-4 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <x-select
                    name="cash_account_id"
                    label="Cash account"
                    :options="$cashAccountOptions"
                    placeholder="Select a cash account"
                />
                <x-select
                    name="counter_account_id"
                    label="Counter account"
                    :options="$accountOptions"
                    placeholder="e.g. Electricity Expense"
                />
                <x-input name="transaction_date" label="Date" type="date" />
                <x-input name="amount" label="Amount" type="number" />
            </div>

            <x-input name="description" label="Description" />

            <div class="flex justify-end gap-3 pt-2">
                <x-button as="a" href="{{ route('cash-transactions.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Draft</x-button>
            </div>
        </form>
    </div>
@endsection
