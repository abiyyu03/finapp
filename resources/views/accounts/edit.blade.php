@extends('layouts.admin')

@section('title', 'Edit Account')

@section('breadcrumb')
    <span class="text-slate-400">Accounting</span> /
    <a href="{{ route('accounts.index') }}" class="text-slate-400 hover:text-slate-600">Chart of Accounts</a> /
    <span class="text-slate-700">{{ $account->code }}</span>
@endsection

@section('content')
    <div class="mx-auto max-w-2xl py-6">
        <h1 class="text-lg font-semibold text-slate-900">Edit {{ $account->code }} — {{ $account->name }}</h1>

        @if ($account->is_system)
            <x-alert variant="info" class="mt-4">This is a system account. Its code and type are best left unchanged.</x-alert>
        @endif

        <form method="POST" action="{{ route('accounts.update', $account) }}" class="mt-4 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')

            @include('accounts._form')

            <div class="flex justify-end gap-3">
                <x-button as="a" href="{{ route('accounts.index') }}" variant="secondary">Cancel</x-button>
                <x-button type="submit" variant="primary">Save Changes</x-button>
            </div>
        </form>
    </div>
@endsection
