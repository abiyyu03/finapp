@extends('layouts.admin')

@section('title', $journal->journal_number)

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> /
    <a href="{{ route('journals.index') }}" class="text-slate-400 hover:text-slate-600">Journal Entries</a> /
    <span class="text-slate-700">{{ $journal->journal_number }}</span>
@endsection

@section('content')
    <div class="py-6">
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold text-slate-900">{{ $journal->journal_number }}</h1>
                    <x-badge :variant="$journal->isPosted() ? 'success' : 'neutral'">{{ $journal->status }}</x-badge>
                    @if ($journal->isOpeningBalance())
                        <x-badge variant="info">Opening Balance</x-badge>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $journal->transaction_date->translatedFormat('d F Y') }}
                    @if ($journal->description)
                        — {{ $journal->description }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-400">Created by {{ $journal->creator->name }}</p>
            </div>

            @if ($journal->isDraft())
                <div class="flex gap-2">
                    @can('journal.create')
                        <x-button as="a" href="{{ route('journals.edit', $journal) }}" variant="secondary">Edit</x-button>
                        <x-button variant="danger" x-data @click="$dispatch('open-modal', 'delete-journal')">Delete</x-button>
                    @endcan
                    @can('journal.post')
                        <x-button variant="primary" x-data @click="$dispatch('open-modal', 'post-journal')">Post</x-button>
                    @endcan
                </div>
            @else
                <div class="text-right text-xs text-slate-400">
                    Posted by {{ $journal->poster?->name }}<br>
                    {{ $journal->posted_at?->translatedFormat('d F Y, H:i') }}
                </div>
            @endif
        </div>
    </div>

    <x-table :headers="['Account', 'Description', 'Debit', 'Credit']">
        @foreach ($journal->lines as $line)
            <tr>
                <td class="px-4 py-2.5 text-slate-800">{{ $line->account->code }} — {{ $line->account->name }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $line->description ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right font-mono text-xs">
                    @if ($line->debit > 0) <x-currency :amount="$line->debit" :symbol="false" /> @endif
                </td>
                <td class="px-4 py-2.5 text-right font-mono text-xs">
                    @if ($line->credit > 0) <x-currency :amount="$line->credit" :symbol="false" /> @endif
                </td>
            </tr>
        @endforeach
        <tr class="bg-slate-50 font-medium">
            <td colspan="2" class="px-4 py-2.5 text-right text-slate-600">Total</td>
            <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$journal->totalDebit()" :symbol="false" /></td>
            <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$journal->totalCredit()" :symbol="false" /></td>
        </tr>
    </x-table>

    @if ($journal->isDraft())
        <x-confirm-modal
            name="delete-journal"
            title="Delete this draft?"
            :description="\"{$journal->journal_number} will be permanently removed. This can't be undone.\""
            confirm-label="Delete"
            :action="route('journals.destroy', $journal)"
            method="DELETE"
        />

        <x-confirm-modal
            name="post-journal"
            title="Post this journal?"
            :description="\"Once posted, {$journal->journal_number} becomes immutable — it can no longer be edited or deleted, only reversed in a future step.\""
            confirm-label="Post"
            confirming-label="Posting…"
            variant="primary"
            :action="route('journals.post', $journal)"
            method="POST"
        />
    @endif
@endsection
