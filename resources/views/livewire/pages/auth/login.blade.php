<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Masuk')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Selamat datang kembali</h1>
    <p class="mt-1.5 text-sm text-slate-500">Masuk dengan akun kantor Anda untuk melanjutkan.</p>

    <x-auth-session-status class="mt-6" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input wire:model="form.email" id="email" class="mt-1.5" type="email" name="email"
                          placeholder="nama@digitaliz.id" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-1.5" />
        </div>

        <div x-data="{ show: false }">
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Password" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-brand-600 hover:text-brand-700" href="{{ route('password.request') }}" wire:navigate>
                        Lupa password?
                    </a>
                @endif
            </div>
            <div class="relative mt-1.5">
                <x-text-input wire:model="form.password" id="password" class="pr-16" x-bind:type="show ? 'text' : 'password'"
                              type="password" name="password" required autocomplete="current-password" />
                <button type="button" x-on:click="show = !show"
                        class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-slate-500 hover:text-slate-700"
                        x-text="show ? 'Sembunyikan' : 'Lihat'">Lihat</button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1.5" />
        </div>

        <label for="remember" class="flex items-center gap-2">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                   class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            <span class="text-sm text-slate-600">Ingat saya di perangkat ini</span>
        </label>

        <button type="submit" class="btn-primary w-full py-3" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memproses…</span>
        </button>
    </form>

    @if (Route::has('register'))
        <p class="mt-8 text-center text-sm text-slate-500">
            Belum punya akun?
            <a href="{{ route('register') }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-700">Daftar sebagai pengaju</a>
        </p>
    @endif
</div>
