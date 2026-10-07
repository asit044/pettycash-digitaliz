<?php

namespace Tests\Feature;

use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5 — Finance processing & completion.
 *
 * Reused (not duplicated): queue page load, markPaid-via-form + receipt
 * validation, finance review/budget 403s, requester markPaid denial,
 * budget preservation on completion — covered by RequestPagesTest /
 * RequestVoltInteractionTest / PettyCashFlowTest / AuthorizationTest.
 * This file locks Phase 5 additions: queue scoping, completion record
 * (status/timestamps/event/notification/proof), non-processing + rejected
 * + double-completion guards, admin isolation, and proof download access.
 */
class FinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_queue_shows_only_actionable_requests(): void
    {
        $finance = User::factory()->finance()->create();
        $processing = PettyCashRequest::factory()->processing()->create();
        $done = PettyCashRequest::factory()->done()->create();
        $pending = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);
        $rejected = PettyCashRequest::factory()->rejected()->create();
        $revision = PettyCashRequest::factory()->needsRevision()->create();

        $response = $this->actingAs($finance)->get(route('finance.index'))->assertOk();

        $response->assertSee($processing->request_number);
        $response->assertDontSee($pending->request_number);
        $response->assertDontSee($rejected->request_number);
        $response->assertDontSee($revision->request_number);

        Livewire::actingAs($finance)
            ->test('pages.finance.index')
            ->set('statusFilter', RequestStatus::Done->value)
            ->assertSee($done->request_number)
            ->assertDontSee($processing->request_number);
    }

    public function test_completion_records_status_timestamps_event_and_notification(): void
    {
        $finance = User::factory()->finance()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $request = PettyCashRequest::factory()->processing()->create(['requester_id' => $requester->id]);

        app(PettyCashService::class)->markPaid(
            $finance,
            $request,
            UploadedFile::fake()->create('bukti-transfer.pdf', 100, 'application/pdf'),
        );

        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);
        $this->assertNotNull($request->paid_at);
        $this->assertNotNull($request->completed_at);

        $proof = $request->files()->where('type', RequestFileType::OfficialReceipt->value)->firstOrFail();
        $this->assertSame('bukti-transfer.pdf', $proof->original_name);

        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => 'completed',
            'actor_id' => $finance->id,
        ]);
        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_phone' => $requester->phone,
            'event' => 'completed',
        ]);
    }

    public function test_finance_cannot_complete_non_processing_or_rejected(): void
    {
        $finance = User::factory()->finance()->create();

        foreach ([
            RequestStatus::PendingReview->value,
            RequestStatus::NeedsRevision->value,
            RequestStatus::Rejected->value,
        ] as $status) {
            $request = PettyCashRequest::factory()->create(['status' => $status]);

            try {
                app(PettyCashService::class)->markPaid(
                    $finance,
                    $request,
                    UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
                );
                $this->fail("Expected ValidationException for [{$status}].");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }

            $this->assertSame($status, $request->fresh()->status);
            $this->assertSame(0, $request->files()->count());
        }
    }

    public function test_completed_request_cannot_be_completed_twice(): void
    {
        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->done()->create();
        $filesBefore = $request->files()->count();
        $eventsBefore = $request->events()->count();

        try {
            app(PettyCashService::class)->markPaid(
                $finance,
                $request,
                UploadedFile::fake()->create('lagi.pdf', 100, 'application/pdf'),
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(RequestStatus::Done->value, $request->fresh()->status);
        $this->assertSame($filesBefore, $request->files()->count());
        $this->assertSame($eventsBefore, $request->events()->count());
    }

    public function test_admin_cannot_bypass_finance_completion(): void
    {
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->processing()->create();

        try {
            app(PettyCashService::class)->markPaid(
                $admin,
                $request,
                UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            );
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(RequestStatus::Processing->value, $request->fresh()->status);
    }

    public function test_official_proof_downloadable_by_authorized_viewers(): void
    {
        $finance = User::factory()->finance()->create();
        $admin = User::factory()->admin()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $request = PettyCashRequest::factory()->processing()->create(['requester_id' => $requester->id]);

        app(PettyCashService::class)->markPaid(
            $finance,
            $request,
            UploadedFile::fake()->create('resmi.pdf', 100, 'application/pdf'),
        );

        $proof = $request->files()->where('type', RequestFileType::OfficialReceipt->value)->firstOrFail();

        foreach ([$requester, $finance, $admin] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('requests.files.download', ['id' => $request->id, 'file' => $proof->id]))
                ->assertOk()
                ->assertDownload('resmi.pdf');
        }
    }
}
