@props(['amount', 'symbol' => true])

{{--
    Single source of truth for money display (spec §20): Indonesian
    thousand-separator, whole Rupiah (no sen — matches every worked example
    in both spec documents, none of which show a comma-decimal amount).
    `symbol` is on for report/summary figures ("Rp1.000.000") and off for a
    ledger-style debit/credit column, where the currency is already implied
    by the table itself.
--}}
<span {{ $attributes }}>{{ $symbol ? 'Rp' : '' }}{{ number_format($amount, 0, ',', '.') }}</span>
