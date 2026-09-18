@php
    $typeOptions = collect(\App\Models\Account::TYPES)->mapWithKeys(fn ($type) => [$type => $type])->all();
    $statusOptions = [\App\Models\Account::ACTIVE => 'ACTIVE', \App\Models\Account::INACTIVE => 'INACTIVE'];
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    {{-- x-input/x-select already resolve `old($name, $value)` themselves, so
         $value here is only the *fallback* used on a fresh (non-redisplay)
         render: the account's current value on edit, or a sane default on
         create. --}}
    <x-input name="code" label="Code" :value="$account?->code" hint="Unique within this company." />
    <x-input name="name" label="Name" :value="$account?->name" />
    <x-select name="type" label="Type" :options="$typeOptions" :selected="$account?->type" placeholder="Select a type" />
    <x-select
        name="parent_id"
        label="Parent account"
        :options="$parentOptions"
        :selected="$account?->parent_id"
        placeholder="No parent"
    />
    <x-select name="status" label="Status" :options="$statusOptions" :selected="$account?->status ?? 'ACTIVE'" />
</div>
