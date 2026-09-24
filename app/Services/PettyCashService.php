<?php

namespace App\Services;

use App\Contracts\Drive;
use App\Contracts\WhatsAppSender;
use App\Enums\RequestEventType;
use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\PettyCashRequest;
use App\Models\RequestEvent;
use App\Models\RequestFile;
use App\Models\User;
use App\Models\WaLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PettyCashService
{
    public function __construct(
        private readonly RequestNumberService $numbers,
        private readonly Drive $drive,
        private readonly WhatsAppSender $whatsapp,
    ) {}

    /**
     * @param  array<int, array{type: string, file: UploadedFile}>  $uploads
     */
    public function submit(User $requester, string $description, string $nominal, array $uploads = []): PettyCashRequest
    {
        $request = PettyCashRequest::create([
            'request_number' => $this->numbers->next(),
            'requester_id' => $requester->id,
            'nominal' => $nominal,
            'currency' => 'IDR',
            'description' => $description,
            'status' => RequestStatus::PendingReview->value,
            'submitted_at' => now(),
        ]);

        $this->attachFiles($request, $requester, $uploads);

        $this->recordEvent($request, RequestEventType::Submitted, $requester, null);

        $this->notifyRole(Role::Admin, RequestEventType::Submitted, $request,
            sprintf(
                "Pengajuan baru masuk: %s\nNominal: Rp %s\nKeperluan: %s\nStatus: menunggu validasi Admin.",
                $request->request_number,
                $this->formatNominal($request),
                $request->description,
            )
        );

        return $request;
    }

    public function resubmit(PettyCashRequest $request, User $requester, string $description): PettyCashRequest
    {
        if ($request->status() !== RequestStatus::NeedsRevision) {
            throw ValidationException::withMessages(['status' => 'Pengajuan hanya dapat diajukan ulang saat berstatus "Perlu Revisi".']);
        }

        $request->update([
            'description' => $description,
            'status' => RequestStatus::PendingReview->value,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ]);

        $this->recordEvent($request, RequestEventType::Submitted, $requester, 'Diajukan ulang setelah revisi.');

        $this->notifyRole(Role::Admin, RequestEventType::Submitted, $request,
            sprintf('Pengajuan %s diajukan ulang oleh %s setelah revisi.', $request->request_number, $requester->name)
        );

        return $request;
    }

    public function review(
        User $admin,
        PettyCashRequest $request,
        string $action,
        ?string $budgetCode = null,
        ?string $budgetDescription = null,
        ?string $reason = null,
    ): PettyCashRequest {
        if ($request->status() !== RequestStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => 'Hanya pengajuan berstatus "Menunggu Validasi" yang dapat direview.']);
        }

        match (true) {
            $action === 'approve' => $this->approve($admin, $request, $budgetCode, $budgetDescription),
            $action === 'reject' => $this->reject($admin, $request, $reason),
            $action === 'revise' => $this->revise($admin, $request, $reason),
            default => throw ValidationException::withMessages(['action' => 'Aksi tidak dikenal.']),
        };

        return $request->refresh();
    }

    public function markPaid(User $finance, PettyCashRequest $request, UploadedFile $officialReceipt): PettyCashRequest
    {
        if (! $finance->isFinance()) {
            throw ValidationException::withMessages(['role' => 'Hanya Finance yang dapat memproses pencairan.']);
        }

        if ($request->status() !== RequestStatus::Processing) {
            throw ValidationException::withMessages(['status' => 'Hanya pengajuan berstatus "Diproses Finance" yang dapat diselesaikan.']);
        }

        $this->attachFiles($request, $finance, [
            ['type' => RequestFileType::OfficialReceipt->value, 'file' => $officialReceipt],
        ]);

        $request->update([
            'status' => RequestStatus::Done->value,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);

        $this->recordEvent($request, RequestEventType::Completed, $finance, 'Pencairan selesai dan bukti transfer resmi terunggah.');

        $this->notify($request->requester, RequestEventType::Completed, $request,
            sprintf('Pengajuan %s telah SELESAI dicairkan. Cek bukti transfer pada sistem.', $request->request_number)
        );

        return $request->refresh();
    }

    private function approve(User $admin, PettyCashRequest $request, ?string $budgetCode, ?string $budgetDescription): RequestEvent
    {
        if (blank($budgetCode) || blank($budgetDescription)) {
            throw ValidationException::withMessages([
                'budget_code' => 'Kode dan uraian anggaran wajib diisi sebelum menyetujui pengajuan.',
            ]);
        }

        $request->update([
            'status' => RequestStatus::Processing->value,
            'budget_code' => $budgetCode,
            'budget_description' => $budgetDescription,
            'admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $event = $this->recordEvent($request, RequestEventType::Approved, $admin, "Kode anggaran: {$budgetCode} — {$budgetDescription}");

        $this->notifyRole(Role::Finance, RequestEventType::Approved, $request,
            sprintf(
                "Pengajuan %s telah DISETUJUI Admin.\nNominal: Rp %s\nKode anggaran: %s\nSilakan proses pencairan.",
                $request->request_number,
                $this->formatNominal($request),
                $budgetCode,
            )
        );

        return $event;
    }

    private function reject(User $admin, PettyCashRequest $request, ?string $reason): RequestEvent
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan wajib diisi.']);
        }

        $request->update([
            'status' => RequestStatus::Rejected->value,
            'admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $event = $this->recordEvent($request, RequestEventType::Rejected, $admin, $reason);

        $this->notify($request->requester, RequestEventType::Rejected, $request,
            sprintf("Pengajuan %s DITOLAK.\nAlasan: %s", $request->request_number, $reason)
        );

        return $event;
    }

    private function revise(User $admin, PettyCashRequest $request, ?string $reason): RequestEvent
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan revisi wajib diisi.']);
        }

        $request->update([
            'status' => RequestStatus::NeedsRevision->value,
            'admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $event = $this->recordEvent($request, RequestEventType::NeedsRevision, $admin, $reason);

        $this->notify($request->requester, RequestEventType::NeedsRevision, $request,
            sprintf("Pengajuan %s diminta REVISI.\nAlasan: %s", $request->request_number, $reason)
        );

        return $event;
    }

    /**
     * @param  array<int, array{type: string, file: UploadedFile}>  $uploads
     */
    private function attachFiles(PettyCashRequest $request, User $uploader, array $uploads): void
    {
        if ($this->drive->isConfigured()) {
            $folder = $this->drive->ensureRequestFolder($request->request_number, $uploader->name);
            $request->update([
                'drive_folder_id' => $folder['id'],
                'drive_folder_url' => $folder['url'],
            ]);
        }

        foreach ($uploads as $upload) {
            if (! isset($upload['file']) || ! is_a($upload['file'], UploadedFile::class)) {
                continue;
            }

            $file = $upload['file'];
            $type = $upload['type'];

            $originalName = $file->getClientOriginalName();
            $driverMeta = ['id' => null, 'url' => null];

            if ($this->drive->isConfigured() && filled($request->drive_folder_id)) {
                $driverMeta = $this->drive->uploadFile(
                    $request->drive_folder_id,
                    $file,
                    sprintf('%s-%s', $type, $originalName),
                );
            }

            $storedPath = Storage::disk('local')->putFile('requests/'.$request->request_number, $file);

            RequestFile::create([
                'request_id' => $request->id,
                'type' => $type,
                'original_name' => $originalName,
                'storage_path' => $storedPath,
                'drive_file_id' => $driverMeta['id'],
                'drive_url' => $driverMeta['url'],
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    private function recordEvent(
        PettyCashRequest $request,
        RequestEventType $event,
        User $actor,
        ?string $note,
    ): RequestEvent {
        return RequestEvent::create([
            'request_id' => $request->id,
            'event' => $event->value,
            'actor_id' => $actor->id,
            'actor_role' => $actor->role,
            'note' => $note,
        ]);
    }

    private function notify(User $recipient, RequestEventType $event, PettyCashRequest $request, string $message): void
    {
        if (blank($recipient->phone)) {
            return;
        }

        $result = $this->whatsapp->send($recipient->phone, $message);

        WaLog::create([
            'request_id' => $request->id,
            'recipient_phone' => $recipient->phone,
            'recipient_role' => $recipient->role,
            'event' => $event->value,
            'message' => $message,
            'status' => $result['error'] !== null ? 'failed' : 'sent',
            ...$result,
        ]);
    }

    private function notifyRole(
        Role $role,
        RequestEventType $event,
        PettyCashRequest $request,
        string $message,
    ): void {
        User::query()
            ->where('role', $role->value)
            ->whereNotNull('phone')
            ->get()
            ->each(fn (User $user) => $this->notify($user, $event, $request, $message));
    }

    private function formatNominal(PettyCashRequest $request): string
    {
        return number_format((float) $request->nominal, 0, ',', '.');
    }
}
