<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-5">
    <header>
        <h2 class="text-lg font-semibold text-rose-700">Hapus Akun</h2>
        <p class="mt-1 text-sm text-slate-500">
            Setelah dihapus, akun dan seluruh datanya hilang permanen. Pastikan tidak ada pengajuan yang masih berjalan.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Hapus Akun</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">
            <h2 class="text-lg font-semibold text-slate-900">Yakin ingin menghapus akun?</h2>
            <p class="mt-1 text-sm text-slate-500">Masukkan password Anda untuk mengonfirmasi. Tindakan ini tidak dapat dibatalkan.</p>

            <div class="mt-6">
                <x-input-label for="password" value="Password" class="sr-only" />
                <x-text-input wire:model="password" id="password" name="password" type="password" class="sm:w-3/4" placeholder="Password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-danger-button>Hapus Akun</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
