@extends('layouts.admin')

@section('title', 'Balance Sheet')

@section('breadcrumb')
    <span class="text-slate-400">Reports</span> / <span class="text-slate-700">Balance Sheet</span>
@endsection

@section('content')
    <div class="py-6">
        <h1 class="text-lg font-semibold text-slate-900">Balance Sheet</h1>
        <p class="mt-1 text-sm text-slate-500">
            As of a single date. "Current Year Earnings" is P&amp;L computed live, not a posted account —
            see <span class="font-mono text-xs">docs/accounting/balance-sheet.md</span> for why.
        </p>
    </div>

    <form method="GET" action="{{ route('reports.balance-sheet') }}" class="mb-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <x-input name="as_of_date" label="As Of Date" type="date" :value="$asOfDate" />
        <div class="flex items-end sm:col-start-3">
            <x-button type="submit" variant="primary" class="w-full justify-center">Run Report</x-button>
        </div>
    </form>

    <p class="mb-3 text-sm text-slate-500">
        As of: {{ \Illuminate\Support\Carbon::parse($asOfDate)->translatedFormat('d F Y') }}
    </p>

    @unless ($report['isBalanced'])
        <x-alert variant="error" class="mb-4">
            <p class="font-medium">Accounting error: Assets (<x-currency :amount="$report['totalAssets']" />)
                does not equal Liabilities + Equity (<x-currency :amount="$report['totalLiabilitiesAndEquity']" />).</p>
            <p class="mt-1 text-xs">This should never happen if every posted journal balances — please report this.</p>
        </x-alert>
    @endunless

    <div class="grid gap-4 md:grid-cols-2">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Assets</div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['assets'] as $line)
                        <tr>
                            <td class="px-4 py-2 text-slate-600">{{ $line->code }} — {{ $line->name }}</td>
                            <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$line->balance" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-4 py-4 text-center text-sm text-slate-400">No assets posted yet.</td></tr>
                    @endforelse
                    <tr class="bg-slate-50 font-medium">
                        <td class="px-4 py-2.5 text-slate-700">Total Assets</td>
                        <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$report['totalAssets']" /></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Liabilities</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report['liabilities'] as $line)
                            <tr>
                                <td class="px-4 py-2 text-slate-600">{{ $line->code }} — {{ $line->name }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$line->balance" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-4 text-center text-sm text-slate-400">Rp0</td></tr>
                        @endforelse
                        <tr class="bg-slate-50 font-medium">
                            <td class="px-4 py-2.5 text-slate-700">Total Liabilities</td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$report['totalLiabilities']" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Equity</div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($report['equityAccounts'] as $line)
                            <tr>
                                <td class="px-4 py-2 text-slate-600">{{ $line->code }} — {{ $line->name }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$line->balance" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-4 text-center text-sm text-slate-400">No equity accounts posted yet.</td></tr>
                        @endforelse
                        <tr>
                            <td class="px-4 py-2 text-slate-600">Current Year Earnings</td>
                            <td class="px-4 py-2 text-right font-mono text-xs"><x-currency :amount="$report['currentYearEarnings']" /></td>
                        </tr>
                        <tr class="bg-slate-50 font-medium">
                            <td class="px-4 py-2.5 text-slate-700">Total Equity</td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs"><x-currency :amount="$report['totalEquity']" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
                    <span>Total Liabilities + Equity</span>
                    <span class="font-mono text-xs"><x-currency :amount="$report['totalLiabilitiesAndEquity']" /></span>
                </div>
            </div>
        </div>
    </div>
@endsection
