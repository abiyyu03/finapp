@extends('layouts.admin')

@section('title', 'Roles & Permissions')

@section('breadcrumb')
    <span class="text-slate-400">Administration</span> / <span class="text-slate-700">Roles &amp; Permissions</span>
@endsection

@section('content')
    <div class="py-6">
        <h1 class="text-lg font-semibold text-slate-900">Roles &amp; Permissions</h1>
        <p class="mt-1 text-sm text-slate-500">
            The MVP ships with a fixed set of roles (spec §3). This is what each one can do — there's no editor yet.
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($roles as $role)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">{{ $role->name }}</h2>
                <ul class="mt-3 space-y-1.5">
                    @forelse ($role->permissions as $permission)
                        <li class="flex items-center gap-2 text-sm text-slate-600">
                            <svg class="h-3.5 w-3.5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            <span class="font-mono text-xs">{{ $permission->key }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-400">No permissions assigned.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>
@endsection
