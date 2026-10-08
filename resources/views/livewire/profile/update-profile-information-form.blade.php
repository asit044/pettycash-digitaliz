<?php

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->phone = (string) Auth::user()->phone;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Phone::rule()],
        ], attributes: ['name' => 'nama', 'phone' => 'nomor WhatsApp']);

        $validated['phone'] = Phone::normalize($validated['phone'] ?? null);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->phone = (string) $user->phone;

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-semibold text-slate-900">Informasi Profil</h2>
        <p class="mt-1 text-sm text-slate-500">Nama, email, dan nomor WhatsApp untuk notifikasi status pengajuan.</p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-5">
        <div>
            <x-input-label for="name" value="Nama lengkap" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1.5" required autofocus autocomplete="name" />
            <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="email" value="Email" />
                <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1.5" required autocomplete="username" />
                <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

                @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                    <p class="mt-2 text-sm text-slate-700">
                        Email Anda belum terverifikasi.
                        <button wire:click.prevent="sendVerification" class="font-semibold text-brand-600 underline hover:text-brand-700">
                            Kirim ulang email verifikasi.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-600">Tautan verifikasi baru sudah dikirim.</p>
                    @endif
                @endif
            </div>

            <div>
                <x-input-label for="phone" value="Nomor WhatsApp" />
                <x-text-input wire:model="phone" id="phone" name="phone" type="tel" class="mt-1.5" placeholder="0812xxxxxxxx" autocomplete="tel" />
                <x-input-error class="mt-1.5" :messages="$errors->get('phone')" />
                @if (blank(auth()->user()->phone))
                    <p class="mt-1.5 text-xs text-amber-600">Belum diisi — Anda tidak akan menerima notifikasi WhatsApp.</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Simpan</x-primary-button>

            <x-action-message class="text-sm font-medium text-emerald-600" on="profile-updated">
                Tersimpan.
            </x-action-message>
        </div>
    </form>
</section>
