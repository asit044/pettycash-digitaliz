<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppSender;
use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\User;
use App\Models\WaLog;
use App\Services\PettyCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Phase 8 — WhatsApp notification hardening.
 *
 * Contract fakes only; never hits the real Fonnte API. The default test
 * environment has no FONNTE_TOKEN, so the real sender reports failure and
 * the workflow must still succeed with wa_logs recording the outcome.
 */
class RecordingFakeWhatsApp implements WhatsAppSender
{
    public array $sent = [];

    public function __construct(private readonly ?\Throwable $failure = null) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $phone, string $message): array
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->sent[] = [$phone, $message];

        return ['provider_message_id' => 'fake-'.count($this->sent), 'error' => null];
    }
}

class WhatsAppHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_matrix_targets_correct_roles_without_duplicates(): void
    {
        $fake = new RecordingFakeWhatsApp;
        $this->app->bind(WhatsAppSender::class, fn () => $fake);

        $requester = User::factory()->requester()->create(['phone' => '6281111111111']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);
        $finance = User::factory()->finance()->create(['phone' => '6283333333333']);
        $head = User::factory()->head()->create(['phone' => '6284444444444']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'Matriks WA', '100000');
        $service->review($admin, $request->fresh(), 'revise', reason: 'Kurang jelas');
        $service->resubmit($request->fresh(), $requester, 'Diperbaiki');
        $service->review($admin, $request->fresh(), 'approve', 'OPR-001', 'Operasional');
        $service->markPaid(
            $finance, $request->fresh(),
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        );

        $logs = WaLog::query()->where('request_id', $request->id)->orderBy('id')->get();

        $this->assertSame(
            ['admin', 'requester', 'admin', 'finance', 'requester'],
            $logs->pluck('recipient_role')->all()
        );
        $this->assertSame(
            ['submitted', 'needs_revision', 'submitted', 'approved', 'completed'],
            $logs->pluck('event')->all()
        );
        $this->assertSame(
            ['6282222222222', '6281111111111', '6282222222222', '6283333333333', '6281111111111'],
            $logs->pluck('recipient_phone')->all()
        );
        $this->assertTrue($logs->every(fn ($log) => $log->status === 'sent'));
        $this->assertSame(0, $logs->where('recipient_role', 'head')->count());
        $this->assertSame(RequestStatus::Done->value, $request->fresh()->status);
    }

    public function test_missing_token_and_phone_do_not_break_workflow(): void
    {
        $requester = User::factory()->requester()->create(['phone' => null]);
        $admin = User::factory()->admin()->create(['phone' => null]);

        $request = app(PettyCashService::class)->submit($requester, 'Tanpa telepon', '50000');

        $this->assertSame(RequestStatus::PendingReview->value, $request->status);
        $this->assertDatabaseHas('request_events', ['request_id' => $request->id, 'event' => 'submitted']);

        app(PettyCashService::class)->review($admin, $request->fresh(), 'reject', reason: 'Ditolak');

        $this->assertSame(RequestStatus::Rejected->value, $request->fresh()->status);
        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'event' => 'rejected',
            'status' => 'skipped',
        ]);
    }

    public function test_sender_exception_does_not_invalidate_successful_workflow(): void
    {
        $this->app->bind(
            WhatsAppSender::class,
            fn () => new RecordingFakeWhatsApp(new \RuntimeException('Gateway down.'))
        );

        $requester = User::factory()->requester()->create();
        $finance = User::factory()->finance()->create();
        $request = PettyCashRequest::factory()->processing()->create(['requester_id' => $requester->id]);

        app(PettyCashService::class)->markPaid(
            $finance, $request,
            UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
        );

        $request->refresh();

        $this->assertSame(RequestStatus::Done->value, $request->status);
        $this->assertDatabaseHas('wa_logs', [
            'request_id' => $request->id,
            'event' => 'completed',
            'status' => 'failed',
        ]);
    }

    public function test_messages_contain_no_restricted_data(): void
    {
        $fake = new RecordingFakeWhatsApp;
        $this->app->bind(WhatsAppSender::class, fn () => $fake);

        $requester = User::factory()->requester()->create(['phone' => '6281111111111', 'password' => 'secret-plain']);
        $admin = User::factory()->admin()->create(['phone' => '6282222222222']);
        BudgetCode::factory()->create(['code' => 'OPR-001', 'description' => 'Operasional']);
        $service = app(PettyCashService::class);

        $request = $service->submit($requester, 'Cek isi pesan', '120000');
        $service->review($admin, $request->fresh(), 'reject', reason: 'Ditolak ya');

        $combined = implode("\n", array_column($fake->sent, 1));

        $this->assertStringContainsString($request->request_number, $combined);
        $this->assertStringNotContainsStringIgnoringCase('password', $combined);
        $this->assertStringNotContainsString('secret-plain', $combined);
        $this->assertStringNotContainsString('FONNTE', $combined);
        $this->assertStringNotContainsString('GOOGLE', $combined);
    }
}
