<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 10 — Head monitoring dashboard (read-only).
 *
 * Write-denial coverage is reused from AuthorizationTest / RequestPagesTest
 * / ExportTest (create/review/process/settings/export 403s); this file locks
 * dashboard access, summary accuracy, period filtering, and Head-specific
 * mutation denials on the detail page.
 */
class HeadDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_can_open_dashboard_with_summary(): void
    {
        $head = User::factory()->head()->create();
        $request = PettyCashRequest::factory()->done()->create([
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);

        $this->actingAs($head)
            ->get(route('head.index'))
            ->assertOk()
            ->assertSee('Monitoring Petty Cash', false)
            ->assertSee('Ringkasan per Status', false)
            ->assertSee('Ringkasan per Kode Anggaran', false)
            ->assertSee('Pengajuan Terbaru', false)
            ->assertSee($request->request_number)
            ->assertSee('OPR-001');

        $this->actingAs(User::factory()->requester()->create())
            ->get(route('head.index'))
            ->assertForbidden();
    }

    public function test_dashboard_totals_match_filtered_dataset(): void
    {
        $head = User::factory()->head()->create();
        $requester = User::factory()->requester()->create();

        PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
            'submitted_at' => '2026-02-05 10:00:00',
            'nominal' => 100000,
        ]);
        PettyCashRequest::factory()->done()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-02-06 10:00:00',
            'nominal' => 200000,
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);
        PettyCashRequest::factory()->done()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-05-06 10:00:00',
            'nominal' => 400000,
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);

        $component = Livewire::actingAs($head)
            ->test('pages.head.index')
            ->set('from', '2026-01-01')
            ->set('to', '2026-12-31');

        $this->assertSame(3, $component->get('totalCount'));
        $this->assertSame(700000.0, $component->get('totalNominal'));

        $breakdown = collect($component->get('statusBreakdown'));
        $this->assertSame(3, $breakdown->sum('count'));
        $this->assertSame(700000.0, (float) $breakdown->sum('nominal'));
        $this->assertSame(1, $breakdown->firstWhere('value', RequestStatus::PendingReview->value)['count']);
        $this->assertSame(2, $breakdown->firstWhere('value', RequestStatus::Done->value)['count']);
        $this->assertSame(0, $breakdown->firstWhere('value', RequestStatus::Rejected->value)['count']);
    }

    public function test_dashboard_period_filter_and_invalid_range(): void
    {
        $head = User::factory()->head()->create();
        $requester = User::factory()->requester()->create();

        $feb = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-02-05 10:00:00',
        ]);
        $may = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-05-05 10:00:00',
        ]);

        $component = Livewire::actingAs($head)
            ->test('pages.head.index')
            ->set('from', '2026-02-01')
            ->set('to', '2026-02-28');

        $this->assertSame(1, $component->get('totalCount'));
        $this->assertTrue(
            $component->get('recentRequests')->pluck('id')->contains($feb->id)
        );
        $this->assertFalse(
            $component->get('recentRequests')->pluck('id')->contains($may->id)
        );

        Livewire::actingAs($head)
            ->test('pages.head.index')
            ->set('from', '2026-05-01')
            ->set('to', '2026-02-01')
            ->assertHasErrors(['to']);

        $this->actingAs($head)
            ->get(route('head.index'))
            ->assertOk();
    }

    public function test_head_cannot_mutate_via_detail_page(): void
    {
        $head = User::factory()->head()->create();
        $pending = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);
        $processing = PettyCashRequest::factory()->processing()->create();

        Livewire::actingAs($head)
            ->test('pages.requests.show', ['id' => $pending->id])
            ->call('review', 'approve')
            ->assertForbidden();

        Livewire::actingAs($head)
            ->test('pages.requests.show', ['id' => $processing->id])
            ->set('officialReceipt', UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'))
            ->call('markPaid')
            ->assertForbidden();

        $this->actingAs($head)->get(route('requests.create'))->assertForbidden();
    }
}
