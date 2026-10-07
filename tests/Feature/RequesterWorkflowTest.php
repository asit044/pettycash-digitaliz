<?php

namespace Tests\Feature;

use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3 — Requester workflow.
 *
 * Reused (not duplicated here): create-via-form, invalid nominal guard,
 * submit event/WA, own/other view scoping, budget-hidden presentation,
 * admin-action 403s, basic resubmit, resubmit ownership — covered by
 * PettyCashFlowTest / RequestVoltInteractionTest / RequestPagesTest /
 * AuthorizationTest. This file locks Phase 3 additions: service-level
 * validation, initial proof upload, revision file uploads (additive),
 * resubmit event + status guards, and budget/nominal immutability.
 */
class RequesterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_rejects_invalid_amount_and_description(): void
    {
        $requester = User::factory()->requester()->create();

        foreach (['0', '-5', 'abc', ''] as $badNominal) {
            try {
                app(PettyCashService::class)->submit($requester, 'Keperluan valid', $badNominal);
                $this->fail("Expected ValidationException for nominal [{$badNominal}].");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('nominal', $e->errors());
            }
        }

        try {
            app(PettyCashService::class)->submit($requester, '', '150000');
            $this->fail('Expected ValidationException for empty description.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('description', $e->errors());
        }

        try {
            app(PettyCashService::class)->submit($requester, str_repeat('a', 2001), '150000');
            $this->fail('Expected ValidationException for overlong description.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('description', $e->errors());
        }

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_requester_can_upload_invoice_and_initial_proof(): void
    {
        $requester = User::factory()->requester()->create();

        Livewire::actingAs($requester)
            ->test('pages.requests.create')
            ->set('description', 'Beli ATK')
            ->set('nominal', '250000')
            ->set('invoice', UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'))
            ->set('proofTransfer', UploadedFile::fake()->create('transfer.jpg', 100, 'image/jpeg'))
            ->call('submit')
            ->assertHasNoErrors();

        $request = PettyCashRequest::query()->where('description', 'Beli ATK')->firstOrFail();

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertNotNull($request->submitted_at);
        $this->assertCount(2, $request->files);
        $this->assertSame(
            [RequestFileType::Invoice->value, RequestFileType::ProofTransfer->value],
            $request->files()->orderBy('id')->pluck('type')->all()
        );
    }

    public function test_requester_can_access_needs_revision_request(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create(['requester_id' => $requester->id]);

        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee('Ajukan Ulang', false);
    }

    public function test_requester_cannot_resubmit_non_needs_revision_request(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
        ]);

        try {
            app(PettyCashService::class)->resubmit($request, $requester, 'Belum waktunya');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);
    }

    public function test_resubmit_creates_request_event_and_returns_to_pending(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create(['requester_id' => $requester->id]);

        app(PettyCashService::class)->resubmit($request, $requester, 'Sudah diperbaiki');

        $request->refresh();

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertNotNull($request->submitted_at);
        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => 'submitted',
            'actor_id' => $requester->id,
        ]);
    }

    public function test_requester_can_upload_supporting_file_during_revision(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create(['requester_id' => $requester->id]);
        $oldFile = $request->files()->create([
            'type' => RequestFileType::Invoice->value,
            'original_name' => 'lama.pdf',
            'storage_path' => 'requests/lama.pdf',
            'uploaded_by' => $requester->id,
        ]);

        Livewire::actingAs($requester)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('editDescription', 'Keperluan diperbaiki')
            ->set('revisionInvoice', UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'))
            ->call('resubmit')
            ->assertHasNoErrors();

        $request->refresh();

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertSame('Keperluan diperbaiki', $request->description);
        $this->assertCount(2, $request->files);
        $this->assertTrue($request->files()->whereKey($oldFile->id)->exists());
        $this->assertSame('baru.pdf', $request->files()->latest('id')->first()->original_name);
        $this->assertSame(RequestFileType::Invoice->value, $request->files()->latest('id')->first()->type);
    }

    public function test_requester_cannot_modify_budget_or_nominal_during_revision(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create([
            'requester_id' => $requester->id,
            'nominal' => 500000,
        ]);

        app(PettyCashService::class)->resubmit($request, $requester, 'Hanya deskripsi berubah');

        $request->refresh();

        $this->assertSame('500000.00', (string) $request->nominal);
        $this->assertNull($request->budget_code);
        $this->assertNull($request->budget_description);
    }
}
