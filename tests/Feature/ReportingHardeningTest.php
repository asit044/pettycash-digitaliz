<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — Reporting, CSV & PDF hardening.
 *
 * Reused (not duplicated): admin/finance export OK, requester/head denial,
 * PDF content-type/download, CSV row presence — covered by ExportTest and
 * AuthorizationTest. This file locks filters (period/status/budget/
 * combined), invalid-input rejection, empty states, signature wiring,
 * filtered totals, leakage, and CSV/PDF filter consistency.
 */
class ReportingHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function seedRows(): array
    {
        $requester = User::factory()->requester()->create();

        $jan = PettyCashRequest::factory()->done()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-01-15 10:00:00',
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
            'nominal' => 100000,
        ]);
        $feb = PettyCashRequest::factory()->done()->create([
            'requester_id' => $requester->id,
            'submitted_at' => '2026-02-15 10:00:00',
            'budget_code' => 'MKT-001',
            'budget_description' => 'Marketing',
            'nominal' => 200000,
        ]);
        $mar = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
            'submitted_at' => '2026-03-15 10:00:00',
            'budget_code' => null,
            'budget_description' => null,
            'nominal' => 300000,
        ]);

        return [$jan, $feb, $mar];
    }

    private function csv(User $user, array $params): string
    {
        return $this->actingAs($user)
            ->get(route('exports.csv', $params))
            ->assertOk()
            ->streamedContent();
    }

    public function test_filter_by_period_status_budget_and_combined(): void
    {
        $admin = User::factory()->admin()->create();
        [$jan, $feb, $mar] = $this->seedRows();

        $febOnly = $this->csv($admin, ['from' => '2026-02-01', 'to' => '2026-02-28']);
        $this->assertStringContainsString($feb->request_number, $febOnly);
        $this->assertStringNotContainsString($jan->request_number, $febOnly);
        $this->assertStringNotContainsString($mar->request_number, $febOnly);

        $pending = $this->csv($admin, ['status' => RequestStatus::PendingReview->value]);
        $this->assertStringContainsString($mar->request_number, $pending);
        $this->assertStringNotContainsString($jan->request_number, $pending);

        $budget = $this->csv($admin, ['budget_code' => 'OPR-001']);
        $this->assertStringContainsString($jan->request_number, $budget);
        $this->assertStringNotContainsString($feb->request_number, $budget);

        $combined = $this->csv($admin, [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'status' => RequestStatus::Done->value,
            'budget_code' => 'MKT-001',
        ]);
        $this->assertStringContainsString($feb->request_number, $combined);
        $this->assertStringNotContainsString($jan->request_number, $combined);
        $this->assertStringNotContainsString($mar->request_number, $combined);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedRows();

        $this->actingAs($admin)
            ->get(route('exports.csv', ['from' => 'not-a-date']))
            ->assertRedirect()
            ->assertSessionHasErrors('from');

        $this->actingAs($admin)
            ->get(route('exports.pdf', ['from' => '2026-03-01', 'to' => '2026-01-01']))
            ->assertRedirect()
            ->assertSessionHasErrors('to');

        $this->actingAs($admin)
            ->get(route('exports.csv', ['status' => 'selesai']))
            ->assertRedirect()
            ->assertSessionHasErrors('status');
    }

    public function test_empty_result_and_csv_headers(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedRows();

        $content = $this->csv($admin, ['from' => '2020-01-01', 'to' => '2020-12-31']);

        $lines = array_values(array_filter(explode("\n", trim($content))));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Nomor', $lines[0]);
        $this->assertStringContainsString('Kode Anggaran', $lines[0]);
        $this->assertStringContainsString('Nominal', $lines[0]);
        $this->assertStringContainsString('Status', $lines[0]);

        $this->actingAs($admin)
            ->get(route('exports.pdf', ['from' => '2020-01-01', 'to' => '2020-12-31']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_template_uses_settings_total_and_empty_state(): void
    {
        Setting::set('signer_name', 'Nama Penandatangan');
        Setting::set('signer_title', 'Jabatan Uji');
        Setting::set('company_name', 'PT Uji');

        $view = $this->view('exports.rekap', [
            'rows' => collect(),
            'total' => 0,
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'signerName' => Setting::get('signer_name', ''),
            'signerTitle' => Setting::get('signer_title', ''),
            'companyName' => Setting::get('company_name', ''),
        ]);

        $view->assertSee('Rekap Pengajuan Petty Cash', false)
            ->assertSee('Tidak ada data pada periode/filter ini.', false)
            ->assertSee('Mengetahui:', false)
            ->assertSee('Nama Penandatangan', false)
            ->assertSee('Jabatan Uji', false)
            ->assertSee('PT Uji', false)
            ->assertSee('Dicetak:', false);
    }

    public function test_filtered_total_is_correct_in_template(): void
    {
        [$jan, $feb] = $this->seedRows();
        $rows = PettyCashRequest::with('requester')
            ->where('status', RequestStatus::Done->value)
            ->latest('submitted_at')
            ->get();

        $this->view('exports.rekap', [
            'rows' => $rows,
            'total' => (float) $rows->sum('nominal'),
            'from' => null,
            'to' => null,
            'signerName' => 'X',
            'signerTitle' => 'Y',
            'companyName' => 'Z',
        ])->assertSee('Rp 300.000', false);
    }

    public function test_csv_and_pdf_share_filter_semantics(): void
    {
        $admin = User::factory()->admin()->create();
        [$jan, $feb, $mar] = $this->seedRows();
        $params = ['status' => RequestStatus::Done->value];

        $csv = $this->csv($admin, $params);
        $this->assertStringContainsString($jan->request_number, $csv);
        $this->assertStringContainsString($feb->request_number, $csv);
        $this->assertStringNotContainsString($mar->request_number, $csv);

        $this->actingAs($admin)
            ->get(route('exports.pdf', $params))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();
    }

    public function test_report_does_not_leak_credentials_or_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = User::factory()->requester()->create();
        PettyCashRequest::factory()->done()->create(['requester_id' => $requester->id]);
        Setting::set('drive_root_folder_id', 'root-secret-123');

        $csv = $this->csv($admin, []);

        $this->assertStringNotContainsString($requester->password, $csv);
        $this->assertStringNotContainsString('root-secret-123', $csv);
        $this->assertStringNotContainsStringIgnoringCase('password', $csv);
        $this->assertStringNotContainsString('FONNTE_TOKEN', $csv);
        $this->assertStringNotContainsString('GOOGLE_SERVICE_ACCOUNT_JSON', $csv);
    }
}
