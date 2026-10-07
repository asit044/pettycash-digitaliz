<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\User;
use App\Models\WaLog;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 11 — Final end-to-end QA.
 *
 * Walks the complete requester → admin → finance lifecycles through the
 * real HTTP + service stack (credential-less integrations fall back
 * gracefully). Step-level behavior is locked by phase suites; this file
 * proves the phases work together without regression.
 */
class EndToEndQATest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_lifecycle_end_to_end(): void
    {
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);
        $finance = User::factory()->finance()->create(['phone' => '6283333333333']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit(
            $requester, 'E2E normal', '500000',
            [
                ['type' => 'invoice', 'file' => UploadedFile::fake()->create('inv.pdf', 100, 'application/pdf')],
                ['type' => 'proof_transfer', 'file' => UploadedFile::fake()->create('tf.pdf', 100, 'application/pdf')],
            ],
        );

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertMatchesRegularExpression('/^KC-\d{4}-\d{4}$/', $request->request_number);

        $service->review($admin, $request->fresh(), 'approve', 'OPR-001', 'Operasional');
        $this->assertSame(RequestStatus::Processing->value, $request->fresh()->status);

        $service->markPaid(
            $finance, $request->fresh(),
            UploadedFile::fake()->create('resmi.pdf', 100, 'application/pdf'),
        );
        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);
        $this->assertNotNull($request->completed_at);

        $this->assertSame(
            ['submitted', 'approved', 'completed'],
            $request->events()->orderBy('id')->pluck('event')->all()
        );
        $this->assertSame(
            ['admin', 'finance', 'requester'],
            WaLog::query()->where('request_id', $request->id)->orderBy('id')->pluck('recipient_role')->all()
        );

        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee($request->request_number)
            ->assertSee('Selesai', false);

        $csv = $this->actingAs($admin)->get(route('exports.csv', []))->assertOk()->streamedContent();
        $this->assertStringContainsString($request->request_number, $csv);
    }

    public function test_revision_lifecycle_end_to_end(): void
    {
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'E2E revisi', '250000');
        $service->review($admin, $request->fresh(), 'revise', reason: 'Invoice buram');

        $this->assertSame(RequestStatus::NeedsRevision->value, $request->fresh()->status);

        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee('Invoice buram', false);

        $service->resubmit(
            $request->fresh(), $requester, 'Sudah diperbaiki',
            [['type' => 'invoice', 'file' => UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf')]],
        );

        $request->refresh();

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertSame('250000.00', (string) $request->nominal);
        $this->assertNull($request->budget_code);
        $this->assertSame(1, $request->files()->count());
        $this->assertSame(
            ['submitted', 'needs_revision', 'submitted'],
            $request->events()->orderBy('id')->pluck('event')->all()
        );
    }

    public function test_rejection_lifecycle_end_to_end(): void
    {
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);
        $finance = User::factory()->finance()->create();
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'E2E tolak', '100000');
        $service->review($admin, $request->fresh(), 'reject', reason: 'Di luar kebijakan');

        $request->refresh();

        $this->assertSame(RequestStatus::Rejected->value, $request->status);
        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'event' => 'rejected',
            'recipient_phone' => $requester->phone,
        ]);

        $this->actingAs($finance)->get(route('finance.index'))->assertOk();
        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee('Di luar kebijakan', false)
            ->assertSee('Ditolak', false);
    }
}
