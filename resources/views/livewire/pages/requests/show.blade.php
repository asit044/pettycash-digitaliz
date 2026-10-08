<?php

use App\Enums\RequestEventType;
use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Services\PettyCashService;
use App\Support\Money;
use Illuminate\Validation\Rule;
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

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $revisionInvoice;

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $revisionProof;

    #[Validate('required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $officialReceipt;

    public function mount(): void
    {
        $this->editDescription = $this->item->description;
    }

    public function rendering($view): void
    {
        $view->title($this->item->request_number);
    }

    #[Computed]
    public function item(): PettyCashRequest
    {
        $item = PettyCashRequest::with(['requester', 'admin', 'files.uploader', 'events.actor'])->findOrFail($this->id);

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

    /**
     * Pre-fill the description from the master budget code; Admin may still edit it.
     */
    public function updatedBudgetCode(string $value): void
    {
        $this->budgetDescription = (string) BudgetCode::query()->where('code', $value)->value('description');
    }

    public function resubmit(): void
    {
        abort_unless(
            auth()->user()->isRequester() && (int) $this->item->requester_id === (int) auth()->id(),
            403
        );

        $this->validate([
            'editDescription' => ['required', 'string', 'max:2000'],
            'revisionInvoice' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'revisionProof' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ], attributes: ['editDescription' => 'keperluan']);

        $uploads = [];

        if ($this->revisionInvoice) {
            $uploads[] = ['type' => RequestFileType::Invoice->value, 'file' => $this->revisionInvoice];
        }

        if ($this->revisionProof) {
            $uploads[] = ['type' => RequestFileType::ProofTransfer->value, 'file' => $this->revisionProof];
        }

        $request = app(PettyCashService::class)->resubmit($this->item, auth()->user(), $this->editDescription, $uploads);

        session()->flash('status', 'Pengajuan '.$request->request_number.' diajukan ulang.');

        $this->reset('revisionInvoice', 'revisionProof');

        unset($this->item);
    }

    public function review(string $action): void
    {
        abort_unless(auth()->user()->can('review-requests'), 403);

        $this->validate($this->reviewRules($action), attributes: [
            'budgetCode' => 'kode anggaran',
            'budgetDescription' => 'uraian anggaran',
            'reason' => 'alasan',
        ]);

        app(PettyCashService::class)->review(
            admin: auth()->user(),
            request: $this->item,
            action: $action,
            budgetCode: $action === 'approve' ? $this->budgetCode : null,
            budgetDescription: $action === 'approve' ? $this->budgetDescription : null,
            reason: in_array($action, ['revise', 'reject']) ? $this->reason : null,
        );

        $flash = match ($action) {
            'approve' => 'disetujui dan diteruskan ke Finance',
            'revise' => 'dikembalikan untuk revisi',
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
        ], attributes: ['officialReceipt' => 'bukti transfer resmi']);

        app(PettyCashService::class)->markPaid(auth()->user(), $this->item, $this->officialReceipt);

        session()->flash('status', 'Pengajuan '.$this->item->request_number.' telah ditandai SELESAI. Pengaju sudah diberi notifikasi.');

        $this->reset('officialReceipt');

        unset($this->item);
    }

    private function reviewRules(string $action): array
    {
        return $action === 'approve'
            ? [
                'budgetCode' => ['required', 'string', 'max:50', Rule::exists('budget_codes', 'code')->where('is_active', true)],
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
        return BudgetCode::query()->where('is_active', true)->orderBy('code')->get();
    }

    /**
     * Most recent Admin note explaining a revision request or rejection.
     */
    #[Computed]
    public function latestAdminNote()
    {
        return $this->item->events
            ->whereIn('event', [RequestEventType::NeedsRevision->value, RequestEventType::Rejected->value])
            ->sortByDesc('id')
            ->first();
    }

    #[Computed]
    public function backUrl(): string
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdmin() => route('admin.index'),
            $user->isFinance() => route('finance.index'),
            $user->isHead() => route('head.index'),
            default => route('requests.index'),
        };
    }
}; ?>

@php
    $item = $this->item;
    $status = $this->status;
    $isRequester = auth()->user()->isRequester();
@endphp

<div>
    <x-slot name="header">
        <x-page-header :title="$item->request_number" :back="$this->backUrl"
                       :description="'Diajukan oleh '.$item->requester->name.' · '.$item->submitted_at?->translatedFormat('d F Y, H:i')">
            <x-status-badge :status="$item->status" class="px-3 py-1.5 text-sm" />
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="error" title="Periksa kembali isian Anda">
                <ul class="list-disc space-y-0.5 ps-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- Progress --}}
        <div class="card px-4 py-6 sm:px-8">
            <x-status-stepper :status="$status" />
        </div>

        {{-- Reason from Admin, shown to everyone while it is relevant --}}
        @if ($this->latestAdminNote && in_array($status, [RequestStatus::NeedsRevision, RequestStatus::Rejected], true))
            <x-alert :type="$status === RequestStatus::Rejected ? 'error' : 'warning'"
                     :title="$status === RequestStatus::Rejected ? 'Pengajuan ditolak' : 'Admin meminta revisi'">
                <p class="whitespace-pre-line">{{ $this->latestAdminNote->note }}</p>
                <p class="mt-1 text-xs opacity-75">
                    {{ $this->latestAdminNote->actor?->name ?? 'Admin' }} · {{ $this->latestAdminNote->created_at->translatedFormat('d M Y, H:i') }}
                </p>
            </x-alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Detail --}}
                <div class="card">
                    <div class="flex flex-col gap-1 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Nominal pengajuan</p>
                            <p class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">{{ Money::rupiah($item->nominal) }}</p>
                        </div>
                        @if ($item->drive_folder_url)
                            <a href="{{ $item->drive_folder_url }}" target="_blank" rel="noopener" class="btn-secondary self-start sm:self-auto">
                                <x-icon name="folder" class="size-4 text-amber-500" /> Folder Google Drive
                                <x-icon name="arrow-top-right" class="size-3.5 text-slate-400" />
                            </a>
                        @endif
                    </div>

                    <dl class="grid gap-x-6 gap-y-5 p-5 text-sm sm:grid-cols-2 sm:p-6">
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Keperluan</dt>
                            <dd class="mt-1 whitespace-pre-line text-slate-900">{{ $item->description }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Pengaju</dt>
                            <dd class="mt-1 font-semibold text-slate-900">{{ $item->requester->name }}</dd>
                            @unless ($isRequester)
                                <dd class="text-xs text-slate-500">{{ $item->requester->email }}{{ $item->requester->phone ? ' · '.$item->requester->phone : '' }}</dd>
                            @endunless
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Tanggal diajukan</dt>
                            <dd class="mt-1 text-slate-900">{{ $item->submitted_at?->translatedFormat('d F Y, H:i') }}</dd>
                        </div>

                        {{-- Budget fields are locked to Admin: requesters never see them --}}
                        @unless ($isRequester)
                            <div class="rounded-xl bg-slate-50 p-4 sm:col-span-2">
                                <dt class="flex items-center gap-1.5 font-medium text-slate-500">
                                    <x-icon name="tag" class="size-4" /> Kode &amp; uraian anggaran
                                    <span class="ml-auto inline-flex items-center gap-1 text-xs font-normal text-slate-400">
                                        <x-icon name="lock-closed" class="size-3.5" /> diisi Admin
                                    </span>
                                </dt>
                                @if ($item->budget_code)
                                    <dd class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-lg bg-white px-2.5 py-1 font-mono text-sm font-semibold text-slate-900 ring-1 ring-slate-200">{{ $item->budget_code }}</span>
                                        <span class="text-slate-700">{{ $item->budget_description }}</span>
                                    </dd>
                                @else
                                    <dd class="mt-2 text-slate-400 italic">Belum diisi</dd>
                                @endif
                            </div>
                        @endunless

                        @if ($item->admin)
                            <div>
                                <dt class="font-medium text-slate-500">Divalidasi oleh</dt>
                                <dd class="mt-1 text-slate-900">{{ $item->admin->name }}</dd>
                                <dd class="text-xs text-slate-500">{{ $item->reviewed_at?->translatedFormat('d M Y, H:i') }}</dd>
                            </div>
                        @endif
                        @if ($item->completed_at)
                            <div>
                                <dt class="font-medium text-slate-500">Selesai dicairkan</dt>
                                <dd class="mt-1 text-slate-900">{{ $item->completed_at->translatedFormat('d M Y, H:i') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Files --}}
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-semibold text-slate-900">Berkas</h2>
                        <span class="text-xs text-slate-500">{{ $item->files->count() }} berkas</span>
                    </div>
                    @if ($item->files->isEmpty())
                        <p class="px-6 py-8 text-center text-sm text-slate-500">Tidak ada berkas yang dilampirkan.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($item->files->sortBy('id') as $file)
                                @php($type = RequestFileType::from($file->type))
                                <li class="flex items-center gap-3 px-5 py-3.5 sm:px-6">
                                    <span @class([
                                        'grid size-10 shrink-0 place-items-center rounded-xl',
                                        'bg-emerald-50 text-emerald-600' => $type === RequestFileType::OfficialReceipt,
                                        'bg-slate-100 text-slate-500' => $type !== RequestFileType::OfficialReceipt,
                                    ])>
                                        <x-icon :name="$type === RequestFileType::OfficialReceipt ? 'check-circle' : 'paper-clip'" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $file->original_name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $type->label() }} · {{ $file->uploader?->name ?? '—' }} · {{ $file->created_at->translatedFormat('d M Y, H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        @if ($file->drive_url)
                                            <a href="{{ $file->drive_url }}" target="_blank" rel="noopener" class="btn-ghost px-2.5 py-2" title="Buka di Google Drive">
                                                <x-icon name="arrow-top-right" class="size-4" /><span class="hidden sm:inline">Drive</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('requests.files.download', ['id' => $item->id, 'file' => $file->id]) }}" class="btn-ghost px-2.5 py-2" title="Unduh">
                                            <x-icon name="arrow-down-tray" class="size-4" /><span class="hidden sm:inline">Unduh</span>
                                        </a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Requester: resubmit after revision --}}
                @if ($isRequester && $status === RequestStatus::NeedsRevision)
                    <form wire:submit="resubmit" class="card divide-y divide-slate-100 ring-2 ring-orange-200">
                        <div class="p-5 sm:p-6">
                            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                                <x-icon name="pencil" class="size-5 text-orange-500" /> Ajukan Ulang
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">Perbaiki keperluan sesuai catatan Admin di atas, tambahkan berkas bila perlu, lalu kirim ulang.</p>

                            <div class="mt-5 space-y-5">
                                <div>
                                    <x-input-label for="editDescription" value="Keperluan" required />
                                    <textarea wire:model="editDescription" id="editDescription" rows="4" class="field mt-1.5"></textarea>
                                    <x-input-error :messages="$errors->get('editDescription')" class="mt-1.5" />
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <x-file-input model="revisionInvoice" label="Invoice / Struk pengganti" />
                                    <x-file-input model="revisionProof" label="Bukti transfer tambahan" />
                                </div>
                                <p class="text-xs text-slate-500">Nominal tidak dapat diubah. Berkas lama tetap tersimpan sebagai riwayat.</p>
                            </div>
                        </div>
                        <div class="flex justify-end bg-slate-50/60 px-5 py-4 sm:px-6">
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="resubmit,revisionInvoice,revisionProof">
                                <x-icon name="arrow-path" class="size-4" /> Ajukan Ulang
                            </button>
                        </div>
                    </form>
                @endif

                {{-- Admin: validation --}}
                @if ($this->canReview && $status === RequestStatus::PendingReview)
                    <div class="card ring-2 ring-brand-200" x-data="{ tab: 'approve' }">
                        <div class="p-5 sm:p-6">
                            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                                <x-icon name="clipboard-check" class="size-5 text-brand-600" /> Validasi Admin
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">Periksa data &amp; berkas, lalu pilih tindakan. Setiap tindakan tercatat di linimasa.</p>

                            <div class="mt-5 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1 text-sm font-semibold">
                                <button type="button" x-on:click="tab = 'approve'" class="rounded-lg px-3 py-2 transition"
                                        x-bind:class="tab === 'approve' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">Setujui</button>
                                <button type="button" x-on:click="tab = 'revise'" class="rounded-lg px-3 py-2 transition"
                                        x-bind:class="tab === 'revise' ? 'bg-white text-orange-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">Minta Revisi</button>
                                <button type="button" x-on:click="tab = 'reject'" class="rounded-lg px-3 py-2 transition"
                                        x-bind:class="tab === 'reject' ? 'bg-white text-rose-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">Tolak</button>
                            </div>

                            {{-- Approve --}}
                            <div x-show="tab === 'approve'" class="mt-5 space-y-4">
                                <div>
                                    <x-input-label for="budgetCode" value="Kode Anggaran" required />
                                    <select wire:model.live="budgetCode" id="budgetCode" class="field mt-1.5">
                                        <option value="">— Pilih kode anggaran —</option>
                                        @foreach ($this->budgetCodes as $code)
                                            <option value="{{ $code->code }}">{{ $code->code }} — {{ $code->description }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('budgetCode')" class="mt-1.5" />
                                </div>
                                <div>
                                    <x-input-label for="budgetDescription" value="Uraian Anggaran" required />
                                    <x-text-input wire:model="budgetDescription" id="budgetDescription" class="mt-1.5" placeholder="Terisi otomatis dari kode, bisa diubah" />
                                    <x-input-error :messages="$errors->get('budgetDescription')" class="mt-1.5" />
                                </div>
                                <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-slate-500">Finance akan langsung menerima notifikasi WhatsApp.</p>
                                    <button type="button" wire:click="review('approve')" wire:loading.attr="disabled"
                                            wire:confirm="Setujui pengajuan ini dan teruskan ke Finance?"
                                            class="btn-success" @disabled($budgetCode === '')>
                                        <x-icon name="check" class="size-4" /> Setujui &amp; Teruskan
                                    </button>
                                </div>
                            </div>

                            {{-- Revise / Reject share the reason field --}}
                            <div x-show="tab !== 'approve'" x-cloak class="mt-5 space-y-4">
                                <div>
                                    <x-input-label for="reason" required>
                                        <span x-text="tab === 'revise' ? 'Apa yang perlu diperbaiki?' : 'Alasan penolakan'">Alasan</span>
                                    </x-input-label>
                                    <textarea wire:model="reason" id="reason" rows="3" class="field mt-1.5"
                                              placeholder="Alasan ini dikirim ke pengaju melalui WhatsApp."></textarea>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-1.5" />
                                </div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-slate-500">Pengaju menerima notifikasi beserta alasan ini.</p>
                                    <button type="button" x-show="tab === 'revise'" wire:click="review('revise')" wire:loading.attr="disabled"
                                            wire:confirm="Kembalikan pengajuan ini untuk direvisi?" class="btn-warning">
                                        <x-icon name="arrow-path" class="size-4" /> Minta Revisi
                                    </button>
                                    <button type="button" x-show="tab === 'reject'" wire:click="review('reject')" wire:loading.attr="disabled"
                                            wire:confirm="Tolak pengajuan ini? Tindakan ini final." class="btn-danger">
                                        <x-icon name="x-circle" class="size-4" /> Tolak Pengajuan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Finance: disbursement --}}
                @if ($this->canProcess && $status === RequestStatus::Processing)
                    <form wire:submit="markPaid" class="card divide-y divide-slate-100 ring-2 ring-sky-200">
                        <div class="p-5 sm:p-6">
                            <h2 class="flex items-center gap-2 font-semibold text-slate-900">
                                <x-icon name="banknotes" class="size-5 text-sky-600" /> Proses Pencairan
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">Transfer dana ke pengaju, lalu unggah bukti transfer resmi. Status tidak bisa Selesai tanpa bukti ini.</p>

                            <dl class="mt-5 grid gap-3 rounded-xl bg-sky-50/70 p-4 text-sm sm:grid-cols-3">
                                <div>
                                    <dt class="text-slate-500">Transfer ke</dt>
                                    <dd class="font-semibold text-slate-900">{{ $item->requester->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Nominal</dt>
                                    <dd class="font-semibold text-slate-900">{{ Money::rupiah($item->nominal) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Kode anggaran</dt>
                                    <dd class="font-mono font-semibold text-slate-900">{{ $item->budget_code ?? '—' }}</dd>
                                </div>
                            </dl>

                            <x-file-input model="officialReceipt" label="Bukti Transfer Resmi" required class="mt-5" />
                        </div>
                        <div class="flex justify-end bg-slate-50/60 px-5 py-4 sm:px-6">
                            <button type="submit" class="btn-success" wire:loading.attr="disabled" wire:target="markPaid,officialReceipt"
                                    wire:confirm="Tandai pengajuan ini SELESAI? Pengaju akan menerima notifikasi.">
                                <x-icon name="check-circle" class="size-4" /> Tandai Selesai
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Timeline --}}
            <aside>
                <div class="card p-5 sm:p-6 lg:sticky lg:top-6">
                    <h2 class="font-semibold text-slate-900">Linimasa Status</h2>
                    <p class="text-xs text-slate-500">Jejak siapa melakukan apa &amp; kapan.</p>

                    <ol class="mt-5">
                        @foreach ($item->events->sortBy([['created_at', 'asc'], ['id', 'asc']]) as $event)
                            @php($type = RequestEventType::from($event->event))
                            <li class="relative flex gap-3 pb-6 last:pb-0">
                                @unless ($loop->last)
                                    <span class="absolute top-9 bottom-1 left-4 w-px bg-slate-200"></span>
                                @endunless
                                <span class="relative grid size-8 shrink-0 place-items-center rounded-full ring-4 {{ $type->iconClasses() }}">
                                    <x-icon :name="$type->icon()" class="size-4" />
                                </span>
                                <div class="min-w-0 pt-1 text-sm">
                                    <p class="font-semibold text-slate-900">{{ $type->label() }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $event->actor?->name ?? 'Sistem' }} · {{ $event->created_at->translatedFormat('d M Y, H:i') }}
                                    </p>
                                    @if ($event->note && ! ($isRequester && $type === RequestEventType::Approved))
                                        <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 whitespace-pre-line text-slate-700">{{ $event->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>
        </div>
    </div>
</div>
