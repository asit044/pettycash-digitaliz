<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Services\PettyCashService;
use App\Support\Phone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Covers behaviour added with the UI/UX revamp: role dashboards, user
 * management, WhatsApp number handling, Drive naming and report filters.
 */
class UiRevampTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_every_role(): void
    {
        PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        foreach (['requester', 'admin', 'finance', 'head'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Status Pengajuan');
        }
    }

    public function test_requester_dashboard_only_counts_own_requests(): void
    {
        $requester = User::factory()->requester()->create();
        PettyCashRequest::factory()->count(2)->create(['requester_id' => $requester->id]);
        PettyCashRequest::factory()->count(3)->create();

        $this->actingAs($requester);

        $counts = Volt::test('pages.dashboard')->get('counts');

        $this->assertSame(2, $counts['all']['count']);
    }

    public function test_requester_timeline_hides_budget_code_from_approval_note(): void
    {
        $requester = User::factory()->requester()->create();
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'MKT-009', 'description' => 'Promosi Rahasia']);

        $request = app(PettyCashService::class)->submit($requester, 'Cetak brosur', '500000');
        app(PettyCashService::class)->review($admin, $request, 'approve', 'MKT-009', 'Promosi Rahasia');

        $this->actingAs($requester)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee('Disetujui Admin')
            ->assertDontSee('MKT-009')
            ->assertDontSee('Promosi Rahasia');
    }

    public function test_admin_review_rejects_already_reviewed_request(): void
    {
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->processing()->create();

        $this->expectException(ValidationException::class);

        app(PettyCashService::class)->review($admin, $request, 'reject', reason: 'Terlambat');
    }

    public function test_admin_can_create_user_with_normalized_phone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Volt::test('pages.settings.users')
            ->call('create')
            ->set('name', 'Sari Finance')
            ->set('email', 'sari@digitaliz.id')
            ->set('phone', '0812-3456-7890')
            ->set('role', 'finance')
            ->set('password', 'rahasia-panjang-123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'sari@digitaliz.id',
            'role' => 'finance',
            'phone' => '6281234567890',
        ]);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Volt::test('pages.settings.users')
            ->call('edit', $admin->id)
            ->set('role', 'requester')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_user_management_is_admin_only(): void
    {
        foreach (['requester', 'finance', 'head'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route('settings.users'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('settings.users'))
            ->assertOk();
    }

    public function test_profile_rejects_invalid_whatsapp_number(): void
    {
        $user = User::factory()->requester()->create();
        $this->actingAs($user);

        Volt::test('profile.update-profile-information-form')
            ->set('phone', '12345')
            ->call('updateProfileInformation')
            ->assertHasErrors(['phone']);

        Volt::test('profile.update-profile-information-form')
            ->set('phone', '+62 811 2233 4455')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('6281122334455', $user->fresh()->phone);
    }

    public function test_phone_normalization(): void
    {
        $this->assertSame('6281234567890', Phone::normalize('081234567890'));
        $this->assertSame('6281234567890', Phone::normalize('+62 812-3456-7890'));
        $this->assertSame('6281234567890', Phone::normalize('81234567890'));
        $this->assertNull(Phone::normalize(''));
        $this->assertFalse(Phone::isValid(Phone::normalize('021555')));
    }

    public function test_drive_file_name_contains_request_number(): void
    {
        $this->assertSame(
            'KC-2026-0007_invoice_struk-bensin-oktober.pdf',
            PettyCashService::driveFileName('KC-2026-0007', 'invoice', 'Struk Bensin Oktober.PDF'),
        );
    }

    public function test_used_budget_code_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $code = BudgetCode::factory()->create(['code' => 'OPR-777']);
        PettyCashRequest::factory()->processing()->create(['budget_code' => 'OPR-777']);

        Volt::test('pages.settings.index')
            ->call('deleteBudgetCode', $code->id)
            ->assertHasErrors(['budgetCodes']);

        $this->assertModelExists($code);
    }

    public function test_reports_filter_by_budget_code(): void
    {
        $this->actingAs(User::factory()->finance()->create());
        $match = PettyCashRequest::factory()->processing()->create(['budget_code' => 'ICT-001', 'nominal' => 100000, 'submitted_at' => now()]);
        PettyCashRequest::factory()->processing()->create(['budget_code' => 'OPR-001', 'nominal' => 900000, 'submitted_at' => now()]);

        $component = Volt::test('pages.reports.index')->set('budgetCode', 'ICT-001');

        $this->assertSame(1, $component->get('totals')['count']);
        $this->assertSame(100000.0, $component->get('totalNominal'));
        $this->assertStringContainsString('budget_code=ICT-001', $component->get('exportQuery'));
        $component->assertSee($match->request_number);
    }
}
