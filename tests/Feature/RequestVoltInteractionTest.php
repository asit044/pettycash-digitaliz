<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class RequestVoltInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_create_request_via_form(): void
    {
        $requester = User::factory()->requester()->create();

        Livewire::actingAs($requester)
            ->test('pages.requests.create')
            ->set('description', 'Beli printer')
            ->set('nominal', '1200000')
            ->set('invoice', UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('requests', [
            'description' => 'Beli printer',
            'status' => RequestStatus::PendingReview->value,
        ]);
    }

    public function test_requester_cannot_submit_invalid_nominal(): void
    {
        $requester = User::factory()->requester()->create();

        Livewire::actingAs($requester)
            ->test('pages.requests.create')
            ->set('description', '')
            ->set('nominal', '0')
            ->call('submit')
            ->assertHasErrors(['description', 'nominal']);

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_admin_can_approve_request_via_form(): void
    {
        $admin = User::factory()->admin()->create();
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        Livewire::actingAs($admin)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('budgetCode', 'OPR-001')
            ->set('budgetDescription', 'Operasional')
            ->call('review', 'approve')
            ->assertHasNoErrors();

        $request->refresh();

        $this->assertSame(RequestStatus::Processing->value, $request->status);
        $this->assertSame('OPR-001', $request->budget_code);

        $this->assertDatabaseHas('request_events', [
            'request_id' => $request->id,
            'event' => 'approved',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_approve_without_budget_code_via_form(): void
    {
        $admin = User::factory()->admin()->create();
        $request = PettyCashRequest::factory()->create(['status' => RequestStatus::PendingReview->value]);

        Livewire::actingAs($admin)
            ->test('pages.requests.show', ['id' => $request->id])
            ->call('review', 'approve')
            ->assertHasErrors(['budgetCode', 'budgetDescription']);

        $this->assertSame(RequestStatus::PendingReview->value, $request->fresh()->status);
    }

    public function test_finance_can_mark_paid_via_form(): void
    {
        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->processing()->create();

        Livewire::actingAs($finance)
            ->test('pages.requests.show', ['id' => $request->id])
            ->set('officialReceipt', UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))
            ->call('markPaid')
            ->assertHasNoErrors();

        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);

        $this->assertTrue($request->hasOfficialReceipt());

        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);
        $this->assertTrue($request->hasOfficialReceipt());
    }

    public function test_finance_cannot_mark_paid_without_receipt_via_form(): void
    {
        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->processing()->create();

        Livewire::actingAs($finance)
            ->test('pages.requests.show', ['id' => $request->id])
            ->call('markPaid')
            ->assertHasErrors(['officialReceipt']);

        $this->assertSame(RequestStatus::Processing->value, $request->fresh()->status);
    }
}
