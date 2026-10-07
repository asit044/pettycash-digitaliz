<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\RequestFile;
use App\Models\User;
use App\Services\PettyCashService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 2 — Authorization & access control.
 *
 * Maps to the 17 required authorization cases. Cases already covered by
 * PettyCashFlowTest / RequestPagesTest / RequestVoltInteractionTest /
 * ExportTest are reused there and NOT duplicated here; this file locks the
 * gaps found in audit: service-level role checks, resubmit ownership,
 * file-download scoping (+ URL consistency), settings actions, and the
 * cross-role 403 matrix.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_download_other_request_file(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();
        $file = RequestFile::factory()->create([
            'request_id' => PettyCashRequest::factory()->create(['requester_id' => $owner->id]),
        ]);

        $this->actingAs($other)
            ->get(route('requests.files.download', ['id' => $file->request_id, 'file' => $file->id]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('requests.files.download', ['id' => $file->request_id + 999999, 'file' => $file->id]))
            ->assertNotFound();
    }

    public function test_requester_cannot_approve_request(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
        ]);

        Livewire::actingAs($requester)
            ->test('pages.requests.show', ['id' => $request->id])
            ->call('review', 'approve')
            ->assertForbidden();

        $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);

        try {
            app(PettyCashService::class)->review($requester, $request, 'approve', 'OPR-001', 'Operasional');
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_requester_cannot_modify_budget(): void
    {
        $requester = User::factory()->requester()->create();
        $request = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
        ]);

        Livewire::actingAs($requester)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('budgetCode', 'OPR-001')
            ->set('budgetDescription', 'Operasional')
            ->call('review', 'approve')
            ->assertForbidden();

        $request->refresh();

        $this->assertNull($request->budget_code);
        $this->assertNull($request->budget_description);
        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
    }

    public function test_admin_can_reject_request(): void
    {
        $admin = User::factory()->admin()->create();
        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $request = PettyCashRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => RequestStatus::PendingReview->value,
        ]);

        app(PettyCashService::class)->review($admin, $request, 'reject', reason: 'Dana tidak tersedia');

        $this->assertSame(RequestStatus::Rejected->value, $request->fresh()->status);
        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => 'rejected',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_finance_cannot_change_budget(): void
    {
        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        Livewire::actingAs($finance)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('budgetCode', 'OPR-001')
            ->set('budgetDescription', 'Operasional')
            ->call('review', 'approve')
            ->assertForbidden();

        try {
            app(PettyCashService::class)->review($finance, $request, 'approve', 'OPR-001', 'Operasional');
            $this->fail('Expected AuthorizationException was not thrown.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $request = PettyCashRequest::factory()->processing()->create([
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
        ]);

        app(PettyCashService::class)->markPaid(
            $finance,
            $request,
            UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        );

        $request->refresh();

        $this->assertSame('OPR-001', $request->budget_code);
        $this->assertSame('Operasional', $request->budget_description);
        $this->assertSame(RequestStatus::Done->value, $request->status);
    }

    public function test_non_owner_cannot_resubmit(): void
    {
        $owner = User::factory()->requester()->create();
        $other = User::factory()->requester()->create();
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->needsRevision()->create(['requester_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertForbidden();

        try {
            app(PettyCashService::class)->resubmit($request, $other, 'Coba ambil alih');
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        try {
            app(PettyCashService::class)->resubmit($request, $admin, 'Admin ikut campur');
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Livewire::actingAs($admin)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('editDescription', 'Admin ikut campur')
            ->call('resubmit')
            ->assertForbidden();

        $this->assertSame(RequestStatus::NeedsRevision->value, $request->fresh()->status);
    }

    public function test_submit_requires_requester_role(): void
    {
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();

        foreach ([$admin, $finance] as $user) {
            try {
                app(PettyCashService::class)->submit($user, 'Tidak berhak', '100000');
                $this->fail('Expected AuthorizationException was not thrown.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_settings_actions_require_admin(): void
    {
        $requester = User::factory()->requester()->create();
        $finance = User::factory()->finance()->create();

        foreach ([$requester, $finance] as $user) {
            Livewire::actingAs($user)
                ->test('pages.settings.index')
                ->set('signerName', 'X')
                ->set('signerTitle', 'Y')
                ->set('companyName', 'Z')
                ->call('saveGeneral')
                ->assertForbidden();

            Livewire::actingAs($user)
                ->test('pages.settings.index')
                ->set('newCode', 'BAD-01')
                ->set('newDescription', 'Buruk')
                ->call('addBudgetCode')
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('budget_codes', ['code' => 'BAD-01']);
    }

    public function test_head_can_view_request_detail_for_monitoring(): void
    {
        $head = User::factory()->head()->create();
        $request = PettyCashRequest::factory()->create();

        $this->actingAs($head)
            ->get(route('requests.show', ['id' => $request->id]))
            ->assertOk()
            ->assertSee($request->request_number);
    }

    public function test_unauthorized_roles_receive_403(): void
    {
        $requester = User::factory()->requester()->create();
        $admin = User::factory()->admin()->create();
        $finance = User::factory()->finance()->create();
        $head = User::factory()->head()->create();

        $this->get(route('requests.index'))->assertRedirectToRoute('login');
        $this->get(route('login'))->assertOk();

        $this->actingAs($finance)->get(route('admin.index'))->assertForbidden();
        $this->actingAs($finance)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($head)->get(route('exports.csv'))->assertForbidden();
        $this->actingAs($head)->get(route('exports.pdf'))->assertForbidden();
        $this->actingAs($requester)->get(route('exports.csv'))->assertForbidden();
        $this->actingAs($requester)->get(route('exports.pdf'))->assertForbidden();
        $this->actingAs($requester)->get(route('reports.index'))->assertForbidden();

        $pending = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);
        $processing = PettyCashRequest::factory()->processing()->create();

        Livewire::actingAs($finance)
            ->test('pages.requests.show', ['id' => $pending->id])
            ->call('review', 'reject')
            ->assertForbidden();

        Livewire::actingAs($admin)
            ->test('pages.requests.show', ['id' => $processing->id])
            ->set('officialReceipt', UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'))
            ->call('markPaid')
            ->assertForbidden();
    }
}
