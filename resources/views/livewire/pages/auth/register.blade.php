<?php

use App\Models\User;
use App\Support\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] #[Title('Daftar')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20', Phone::rule()],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['phone'] = Phone::normalize($validated['phone'] ?? null);
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Buat akun pengaju</h1>
    <p class="mt-1.5 text-sm text-slate-500">Akun baru otomatis berperan sebagai Pengaju. Peran lain diatur oleh Admin.</p>

    <form wire:submit="register" class="mt-8 space-y-5">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input wire:model="name" id="name" class="mt-1.5" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="email" value="Email kantor" />
            <x-text-input wire:model="email" id="email" class="mt-1.5" type="email" name="email" placeholder="nama@digitaliz.id" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="phone" value="Nomor WhatsApp" />
            <x-text-input wire:model="phone" id="phone" class="mt-1.5" type="tel" name="phone" placeholder="0812xxxxxxxx" inputmode="tel" autocomplete="tel" />
            <p class="mt-1.5 text-xs text-slate-500">Dipakai untuk notifikasi status pengajuan.</p>
            <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="password" value="Password" />
                <x-text-input wire:model="password" id="password" class="mt-1.5" type="password" name="password" required autocomplete="new-password" />
            </div>
            <div>
                <x-input-label for="password_confirmation" value="Ulangi password" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-1.5" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>
        </div>
        <x-input-error :messages="$errors->get('password')" class="-mt-3" />

        <button type="submit" class="btn-primary w-full py-3" wire:loading.attr="disabled">Daftar</button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-700">Masuk</a>
    </p>
</div>
