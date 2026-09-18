@extends('layouts.admin')

@section('title', 'Journal Entries')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> / <span class="text-slate-700">Journal Entries</span>
@endsection

@section('content')
    <div class="flex items-center justify-between py-6">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">Journal Entries</h1>
            <p class="mt-1 text-sm text-slate-500">Drafts don't affect any report until they're posted.</p>
        </div>

        @can('journal.create')
            <div class="flex gap-2">
                @if ($hasOpeningBalance === false)
                    <x-button as="a" href="{{ route('opening-balance.show') }}" variant="secondary">Set Opening Balance</x-button>
                @endif
                <x-button as="a" href="{{ route('journals.create') }}" variant="primary">New Journal</x-button>
            </div>
        @endcan
    </div>

    <form method="GET" action="{{ route('journals.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="w-48">
            <x-select
                name="status"
                label="Status"
                :options="['DRAFT' => 'Draft', 'POSTED' => 'Posted']"
                :selected="request('status')"
                placeholder="All statuses"
            />
        </div>
        <x-button type="submit" variant="secondary">Filter</x-button>
        @if (request('status'))
            <x-button as="a" href="{{ route('journals.index') }}" variant="ghost">Clear</x-button>
        @endif
    </form>

    <x-table :headers="['Number', 'Date', 'Description', 'Debit', 'Credit', 'Status']">
        @forelse ($journals as $journal)
            <tr>
                <td class="px-4 py-2.5">
                    <a href="{{ route('journals.show', $journal) }}" class="font-mono text-xs text-emerald-700 hover:underline">
                        {{ $journal->journal_number }}
                    </a>
                    @if ($journal->isOpeningBalance())
                        <x-badge variant="info" class="ml-1.5">Opening</x-badge>
                    @endif
                </td>
                <td class="px-4 py-2.5 text-slate-600">{{ $journal->transaction_date->format('d M Y') }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ $journal->description ?? '—' }}</td>
                <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$journal->total_debit ?? 0" :symbol="false" /></td>
                <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$journal->total_credit ?? 0" :symbol="false" /></td>
                <td class="px-4 py-2.5">
                    <x-badge :variant="$journal->isPosted() ? 'success' : 'neutral'">{{ $journal->status }}</x-badge>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No journal entries yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$journals" class="mt-4" />
@endsection
