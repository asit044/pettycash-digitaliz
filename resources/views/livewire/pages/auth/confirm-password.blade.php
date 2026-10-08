<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Konfirmasi password</h1>
    <p class="mt-1.5 text-sm text-slate-500">Area ini dilindungi. Masukkan password Anda untuk melanjutkan.</p>

    <form wire:submit="confirmPassword" class="mt-8 space-y-5">
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input wire:model="password" id="password" class="mt-1.5" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <button type="submit" class="btn-primary w-full py-3" wire:loading.attr="disabled">Konfirmasi</button>
    </form>
</div>
