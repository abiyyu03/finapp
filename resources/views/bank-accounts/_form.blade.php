@php
    $statusOptions = [\App\Models\BankAccount::ACTIVE => 'ACTIVE', \App\Models\BankAccount::INACTIVE => 'INACTIVE'];
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-input name="bank_name" label="Bank" :value="$bankAccount?->bank_name" hint="e.g. BCA, Mandiri, BRI" />
    <x-input name="account_name" label="Account holder name" :value="$bankAccount?->account_name" />
    <x-input name="account_number" label="Account number" :value="$bankAccount?->account_number" />
    <x-select
        name="account_id"
        label="Linked account"
        :options="$accountOptions"
        :selected="$bankAccount?->account_id"
        placeholder="Select an ASSET account"
    />
    <x-select name="status" label="Status" :options="$statusOptions" :selected="$bankAccount?->status ?? 'ACTIVE'" />
</div>
