<?php

use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\PettyCashRequest;
use App\Services\PettyCashService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public int $id;

    #[Validate('required|string|max:2000')]
    public string $editDescription = '';

    public string $budgetCode = '';

    public string $budgetDescription = '';

    #[Validate('required|string|max:2000')]
    public string $reason = '';

    #[Validate('required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $officialReceipt;

    public function mount(): void
    {
        $this->editDescription = $this->item->description;
    }

    #[Computed]
    public function item(): PettyCashRequest
    {
        $item = PettyCashRequest::with(['requester', 'files', 'events.actor'])->findOrFail($this->id);

        abort_unless(auth()->user()->can('view-request', $item), 403);

        return $item;
    }

    #[Computed]
    public function canReview(): bool
    {
        return auth()->user()->can('review-requests');
    }

    #[Computed]
    public function canProcess(): bool
    {
        return auth()->user()->can('process-requests');
    }

    public function resubmit(): void
    {
        $this->validate(['editDescription' => ['required', 'string', 'max:2000']]);

        $request = app(PettyCashService::class)->resubmit($this->item, auth()->user(), $this->editDescription);

        session()->flash('status', 'Pengajuan '.$request->request_number.' diajukan ulang.');

        unset($this->item);
    }

    public function review(string $action): void
    {
        abort_unless(auth()->user()->can('review-requests'), 403);

        $this->validate($this->reviewRules($action));

        app(PettyCashService::class)->review(
            admin: auth()->user(),
            request: $this->item,
            action: $action,
            budgetCode: $action === 'approve' ? $this->budgetCode : null,
            budgetDescription: $action === 'approve' ? $this->budgetDescription : null,
            reason: in_array($action, ['revise', 'reject']) ? $this->reason : null,
        );

        $flash = match ($action) {
            'approve' => 'disetujui',
            'revise' => 'diminta revisi',
            'reject' => 'ditolak',
        };

        session()->flash('status', 'Pengajuan '.$this->item->request_number.' telah '.$flash.'.');

        $this->reset('budgetCode', 'budgetDescription', 'reason');

        unset($this->item);
    }

    public function markPaid(): void
    {
        abort_unless(auth()->user()->can('process-requests'), 403);

        $this->validate([
            'officialReceipt' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        app(PettyCashService::class)->markPaid(auth()->user(), $this->item, $this->officialReceipt);

        session()->flash('status', 'Pengajuan '.$this->item->request_number.' telah ditandai SELESAI.');

        $this->reset('officialReceipt');

        unset($this->item);
    }

    private function reviewRules(string $action): array
    {
        return $action === 'approve'
            ? [
                'budgetCode' => ['required', 'string', 'max:50'],
                'budgetDescription' => ['required', 'string', 'max:255'],
            ]
            : ['reason' => ['required', 'string', 'max:2000']];
    }

    #[Computed]
    public function status(): RequestStatus
    {
        return $this->item->status();
    }

    #[Computed]
    public function budgetCodes()
    {
        return \App\Models\BudgetCode::query()->where('is_active', true)->orderBy('code')->get();
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $this->item->request_number }}
            </h2>
            <x-status-badge :status="$this->item->status" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
                    <ul class="list-disc ps-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Detail Pengajuan</h3>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Pengaju</dt>
                            <dd class="mt-1 font-medium text-gray-900">{{ $this->item->requester->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Nominal</dt>
                            <dd class="mt-1 font-medium text-gray-900">Rp {{ number_format($this->item->nominal, 0, ',', '.') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-gray-500">Keperluan</dt>
                            <dd class="mt-1 text-gray-900 whitespace-pre-line">{{ $this->item->description }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Diajukan</dt>
                            <dd class="mt-1 text-gray-900">{{ $this->item->submitted_at?->format('d M Y H:i') }}</dd>
                        </div>
                        @if ($this->item->drive_folder_url)
                            <div>
                                <dt class="text-gray-500">Folder Google Drive</dt>
                                <dd class="mt-1">
                                    <a href="{{ $this->item->drive_folder_url }}" target="_blank" rel="noopener"
                                       class="text-indigo-600 hover:text-indigo-500 font-medium">Buka folder →</a>
                                </dd>
                            </div>
                        @endif
                        @can('fill-budget-code')
                            @if ($this->item->budget_code)
                                <div>
                                    <dt class="text-gray-500">Kode Anggaran</dt>
                                    <dd class="mt-1 font-medium text-gray-900">{{ $this->item->budget_code }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Uraian Anggaran</dt>
                                    <dd class="mt-1 text-gray-900">{{ $this->item->budget_description }}</dd>
                                </div>
                            @endif
                        @endcan
                    </dl>
                </div>
            </div>

            @if ($this->item->files->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-gray-900">Berkas</h3>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach ($this->item->files as $file)
                                <li class="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-4 py-2">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-900 truncate">{{ $file->original_name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ \App\Enums\RequestFileType::from($file->type)->label() }}
                                            · {{ $file->created_at->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        @if ($file->drive_url)
                                            <a href="{{ $file->drive_url }}" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-500">Drive</a>
                                        @endif
                                        <a href="{{ route('requests.files.download', ['id' => $this->item->id, 'file' => $file->id]) }}" class="text-indigo-600 hover:text-indigo-500">Unduh</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Linimasa Status</h3>
                    <ol class="mt-4 space-y-4">
                        @foreach ($this->item->events->sortBy('created_at') as $event)
                            <li class="flex gap-3">
                                <div class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></div>
                                <div class="text-sm">
                                    <p class="font-medium text-gray-900">
                                        {{ \App\Enums\RequestEventType::from($event->event)->label() }}
                                    </p>
                                    <p class="text-gray-500">
                                        {{ $event->actor?->name ?? 'Sistem' }} · {{ $event->created_at->format('d M Y H:i') }}
                                    </p>
                                    @if ($event->note)
                                        <p class="mt-1 text-gray-700 bg-gray-50 rounded-md px-3 py-2 whitespace-pre-line">{{ $event->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- Requester actions --}}
            @if (auth()->user()->isRequester() && $this->status === App\Enums\RequestStatus::NeedsRevision)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form wire:submit="resubmit" class="p-6">
                        <h3 class="font-semibold text-gray-900">Ajukan Ulang</h3>
                        <p class="mt-1 text-sm text-gray-500">Perbaiki keperluan sesuai permintaan revisi lalu kirim ulang.</p>
                        <textarea wire:model="editDescription" rows="3" class="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('editDescription')" class="mt-2" />
                        <div class="mt-4">
                            <x-primary-button>Ajukan Ulang</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Admin review actions --}}
            @if ($this->canReview && $this->status === App\Enums\RequestStatus::PendingReview)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="font-semibold text-gray-900">Validasi &amp; Kode Anggaran</h3>

                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label value="Kode Anggaran *" />
                                <select wire:model="budgetCode"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">— Pilih kode anggaran —</option>
                                    @foreach ($this->budgetCodes as $code)
                                        <option value="{{ $code->code }}">{{ $code->code }} — {{ $code->description }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('budgetCode')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label value="Uraian Anggaran *" />
                                <x-text-input wire:model="budgetDescription" class="mt-1 block w-full" placeholder="Contoh: Operasional & Umum" />
                                <x-input-error :messages="$errors->get('budgetDescription')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label value="Alasan (wajib untuk Revisi / Tolak)" />
                                <textarea wire:model="reason" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>

                            <div class="flex flex-wrap gap-3 pt-2">
                                <button type="button" wire:click="review('approve')" wire:loading.attr="disabled" wire:confirm="Approve pengajuan ini?"
                                        class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500">
                                    Approve
                                </button>
                                <button type="button" wire:click="review('revise')" wire:loading.attr="disabled" wire:confirm="Minta revisi? Pengaju akan diberi notifikasi beserta alasannya."
                                        class="inline-flex items-center rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-400">
                                    Minta Revisi
                                </button>
                                <button type="button" wire:click="review('reject')" wire:loading.attr="disabled" wire:confirm="Tolak pengajuan ini?"
                                        class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                                    Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Finance process actions --}}
            @if ($this->canProcess && $this->status === App\Enums\RequestStatus::Processing)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <form wire:submit="markPaid" class="p-6">
                        <h3 class="font-semibold text-gray-900">Proses Pencairan &amp; Selesaikan</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Lakukan transfer, lalu unggah bukti transfer resmi. Wajib ada sebelum status menjadi Selesai.
                        </p>
                        <input wire:model="officialReceipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                               class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                        <x-input-error :messages="$errors->get('officialReceipt')" class="mt-2" />
                        <div class="mt-4">
                            <x-primary-button wire:loading.attr="disabled">Tandai Selesai</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>