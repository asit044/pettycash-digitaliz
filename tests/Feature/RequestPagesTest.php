<?php

namespace Tests\Feature;

use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequestPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('requests.index'))->assertRedirectToRoute('login');
        $this->get(route('admin.index'))->assertRedirectToRoute('login');
    }

    public function test_requester_pages_load(): void
    {
        $requester = User::factory()->requester()->create();
        PettyCashRequest::factory()->count(3)->create(['requester_id' => $requester->id]);

        $this->actingAs($requester)
            ->get(route('requests.index'))
            ->assertOk()
            ->assertSee('Pengajuan Saya');

        $this->actingAs($requester)
            ->get(route('requests.create'))
            ->assertOk()
            ->assertSee('Keperluan');
    }

    public function test_requester_cannot_access_admin_finance_settings(): void
    {
        $requester = User::factory()->requester()->create();

        $this->actingAs($requester)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($requester)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($requester)->get(route('settings.index'))->assertForbidden();
    }

    public function test_requester_cannot_create_own_request_via_unfiltered_page(): void
    {
        $requester = User::factory()->requester()->create();

        $this->actingAs($requester)->get(route('admin.index'))->assertStatus(403);
    }

    public function test_admin_pages_load(): void
    {
        $admin = User::factory()->admin()->create();
        PettyCashRequest::factory()->count(2)->create(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Validasi Pengajuan');

        $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Kode Anggaran');
    }

    public function test_finance_page_loads(): void
    {
        $finance = User::factory()->finance()->create();
        PettyCashRequest::factory()->processing()->create();

        $this->actingAs($finance)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Pencairan Finance');
    }

    public function test_reports_page_access(): void
    {
        $finance = User::factory()->finance()->create();
        PettyCashRequest::factory()->done()->create();

        $this->actingAs($finance)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan');

        $requester = User::factory()->requester()->create();
        $this->actingAs($requester)->get(route('reports.index'))->assertForbidden();
    }

    public function test_head_can_view_reports_but_not_admin_tools(): void
    {
        $head = User::factory()->head()->create();

        $this->actingAs($head)->get(route('reports.index'))->assertOk();
        $this->actingAs($head)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($head)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($head)->get(route('settings.index'))->assertForbidden();
    }

    public function test_settings_page_manages_budget_codes(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'EXIST-01']);

        Livewire::actingAs($admin)
            ->test('pages.settings.index')
            ->set('newCode', 'NEW-01')
            ->set('newDescription', 'Baru')
            ->call('addBudgetCode')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('budget_codes', ['code' => 'NEW-01']);
    }
}
