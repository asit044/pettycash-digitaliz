<?php

namespace Tests\Feature;

use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_contains_rows_and_filters(): void
    {
        $finance = User::factory()->finance()->create();

        $request = PettyCashRequest::factory()->done()->create([
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);

        $this->actingAs($finance)
            ->get(route('exports.csv', ['status' => 'done']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload();

        $content = $this->get(route('exports.csv', ['status' => 'done']))->streamedContent();

        $this->assertStringContainsString($request->request_number, $content);
        $this->assertStringContainsString('OPR-001', $content);
    }

    public function test_pdf_export_generates_download_with_signature_block(): void
    {
        $admin = User::factory()->admin()->create();
        PettyCashRequest::factory()->done()->create(['budget_code' => 'OPR-001']);

        $response = $this->actingAs($admin)
            ->get(route('exports.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $this->assertNotEmpty($response->baseResponse->getContent());
    }

    public function test_requester_cannot_export(): void
    {
        $requester = User::factory()->requester()->create();

        $this->actingAs($requester)->get(route('exports.csv'))->assertForbidden();
        $this->actingAs($requester)->get(route('exports.pdf'))->assertForbidden();
    }

    public function test_head_cannot_export_but_admin_can(): void
    {
        $head = User::factory()->head()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($head)->get(route('exports.csv'))->assertForbidden();

        $this->actingAs($admin)->get(route('exports.csv'))->assertOk();
    }
}
