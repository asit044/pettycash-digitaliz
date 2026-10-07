<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4 — Admin validation & approval workflow.
 *
 * Reused (not duplicated): assign/approve-with-budget, approve-without-
 * budget, approve→processing+event+finance-notify, revise+reason→
 * needs_revision, reject→rejected+event, requester/finance 403s — covered
 * by PettyCashFlowTest / RequestVoltInteractionTest / AuthorizationTest.
 * This file locks Phase 4 additions: list/detail, budget-code validity,
 * reason-required negatives, rejected isolation, invalid transitions, and
 * review side-effect boundaries (ownership/files untouched).
 */
class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_shows_all_pending_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $a = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);
        $b = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee($a->request_number)
            ->assertSee($b->request_number)
            ->assertSee($a->requester->name)
            ->assertSee('Review', false);
    }

    public function test_admin_detail_shows_review_form_for_pending(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        $this->actingAs($admin)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee($request->request_number)
            ->assertSee($request->requester->name)
            ->assertSee('Validasi', false);
    }

    public function test_approve_rejects_unknown_or_inactive_budget_code(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OFF-001', 'description' => 'Nonaktif', 'is_active' => false]);

        foreach ([
            ['NOPE-001', 'Tidak ada'],
            ['OFF-001', 'Nonaktif'],
        ] as [$code, $desc]) {
            $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

            try {
                app(PettyCashService::class)->review($admin, $request, 'approve', $code, $desc);
                $this->fail("Expected ValidationException for budget [{$code}].");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('budget_code', $e->errors());
            }

            $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);
        }

        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        Livewire::actingAs($admin)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('budgetCode', 'NOPE-001')
            ->set('budgetDescription', 'Tidak ada')
            ->call('review', 'approve')
            ->assertHasErrors(['budgetCode']);
    }

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        try {
            app(PettyCashService::class)->review($admin, $request, 'reject');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);
    }

    public function test_rejected_request_cannot_enter_processing(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        app(PettyCashService::class)->review($admin, $request, 'reject', reason: 'Ditolak');
        $this->assertSame(RequestStatus::Rejected->value, $request->fresh()->status);

        foreach (['approve', 'revise', 'reject'] as $action) {
            try {
                app(PettyCashService::class)->review(
                    $admin, $request->fresh(), $action, 'OPR-001', 'Operasional', 'Alasan'
                );
                $this->fail("Expected ValidationException for [{$action}] on rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('status', $e->errors());
            }
        }

        try {
            app(PettyCashService::class)->markPaid(
                $finance, $request->fresh(),
                UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            );
            $this->fail('Expected ValidationException for markPaid on rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(RequestStatus::Rejected->value, $request->fresh()->status);
    }

    public function test_invalid_transitions_on_processing_and_done_are_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);

        foreach ([RequestStatus::Processing->value, RequestStatus::Done->value] as $status) {
            $request = PettyCashRequest::factory()->create(['status' => $status]);

            foreach (['approve', 'revise', 'reject'] as $action) {
                try {
                    app(PettyCashService::class)->review(
                        $admin, $request, $action, 'OPR-001', 'Operasional', 'Alasan'
                    );
                    $this->fail("Expected ValidationException for [{$action}] on [{$status}].");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('status', $e->errors());
                }
            }

            $this->assertSame($status, $request->fresh()->status);
        }
    }

    public function test_review_preserves_ownership_files_and_notifies_requester(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
        ]);
        $request->files()->create([
            'type' => 'invoice',
            'original_name' => 'nota.pdf',
            'storage_path' => 'requests/nota.pdf',
            'uploaded_by' => $requester->id,
        ]);

        app(PettyCashService::class)->review($admin, $request, 'revise', reason: 'Invoice buram');

        $request->refresh();

        $this->assertSame((int) $requester->id, (int) $request->requester_id);
        $this->assertCount(1, $request->files);
        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => 'needs_revision',
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_phone' => $requester->phone,
            'event' => 'needs_revision',
        ]);
    }
}
