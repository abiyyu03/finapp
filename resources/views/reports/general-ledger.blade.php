@extends('layouts.admin')

@section('title', 'General Ledger')

@section('breadcrumb')
    <span class="text-slate-400">Reports</span> / <span class="text-slate-700">General Ledger</span>
@endsection

@section('content')
    <div class="py-6">
        <h1 class="text-lg font-semibold text-slate-900">General Ledger</h1>
        <p class="mt-1 text-sm text-slate-500">Mutations of one account, from posted journals only. Drafts never appear here.</p>
    </div>

    <form method="GET" action="{{ route('reports.general-ledger') }}" class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
        <x-select
            name="account_id"
            label="Account"
            :options="$accountOptions"
            :selected="$selectedAccount?->id"
            placeholder="Select an account"
        />
        <x-input name="date_from" label="Date From" type="date" :value="$dateFrom" />
        <x-input name="date_to" label="Date To" type="date" :value="$dateTo" />
        <div class="flex items-end">
            <x-button type="submit" variant="primary" class="w-full justify-center">Show Ledger</x-button>
        </div>
    </form>

    @if (! $selectedAccount)
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-400">
            Select an account to view its ledger.
        </div>
    @else
        <div class="mb-3 text-sm text-slate-600">
            Account: <span class="font-medium text-slate-900">{{ $selectedAccount->code }} — {{ $selectedAccount->name }}</span>
            @if ($dateFrom || $dateTo)
                <span class="text-slate-400">
                    · Period: {{ $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->translatedFormat('d F Y') : 'the beginning' }}
                    – {{ $dateTo ? \Illuminate\Support\Carbon::parse($dateTo)->translatedFormat('d F Y') : 'now' }}
                </span>
            @endif
        </div>

        <x-table :headers="['Date', 'Journal Number', 'Description', 'Debit', 'Credit', 'Balance']">
            @if ($dateFrom)
                <tr class="bg-slate-50">
                    <td colspan="5" class="px-4 py-2.5 text-right text-slate-500">Balance brought forward</td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs font-medium"><x-currency :amount="$ledger['broughtForward']" :symbol="false" /></td>
                </tr>
            @endif

            @forelse ($ledger['rows'] as $row)
                <tr>
                    <td class="px-4 py-2.5 text-slate-600">{{ \Illuminate\Support\Carbon::parse($row->transaction_date)->format('d M Y') }}</td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-xs text-slate-500">{{ $row->journal_number }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-slate-500">{{ $row->description ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs">
                        @if ($row->debit > 0) <x-currency :amount="$row->debit" :symbol="false" /> @endif
                    </td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs">
                        @if ($row->credit > 0) <x-currency :amount="$row->credit" :symbol="false" /> @endif
                    </td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$row->balance" :symbol="false" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No posted transactions in this period.</td>
                </tr>
            @endforelse

            <tr class="bg-slate-50 font-medium">
                <td colspan="5" class="px-4 py-2.5 text-right text-slate-600">Ending balance</td>
                <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$ledger['endingBalance']" :symbol="false" /></td>
            </tr>
        </x-table>
    @endif
@endsection
