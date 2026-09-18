@extends('layouts.admin')

@section('title', 'Profit & Loss')

@section('breadcrumb')
    <span class="text-slate-400">Reports</span> / <span class="text-slate-700">Profit &amp; Loss</span>
@endsection

@section('content')
    <div class="py-6">
        <h1 class="text-lg font-semibold text-slate-900">Profit &amp; Loss</h1>
        <p class="mt-1 text-sm text-slate-500">Revenue and expense from posted journals only. Drafts never affect this.</p>
    </div>

    <form method="GET" action="{{ route('reports.profit-loss') }}" class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <x-input name="date_from" label="Date From" type="date" :value="$dateFrom" />
        <x-input name="date_to" label="Date To" type="date" :value="$dateTo" />
        <div class="flex items-end">
            <x-button type="submit" variant="primary" class="w-full justify-center">Run Report</x-button>
        </div>
    </form>

    @if ($dateFrom || $dateTo)
        <p class="mb-3 text-sm text-slate-500">
            Period: {{ $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->translatedFormat('d F Y') : 'the beginning' }}
            – {{ $dateTo ? \Illuminate\Support\Carbon::parse($dateTo)->translatedFormat('d F Y') : 'now' }}
        </p>
    @endif

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue</div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @forelse ($report['revenueLines'] as $line)
                    <tr>
                        <td class="px-4 py-2 text-slate-600">{{ $line->code }} — {{ $line->name }}</td>
                        <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$line->balance" /></td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-4 text-center text-sm text-slate-400">No revenue posted in this period.</td></tr>
                @endforelse
                <tr class="bg-slate-50 font-medium">
                    <td class="px-4 py-2.5 text-slate-700">Total Revenue</td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$report['totalRevenue']" /></td>
                </tr>
            </tbody>
        </table>

        <div class="border-y border-slate-200 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Expenses</div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @forelse ($report['expenseLines'] as $line)
                    <tr>
                        <td class="px-4 py-2 text-slate-600">{{ $line->code }} — {{ $line->name }}</td>
                        <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$line->balance" /></td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-4 text-center text-sm text-slate-400">No expenses posted in this period.</td></tr>
                @endforelse
                <tr class="bg-slate-50 font-medium">
                    <td class="px-4 py-2.5 text-slate-700">Total Expenses</td>
                    <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$report['totalExpense']" /></td>
                </tr>
            </tbody>
        </table>

        <table class="w-full text-sm">
            <tbody>
                <tr class="{{ $report['netProfit'] >= 0 ? 'bg-emerald-50' : 'bg-red-50' }} font-semibold">
                    <td class="px-4 py-3 text-slate-800">{{ $report['netProfit'] >= 0 ? 'Net Profit' : 'Net Loss' }}</td>
                    <td class="px-4 py-3 text-right font-mono text-sm"><x-currency :amount="$report['netProfit']" /></td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
