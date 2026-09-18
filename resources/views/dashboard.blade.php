@extends('layouts.admin')

@section('title', 'Dashboard')

@section('breadcrumb')
    <span class="text-slate-700">Dashboard</span>
@endsection

@section('content')
    <div class="space-y-6 py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ auth()->user()->activeCompany()->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Every figure below is read live from posted journals — nothing here is cached or computed separately (spec §21).</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Cash Balance</p>
                <p class="mt-2 font-mono text-lg text-slate-900"><x-currency :amount="$cashBalance" /></p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Bank Balance</p>
                <p class="mt-2 font-mono text-lg text-slate-900"><x-currency :amount="$bankBalance" /></p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Revenue</p>
                <p class="mt-2 font-mono text-lg text-emerald-700"><x-currency :amount="$totalRevenue" /></p>
                <p class="mt-0.5 text-xs text-slate-400">All time</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Expense</p>
                <p class="mt-2 font-mono text-lg text-red-700"><x-currency :amount="$totalExpense" /></p>
                <p class="mt-0.5 text-xs text-slate-400">All time</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }}</p>
                <p class="mt-2 font-mono text-lg {{ $netProfit >= 0 ? 'text-emerald-700' : 'text-red-700' }}"><x-currency :amount="$netProfit" /></p>
                <p class="mt-0.5 text-xs text-slate-400">All time</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recent Journal Entries</span>
                    <a href="{{ route('journals.index') }}" class="text-xs text-emerald-700 hover:underline">View all</a>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentJournals as $journal)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('journals.show', $journal) }}" class="font-mono text-xs text-emerald-700 hover:underline">{{ $journal->journal_number }}</a>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500">{{ $journal->transaction_date->format('d M Y') }}</td>
                                <td class="px-4 py-2.5">
                                    <x-badge :variant="$journal->isPosted() ? 'success' : 'neutral'">{{ $journal->status }}</x-badge>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-slate-400">No journal entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Recent Cash Transactions</span>
                    <a href="{{ route('cash-transactions.index') }}" class="text-xs text-emerald-700 hover:underline">View all</a>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentCashTransactions as $transaction)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('cash-transactions.show', $transaction) }}" class="font-mono text-xs text-emerald-700 hover:underline">{{ $transaction->transaction_number }}</a>
                                </td>
                                <td class="px-4 py-2.5">
                                    <x-badge :variant="$transaction->isCashIn() ? 'success' : 'warning'">{{ $transaction->transaction_type }}</x-badge>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$transaction->amount" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-slate-400">No cash transactions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
