<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Verifikasi email</h1>
    <p class="mt-1.5 text-sm text-slate-500">
        Kami sudah mengirim tautan verifikasi ke email Anda. Klik tautan tersebut untuk mulai memakai aplikasi.
    </p>

    @if (session('status') == 'verification-link-sent')
        <x-auth-session-status class="mt-6" status="Tautan verifikasi baru sudah dikirim ke email Anda." />
    @endif

    <div class="mt-8 flex items-center justify-between gap-3">
        <x-primary-button wire:click="sendVerification">Kirim ulang email</x-primary-button>

        <button wire:click="logout" type="submit" class="btn-ghost">Keluar</button>
    </div>
</div>
