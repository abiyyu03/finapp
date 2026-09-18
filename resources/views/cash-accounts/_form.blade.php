@php
    $statusOptions = [\App\Models\CashAccount::ACTIVE => 'ACTIVE', \App\Models\CashAccount::INACTIVE => 'INACTIVE'];
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-input name="name" label="Name" :value="$cashAccount?->name" hint="e.g. Main Cash, Petty Cash" />
    <x-select
        name="account_id"
        label="Linked account"
        :options="$accountOptions"
        :selected="$cashAccount?->account_id"
        placeholder="Select an ASSET account"
    />
    <x-select name="status" label="Status" :options="$statusOptions" :selected="$cashAccount?->status ?? 'ACTIVE'" />
</div>
