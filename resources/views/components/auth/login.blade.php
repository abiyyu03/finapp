<?php

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Livewire (auth module only — spec §3/§7 amendment) sits on top of
     * Laravel's own session guard. It changes how this screen renders, not
     * which authentication system is used.
     */
    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        session()->regenerate();

        $companyCount = Auth::user()->companies()->count();

        $this->redirect(
            $companyCount === 1 ? route('dashboard') : route('company.select'),
            navigate: false,
        );
    }
};
?>

<div class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-12">
    <div class="w-full max-w-sm">
        <div class="mb-8 flex flex-col items-center gap-2">
            <span class="flex h-10 w-10 items-center justify-center rounded bg-emerald-600 text-sm font-bold text-white">FA</span>
            <span class="text-base font-semibold tracking-tight text-slate-900">FinApp</span>
        </div>

        <form wire:submit="login" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h1 class="text-base font-semibold text-slate-900">Sign in</h1>
                <p class="mt-1 text-sm text-slate-500">Use your company account to continue.</p>
            </div>

            <x-input wire:model="email" name="email" label="Email" type="email" autofocus autocomplete="username" />
            <x-input wire:model="password" name="password" label="Password" type="password" autocomplete="current-password" />

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input wire:model="remember" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                Remember me
            </label>

            <x-button type="submit" variant="primary" class="w-full justify-center" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </x-button>
        </form>
    </div>
</div>
