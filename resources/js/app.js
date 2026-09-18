import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Dynamic journal-line rows (spec STEP 5). The totals shown here are a live
// UI preview only — never the system of record. The authoritative
// debit = credit check happens server-side in JournalPostingService (STEP 6),
// computed by Postgres over NUMERIC columns, not by this JS.
document.addEventListener('alpine:init', () => {
    Alpine.data('journalForm', (initialLines, accounts, errorIndexes = []) => ({
        accounts,
        errorIndexes,
        lines: initialLines.length
            ? initialLines
            : [
                  { account_id: '', description: '', debit: '', credit: '' },
                  { account_id: '', description: '', debit: '', credit: '' },
              ],

        hasError(index) {
            return this.errorIndexes.includes(index)
        },

        addLine() {
            this.lines.push({ account_id: '', description: '', debit: '', credit: '' })
        },

        removeLine(index) {
            if (this.lines.length > 2) {
                this.lines.splice(index, 1)
            }
        },

        get totalDebit() {
            return this.lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0)
        },

        get totalCredit() {
            return this.lines.reduce((sum, line) => sum + (parseFloat(line.credit) || 0), 0)
        },

        get difference() {
            return Math.round((this.totalDebit - this.totalCredit) * 100) / 100
        },

        formatMoney(amount) {
            // Matches <x-currency> (spec §20): whole Rupiah, no sen.
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount)
        },
    }))
})

// Loading state (STEP 19), applied once, site-wide: disable every submit
// button in a form the instant it's submitted, so a slow request can't be
// fired twice by an impatient second click. <x-button> already styles
// `disabled` (dimmed, no-cursor), so this needs no per-view markup.
document.addEventListener('submit', (event) => {
    if (!(event.target instanceof HTMLFormElement)) {
        return
    }

    event.target.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true
    })
})

Alpine.start();
