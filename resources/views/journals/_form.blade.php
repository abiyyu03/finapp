@php
    $initialLines = old('lines', $journal?->lines->map(fn ($line) => [
        'account_id' => (string) $line->account_id,
        'description' => $line->description,
        'debit' => $line->debit > 0 ? (string) $line->debit : '',
        'credit' => $line->credit > 0 ? (string) $line->credit : '',
    ])->all() ?? []);

    // Which dynamic rows a server-side validation error belongs to (e.g.
    // "lines.1.debit"), so the row itself can be highlighted — not just a
    // generic message at the top of the form.
    $lineErrorIndexes = collect($errors->keys())
        ->map(fn ($key) => preg_match('/^lines\.(\d+)\./', $key, $m) ? (int) $m[1] : null)
        ->filter(fn ($index) => $index !== null)
        ->unique()
        ->values();
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-input name="transaction_date" label="Date" type="date" :value="$journal?->transaction_date?->format('Y-m-d')" />
    <x-input name="description" label="Description" :value="$journal?->description" />
</div>

<div x-data="journalForm(@js($initialLines), @js($accountOptions), @js($lineErrorIndexes))" class="mt-6">
    <div class="overflow-x-auto rounded-lg border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Account</th>
                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                    <th class="w-36 px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Debit</th>
                    <th class="w-36 px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Credit</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <template x-for="(line, index) in lines" :key="index">
                    <tr>
                        <td class="px-3 py-2">
                            <select
                                :name="'lines[' + index + '][account_id]'"
                                x-model="line.account_id"
                                class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            >
                                <option value="">Select account</option>
                                <template x-for="(label, id) in accounts" :key="id">
                                    <option :value="id" x-text="label" :selected="String(line.account_id) === String(id)"></option>
                                </template>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <input
                                type="text"
                                :name="'lines[' + index + '][description]'"
                                x-model="line.description"
                                class="block w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                type="number" step="0.01" min="0"
                                :name="'lines[' + index + '][debit]'"
                                x-model="line.debit"
                                :class="hasError(index) ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500'"
                                class="block w-full rounded-md border px-2.5 py-1.5 text-right text-sm text-slate-900 focus:outline-none focus:ring-2"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input
                                type="number" step="0.01" min="0"
                                :name="'lines[' + index + '][credit]'"
                                x-model="line.credit"
                                :class="hasError(index) ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-500'"
                                class="block w-full rounded-md border px-2.5 py-1.5 text-right text-sm text-slate-900 focus:outline-none focus:ring-2"
                            />
                        </td>
                        <td class="px-2 py-2 text-center">
                            <button
                                type="button"
                                @click="removeLine(index)"
                                x-show="lines.length > 2"
                                class="text-slate-400 hover:text-red-600"
                                aria-label="Remove line"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 font-medium">
                <tr>
                    <td colspan="2" class="px-3 py-2.5 text-right text-slate-600">Total</td>
                    <td class="px-3 py-2.5 text-right font-mono text-xs" x-text="formatMoney(totalDebit)"></td>
                    <td class="px-3 py-2.5 text-right font-mono text-xs" x-text="formatMoney(totalCredit)"></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2" class="px-3 py-1.5 text-right text-slate-500">Difference</td>
                    <td colspan="2" class="px-3 py-1.5 text-right font-mono text-xs" :class="difference === 0 ? 'text-emerald-700' : 'text-red-600'" x-text="formatMoney(difference)"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <button
        type="button"
        @click="addLine"
        class="mt-3 text-sm font-medium text-emerald-700 hover:text-emerald-800"
    >
        + Add line
    </button>

    <p class="mt-2 text-xs text-slate-400">
        @if ($autoPosts ?? false)
            Difference is a live preview. This form posts immediately, so it must be zero to succeed.
        @else
            Difference is a live preview — it doesn't have to be zero to save a draft. Balancing is enforced when the journal is posted (STEP 6).
        @endif
    </p>
</div>
