<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Select Company · {{ config('app.name', 'FinApp') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans text-sm text-slate-800 antialiased">
        <div class="flex min-h-screen items-center justify-center px-4 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-6 flex flex-col items-center gap-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-emerald-600 text-sm font-bold text-white">FA</span>
                    <span class="text-base font-semibold tracking-tight text-slate-900">FinApp</span>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 class="text-base font-semibold text-slate-900">Select a company</h1>
                    <p class="mt-1 text-sm text-slate-500">You belong to more than one company. Choose which one to work in.</p>

                    <ul class="mt-4 space-y-2">
                        @foreach ($companies as $company)
                            <li>
                                <form method="POST" action="{{ route('company.switch', $company) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center justify-between rounded-md border border-slate-200 px-3 py-2.5 text-left hover:border-emerald-300 hover:bg-emerald-50"
                                    >
                                        <span class="font-medium text-slate-800">{{ $company->name }}</span>
                                        <x-badge :variant="$company->isActive() ? 'success' : 'neutral'">{{ $company->status }}</x-badge>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="text-xs text-slate-400 hover:text-slate-600">Sign out instead</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
