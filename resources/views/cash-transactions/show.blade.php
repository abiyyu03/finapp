@extends('layouts.admin')

@section('title', $transaction->transaction_number)

@section('breadcrumb')
    <span class="text-slate-400">Cash &amp; Bank</span> /
    <a href="{{ route('cash-transactions.index') }}" class="text-slate-400 hover:text-slate-600">Cash Transactions</a> /
    <span class="text-slate-700">{{ $transaction->transaction_number }}</span>
@endsection

@section('content')
    <div class="py-6">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-slate-900">{{ $transaction->transaction_number }}</h1>
                    <x-badge :variant="$transaction->isCashIn() ? 'success' : 'warning'">{{ $transaction->transaction_type }}</x-badge>
                    <x-badge :variant="$transaction->isPosted() ? 'success' : 'neutral'">{{ $transaction->status }}</x-badge>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $transaction->transaction_date->translatedFormat('d F Y') }}
                    @if ($transaction->description)
                        — {{ $transaction->description }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-400">Created by {{ $transaction->creator->name }}</p>
            </div>

            @if ($transaction->isDraft())
                <div class="flex gap-2">
                    @can('journal.create')
                        <x-button variant="danger" x-data @click="$dispatch('open-modal', 'delete-transaction')">Delete</x-button>
                    @endcan
                    @can('journal.post')
                        <x-button variant="primary" x-data @click="$dispatch('open-modal', 'post-transaction')">Post</x-button>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Cash Account</p>
            <p class="mt-1 text-sm text-slate-800">{{ $transaction->cashAccount->name }}</p>
            <p class="text-xs text-slate-400">{{ $transaction->cashAccount->account->code }} — {{ $transaction->cashAccount->account->name }}</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Counter Account</p>
            <p class="mt-1 text-sm text-slate-800">{{ $transaction->counterAccount->code }} — {{ $transaction->counterAccount->name }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Amount</p>
        <p class="mt-1 font-mono text-lg text-slate-900"><x-currency :amount="$transaction->amount" /></p>
    </div>

    <p class="mt-4 text-xs text-slate-400">
        Underlying journal:
        <a href="{{ route('journals.show', $transaction->journalEntry) }}" class="font-mono text-emerald-700 hover:underline">
            {{ $transaction->journalEntry->journal_number }}
        </a>
    </p>

    @if ($transaction->isDraft())
        <x-confirm-modal
            name="delete-transaction"
            title="Delete this draft?"
            :description="\"{$transaction->transaction_number} and its draft journal will be permanently removed.\""
            confirm-label="Delete"
            :action="route('cash-transactions.destroy', $transaction)"
            method="DELETE"
        />

        <x-confirm-modal
            name="post-transaction"
            title="Post this cash transaction?"
            :description="\"Once posted, {$transaction->transaction_number} and its journal become immutable.\""
            confirm-label="Post"
            confirming-label="Posting…"
            variant="primary"
            :action="route('cash-transactions.post', $transaction)"
            method="POST"
        />
    @endif
@endsection
