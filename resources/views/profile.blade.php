<x-app-layout>
    <x-slot name="title">Profil</x-slot>

    <x-slot name="header">
        <x-page-header title="Profil Saya" description="Perbarui data diri, nomor WhatsApp, dan password Anda." />
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="card p-5 sm:p-8">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="card p-5 sm:p-8">
            <livewire:profile.update-password-form />
        </div>

        <div class="card border-rose-200 p-5 sm:p-8">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
