<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') · {{ config('app.name', 'FinApp') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data="{ sidebarOpen: false }"
        class="h-full font-sans text-sm text-slate-800 antialiased"
    >
        <div class="flex h-full">
            {{-- Sidebar --}}
            <div
                x-show="sidebarOpen"
                x-cloak
                @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
            ></div>

            <aside
                x-cloak
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
                class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white transition-transform duration-150 lg:static lg:translate-x-0"
            >
                <div class="flex h-14 shrink-0 items-center gap-2 border-b border-slate-200 px-5">
                    <span class="flex h-7 w-7 items-center justify-center rounded bg-emerald-600 text-xs font-bold text-white">FA</span>
                    <span class="text-sm font-semibold tracking-tight text-slate-900">FinApp</span>
                </div>

                <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                    <div>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            Dashboard
                        </x-nav-link>
                    </div>

                    <x-nav-group label="Accounting" :active="request()->routeIs('accounts.*') || request()->routeIs('journals.*')">
                        <x-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">Chart of Accounts</x-nav-link>
                        <x-nav-link :href="route('journals.index')" :active="request()->routeIs('journals.*')">Journal Entries</x-nav-link>
                    </x-nav-group>

                    <x-nav-group label="Cash & Bank" :active="request()->routeIs('cash-accounts.*') || request()->routeIs('bank-accounts.*') || request()->routeIs('cash-transactions.*')">
                        <x-nav-link :href="route('cash-accounts.index')" :active="request()->routeIs('cash-accounts.*')">Cash Accounts</x-nav-link>
                        <x-nav-link :href="route('bank-accounts.index')" :active="request()->routeIs('bank-accounts.*')">Bank Accounts</x-nav-link>
                        <x-nav-link :href="route('cash-transactions.index')" :active="request()->routeIs('cash-transactions.*')">Cash Transactions</x-nav-link>
                    </x-nav-group>

                    <x-nav-group label="Reports" :active="request()->routeIs('reports.*')">
                        <x-nav-link :href="route('reports.general-ledger')" :active="request()->routeIs('reports.general-ledger')">General Ledger</x-nav-link>
                        <x-nav-link :href="route('reports.profit-loss')" :active="request()->routeIs('reports.profit-loss')">Profit &amp; Loss</x-nav-link>
                        <x-nav-link :href="route('reports.balance-sheet')" :active="request()->routeIs('reports.balance-sheet')">Balance Sheet</x-nav-link>
                    </x-nav-group>

                    <x-nav-group label="Administration" :active="request()->routeIs('users.*') || request()->routeIs('roles.*') || request()->routeIs('audit-logs.*')">
                        <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Users</x-nav-link>
                        <x-nav-link :href="route('roles.index')" :active="request()->routeIs('roles.*')">Roles &amp; Permissions</x-nav-link>
                        <x-nav-link :href="route('audit-logs.index')" :active="request()->routeIs('audit-logs.*')">Audit Logs</x-nav-link>
                    </x-nav-group>
                </nav>

                <div class="border-t border-slate-200 p-3 text-xs text-slate-400">
                    MVP v1.1.0
                </div>
            </aside>

            {{-- Main column --}}
            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Topbar --}}
                <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:px-6">
                    <button
                        type="button"
                        @click="sidebarOpen = true"
                        class="rounded p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                        aria-label="Open sidebar"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>

                    <div class="flex-1"></div>

                    {{-- Company switcher --}}
                    @php $activeCompany = auth()->user()->activeCompany(); @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-1.5 rounded-md border border-slate-200 px-3 py-1.5 text-slate-700 hover:bg-slate-50"
                        >
                            <span>{{ $activeCompany?->name ?? 'Select Company' }}</span>
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            x-show="open"
                            x-cloak
                            x-transition
                            class="absolute right-0 z-30 mt-1 w-56 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg"
                        >
                            @foreach (auth()->user()->companies as $company)
                                <form method="POST" action="{{ route('company.switch', $company) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        @class([
                                            'flex w-full items-center justify-between px-3 py-2 text-left hover:bg-slate-50',
                                            'font-medium text-emerald-700' => $activeCompany?->id === $company->id,
                                            'text-slate-700' => $activeCompany?->id !== $company->id,
                                        ])
                                    >
                                        {{ $company->name }}
                                        @if ($activeCompany?->id === $company->id)
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                    {{-- User menu --}}
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-2 rounded-md px-2 py-1.5 text-slate-700 hover:bg-slate-50"
                        >
                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-xs font-medium text-slate-600">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                            <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            x-show="open"
                            x-cloak
                            x-transition
                            class="absolute right-0 z-30 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 text-sm shadow-lg"
                        >
                            <div class="px-3 py-2 text-xs text-slate-400">{{ auth()->user()->email }}</div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </header>

                {{-- Breadcrumb --}}
                @hasSection('breadcrumb')
                    <div class="border-b border-slate-200 bg-white px-4 py-2.5 text-xs text-slate-500 lg:px-6">
                        @yield('breadcrumb')
                    </div>
                @endif

                {{-- Flash messages --}}
                <div class="px-4 pt-4 lg:px-6">
                    @if (session('success'))
                        <x-alert variant="success" class="mb-4">{{ session('success') }}</x-alert>
                    @endif
                    @if (session('error'))
                        <x-alert variant="error" class="mb-4">{{ session('error') }}</x-alert>
                    @endif
                    @if (session('warning'))
                        <x-alert variant="warning" class="mb-4">{{ session('warning') }}</x-alert>
                    @endif
                    @if (session('info'))
                        <x-alert variant="info" class="mb-4">{{ session('info') }}</x-alert>
                    @endif

                    @if ($errors->any())
                        <x-alert variant="error" class="mb-4">
                            <p class="font-medium">Please fix the following:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif
                </div>

                {{-- Page content --}}
                <main class="flex-1 px-4 pb-10 lg:px-6">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
