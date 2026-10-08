<?php

namespace Tests\Feature;

use App\Contracts\Drive;
use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\Drive\GoogleDriveService;
use App\Services\Drive\NullDriveService;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 7 — Google Drive integration hardening.
 *
 * Uses contract fakes only; never requires real Google credentials.
 * Drive is best-effort mirroring over the canonical local workflow:
 * request_files is always written locally, drive_* metadata only when a
 * Drive upload verifiably succeeded.
 */
class RecordingFakeDrive implements Drive
{
    public array $folders = [];

    public array $uploads = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function ensureRequestFolder(string $requestNumber, string $requesterName): array
    {
        $this->folders[] = [$requestNumber, $requesterName];

        return [
            'id' => 'folder-'.$requestNumber,
            'url' => 'https://drive.example.test/folders/'.$requestNumber,
        ];
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array
    {
        $this->uploads[] = [$folderId, $fileName];

        return [
            'id' => 'file-'.$fileName,
            'url' => 'https://drive.example.test/file/'.$fileName,
        ];
    }
}

class ThrowingFakeDrive implements Drive
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function ensureRequestFolder(string $requestNumber, string $requesterName): array
    {
        throw new \RuntimeException('Drive API unavailable.');
    }

    public function uploadFile(string $folderId, UploadedFile $file, string $fileName): array
    {
        throw new \RuntimeException('Drive API unavailable.');
    }
}

class DriveHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_environment_resolves_null_drive(): void
    {
        $this->assertEmpty(config('services.google.service_account_json'));
        $this->assertInstanceOf(NullDriveService::class, app(Drive::class));
        $this->assertFalse(app(Drive::class)->isConfigured());
    }

    public function test_google_drive_service_constructs_without_database_or_credentials(): void
    {
        $service = new GoogleDriveService;

        $this->assertFalse($service->isConfigured());
    }

    public function test_request_folder_uses_request_number_and_metadata_is_stored(): void
    {
        $fake = new RecordingFakeDrive;
        $this->app->bind(Drive::class, fn () => $fake);

        $requester = User::factory()->requester()->create(['name' => 'Budi Santoso']);

        $request = app(PettyCashService::class)->submit(
            $requester, 'Keperluan drive', '300000',
            [
                ['type' => RequestFileType::Invoice->value, 'file' => UploadedFile::fake()->create('inv.pdf', 100, 'application/pdf')],
                ['type' => RequestFileType::ProofTransfer->value, 'file' => UploadedFile::fake()->create('tf.jpg', 100, 'image/jpeg')],
            ],
        );

        $this->assertSame([[$request->request_number, 'Budi Santoso']], $fake->folders);
        $this->assertSame('folder-'.$request->request_number, $request->drive_folder_id);
        $this->assertStringContainsString($request->request_number, $request->drive_folder_url);

        $files = $request->files()->orderBy('id')->get();
        $this->assertCount(2, $files);
        $this->assertSame('folder-'.$request->request_number, $fake->uploads[0][0]);
        $this->assertSame($request->request_number.'_invoice_inv.pdf', $fake->uploads[0][1]);
        $this->assertSame($request->request_number.'_proof_transfer_tf.jpg', $fake->uploads[1][1]);

        foreach ($files as $file) {
            $this->assertNotNull($file->drive_file_id);
            $this->assertNotNull($file->drive_url);
            $this->assertTrue(Storage::disk('local')->exists($file->storage_path));
        }
    }

    public function test_official_receipt_is_mirrored_to_drive(): void
    {
        $fake = new RecordingFakeDrive;
        $this->app->bind(Drive::class, fn () => $fake);

        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->processing()->create();

        app(PettyCashService::class)->markPaid(
            $finance, $request,
            UploadedFile::fake()->create('resmi.pdf', 100, 'application/pdf'),
        );

        $proof = $request->files()->where('type', RequestFileType::OfficialReceipt->value)->firstOrFail();

        $this->assertSame('file-'.$request->request_number.'_official_receipt_resmi.pdf', $proof->drive_file_id);
        $this->assertNotNull($proof->drive_url);
        $this->assertSame(RequestStatus::Done->value, $request->fresh()->status);
    }

    public function test_drive_failure_does_not_corrupt_request_workflow(): void
    {
        $this->app->bind(Drive::class, fn () => new ThrowingFakeDrive);

        $requester = User::factory()->requester()->create();
        $finance = User::factory()->finance()->create();

        $request = app(PettyCashService::class)->submit(
            $requester, 'Tetap tersimpan', '150000',
            [['type' => RequestFileType::Invoice->value, 'file' => UploadedFile::fake()->create('i.pdf', 100, 'application/pdf')]],
        );

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertNull($request->drive_folder_id);
        $this->assertCount(1, $request->files);
        $this->assertNull($request->files()->first()->drive_file_id);
        $this->assertTrue(Storage::disk('local')->exists($request->files()->first()->storage_path));
        $this->assertDatabaseHas('request_events', ['request_id' => $request->id, 'event' => 'submitted']);

        $processing = PettyCashRequest::factory()->processing()->create();

        app(PettyCashService::class)->markPaid(
            $finance, $processing,
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        );

        $processing->refresh();

        $this->assertSame(RequestStatus::Done->value, $processing->status);
        $this->assertNotNull($processing->completed_at);
        $this->assertTrue($processing->hasOfficialReceipt());
    }

    public function test_null_drive_keeps_local_workflow_functional(): void
    {
        $this->app->bind(Drive::class, fn () => new NullDriveService);

        $requester = User::factory()->requester()->create();

        $request = app(PettyCashService::class)->submit(
            $requester, 'Lokal saja', '90000',
            [['type' => RequestFileType::Invoice->value, 'file' => UploadedFile::fake()->create('l.pdf', 100, 'application/pdf')]],
        );

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertTrue(Storage::disk('local')->exists($request->files()->first()->storage_path));
    }

    public function test_drive_backed_file_remains_owner_scoped(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->create(['requester_id' => $owner->id]);
        $file = $request->files()->create([
            'type' => RequestFileType::Invoice->value,
            'original_name' => 'drive.pdf',
            'storage_path' => 'requests/drive.pdf',
            'drive_file_id' => 'drive-123',
            'drive_url' => 'https://drive.example.test/file/drive-123',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('requests.files.download', ['id' => $request->id, 'file' => $file->id]))
            ->assertForbidden();
    }
}
