<?php

namespace Tests\Feature;

use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 6 — Tracking, timeline & audit.
 *
 * Reused (not duplicated): per-transition event creation, actor/note
 * storage, owner/admin/finance timeline access control, budget hiding —
 * covered by PettyCashFlowTest / AuthorizationTest / AdminWorkflowTest /
 * FinanceWorkflowTest / RequesterWorkflowTest. This file locks the
 * full-lifecycle chain, chronological order, failure atomicity (no
 * misleading events), and post-completion archive completeness.
 */
class TrackingAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_events_are_chronological(): void
    {
        $requester = User::factory()->requester()->create();
        $admin = User::factory()->admin()->create(['name' => 'Admin Digitaliz']);
        $finance = User::factory()->finance()->create(['name' => 'Finance Digitaliz']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'Keperluan', '100000');
        $service->review($admin, $request, 'revise', reason: 'Invoice buram');
        $service->resubmit($request->fresh(), $requester, 'Diperbaiki');
        $service->review($admin, $request->fresh(), 'approve', 'OPR-001', 'Operasional');
        $service->markPaid(
            $finance, $request->fresh(),
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        );

        $events = $request->events()->orderBy('id')->get();

        $this->assertSame(
            ['submitted', 'needs_revision', 'submitted', 'approved', 'completed'],
            $events->pluck('event')->all()
        );
        $this->assertSame(
            [$requester->id, $admin->id, $requester->id, $admin->id, $finance->id],
            $events->pluck('actor_id')->all()
        );
        $this->assertSame(RequestStatus::Done->value, $request->fresh()->status);
    }

    public function test_timeline_visible_to_owner_admin_and_finance(): void
    {
        $requester = User::factory()->requester()->create(['name' => 'Andi Requester']);
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'Keperluan timeline', '200000');

        $service->review($admin, $request->fresh(), 'approve', 'OPR-001', 'Operasional');

        foreach ([$requester, $admin, $finance] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('requests.show', ['id' => $request->id]))
                ->assertOk()
                ->assertSee('Linimasa Status', false)
                ->assertSee('Pengajuan dibuat', false)
                ->assertSee('Disetujui Admin', false)
                ->assertSee('Andi Requester', false);
        }
    }

    public function test_failed_transition_creates_no_event(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->done()->create();
        $eventsBefore = $request->events()->count();

        try {
            app(PettyCashService::class)->review($admin, $request, 'approve', 'OPR-001', 'Operasional');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(RequestStatus::Done->value, $request->fresh()->status);
        $this->assertSame($eventsBefore, $request->events()->count());
    }

    public function test_approval_event_preserves_actor_role_and_budget_note(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        app(PettyCashService::class)->review($admin, $request, 'approve', 'OPR-001', 'Operasional');

        $event = $request->events()->where('event', 'approved')->firstOrFail();

        $this->assertSame((int) $admin->id, (int) $event->actor_id);
        $this->assertSame('admin', $event->actor_role);
        $this->assertStringContainsString('OPR-001', $event->note);
        $this->assertStringContainsString('Operasional', $event->note);
        $this->assertNotNull($event->created_at);
    }

    public function test_completed_request_retains_full_archive(): void
    {
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit(
            $requester, 'Arsip penting', '750000',
            [['type' => RequestFileType::Invoice->value, 'file' => UploadedFile::fake()->create('n.pdf', 100, 'application/pdf')]],
        );
        $service->review($admin, $request->fresh(), 'approve', 'OPR-001', 'Operasional');
        $service->markPaid(
            $finance, $request->fresh(),
            UploadedFile::fake()->create('t.pdf', 100, 'application/pdf'),
        );

        $archived = PettyCashRequest::with(['requester', 'files', 'events'])->findOrFail($request->id);

        $this->assertNotEmpty($archived->request_number);
        $this->assertSame('Arsip penting', $archived->description);
        $this->assertSame('750000.00', (string) $archived->nominal);
        $this->assertSame('OPR-001', $archived->budget_code);
        $this->assertNotNull($archived->submitted_at);
        $this->assertNotNull($archived->reviewed_at);
        $this->assertNotNull($archived->completed_at);
        $this->assertSame(2, $archived->files()->count());
        $this->assertSame(3, $archived->events()->count());
        $this->assertSame(1, $archived->files()->where('type', RequestFileType::OfficialReceipt->value)->count());
        $this->assertDatabaseHas('wa_logs', ['request_id' => $request->id, 'event' => 'completed']);
    }
}
