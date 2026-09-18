@extends('layouts.admin')

@section('title', 'Audit Logs')

@section('breadcrumb')
    <span class="text-slate-400">Administration</span> / <span class="text-slate-700">Audit Logs</span>
@endsection

@section('content')
    <div class="py-6">
        <h1 class="text-lg font-semibold text-slate-900">Audit Logs</h1>
        <p class="mt-1 text-sm text-slate-500">Append-only. Nothing here can be edited or deleted from the app.</p>
    </div>

    <x-table :headers="['Time', 'User', 'Action', 'Resource']">
        @forelse ($logs as $log)
            <tr>
                <td class="px-4 py-2.5 text-slate-600">{{ $log->created_at->format('d M Y, H:i') }}</td>
                <td class="px-4 py-2.5 text-slate-800">{{ $log->user?->name ?? '—' }}</td>
                <td class="px-4 py-2.5">
                    <x-badge variant="info">{{ $log->action }}</x-badge>
                </td>
                <td class="px-4 py-2.5 text-slate-500">
                    {{ class_basename($log->resource_type) }} #{{ $log->resource_id }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No audit records yet.</td>
            </tr>
        @endforelse
    </x-table>

    <x-pagination :paginator="$logs" class="mt-4" />
@endsection
