<?php

namespace Tests\Feature;

use App\Enums\RequestEventType;
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

class PettyCashFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
    }

    public function test_requester_can_submit_a_request(): void
    {
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);

        $request = app(PettyCashService::class)->submit(
            $requester,
            'Beli pulsa meeting',
            '150000',
            [
                ['type' => RequestFileType::Invoice->value, 'file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
            ],
        );

        $this->assertSame('KC-'.now()->format('Y').'-0001', $request->request_number);
        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertCount(1, $request->files);
        $this->assertSame('invoice.pdf', $request->files->first()->original_name);

        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => RequestEventType::Submitted->value,
            'actor_id' => $requester->id,
        ]);

        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_role' => 'admin',
            'recipient_phone' => $admin->phone,
        ]);
    }

    public function test_admin_cannot_approve_without_budget_code(): void
    {
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->create();

        try {
            app(PettyCashService::class)->review($admin, $request, 'approve');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('budget_code', $e->errors());
        }

        $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);
    }

    public function test_admin_approve_sets_processing_budget_code_and_notifies_finance(): void
    {
        $finance = User::factory()->finance()->create(['phone' => '6283333333333']);
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        app(PettyCashService::class)->review($admin, $request, 'approve', 'OPR-001', 'Operasional');

        $request->refresh();

        $this->assertSame(RequestStatus::Processing->value, $request->status);
        $this->assertSame('OPR-001', $request->budget_code);
        $this->assertSame('Operasional', $request->budget_description);
        $this->assertSame($admin->id, $request->admin_id);

        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_role' => 'finance',
            'recipient_phone' => $finance->phone,
        ]);
    }

    public function test_admin_revise_requires_reason_and_returns_to_requester(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $request = PettyCashRequest::factory()->create([
            'status' => RequestStatus::PendingReview->value,
            'requester_id' => $requester->id,
        ]);

        try {
            app(PettyCashService::class)->review($admin, $request, 'revise');
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
        }

        app(PettyCashService::class)->review($admin, $request, 'revise', reason: 'Lampiran kurang jelas');

        $request->refresh();

        $this->assertSame(RequestStatus::NeedsRevision->value, $request->status);

        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_phone' => $requester->phone,
            'event' => RequestEventType::NeedsRevision->value,
        ]);
    }

    public function test_requester_can_resubmit_revised_request(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create([
            'requester_id' => $requester->id,
        ]);

        app(PettyCashService::class)->resubmit($request, $requester, 'Keperluan diperbaiki');

        $request->refresh();

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
    }

    public function test_finance_cannot_complete_without_official_receipt(): void
    {
        $finance = User::factory()->finance()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $request = PettyCashRequest::factory()->processing()->create([
            'requester_id' => $requester->id,
        ]);

        app(PettyCashService::class)->markPaid(
            $finance,
            $request,
            UploadedFile::fake()->create('bukti-transfer.pdf', 100, 'application/pdf'),
        );

        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);
        $this->assertSame('bukti-transfer.pdf', $request->files()->first()->original_name);
        $this->assertSame(RequestFileType::OfficialReceipt->value, $request->files()->first()->type);

        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'recipient_phone' => $requester->phone,
            'event' => RequestEventType::Completed->value,
        ]);
    }

    public function test_non_finance_cannot_mark_paid(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->processing()->create([
            'requester_id' => $requester->id,
        ]);

        try {
            app(PettyCashService::class)->markPaid(
                $requester,
                $request,
                UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(RequestStatus::Processing->value, $request->fresh()->status);
    }

    public function test_staff_cannot_view_another_request_detail(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();

        $request = PettyCashRequest::factory()->create(['requester_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee($request->request_number);
    }

    public function test_requester_never_sees_budget_code(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->processing()->create([
            'requester_id' => $requester->id,
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);

        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertDontSee('OPR-001')
            ->assertDontSee('Operasional');
    }
}
