<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-semibold text-slate-900">Ubah Password</h2>
        <p class="mt-1 text-sm text-slate-500">Gunakan password yang panjang dan acak agar akun tetap aman.</p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-5">
        <div class="sm:max-w-sm">
            <x-input-label for="update_password_current_password" value="Password saat ini" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1.5" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-1.5" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="update_password_password" value="Password baru" />
                <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1.5" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Ulangi password baru" />
                <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1.5" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan Password</x-primary-button>

            <x-action-message class="text-sm font-medium text-emerald-600" on="password-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>
</section>
