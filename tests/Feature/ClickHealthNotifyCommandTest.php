<?php

namespace Tests\Feature;

use App\Models\DeviationNotification;
use App\Models\Session;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Support\HealthThresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClickHealthNotifyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
    }

    protected function tearDown(): void
    {
        putenv('TELEGRAM_BOT_TOKEN');
        putenv('TELEGRAM_CHAT_ID');
        parent::tearDown();
    }

    private function setTelegramEnv(): void
    {
        putenv('TELEGRAM_BOT_TOKEN=test-bot-token');
        putenv('TELEGRAM_CHAT_ID=123456789');
    }

    /**
     * @return array{in_progress: Task, review: Task, done: Task, todo: Task}
     */
    private function seedDeviationFixtures(): array
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);

        $staleProgress = Task::create([
            'code' => 'CLK-IP',
            'title' => 'Progress basi',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        $staleProgress->forceFill(['updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 1)])->save();

        $staleReview = Task::create([
            'code' => 'CLK-RV',
            'title' => 'Review basi',
            'status' => 'review',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        $staleReview->forceFill(['updated_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 1)])->save();

        $doneNoProof = Task::create([
            'code' => 'CLK-DN',
            'title' => 'Done tanpa bukti',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now(),
        ]);

        TaskUpdate::create([
            'task_id' => $doneNoProof->id,
            'from_status' => 'in_progress',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $todoCommit = Task::create([
            'code' => 'CLK-TC',
            'title' => 'Sudah commit belum naik',
            'status' => 'todo',
            'priority' => 'normal',
            'external_ref' => 'abc1234567890deadbeef',
        ]);

        TaskUpdate::create([
            'task_id' => $staleProgress->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ])->forceFill([
            'created_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
            'updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
        ])->save();

        TaskUpdate::create([
            'task_id' => $staleReview->id,
            'from_status' => 'in_progress',
            'to_status' => 'review',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ])->forceFill([
            'created_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 2),
            'updated_at' => now()->subHours(HealthThresholds::REVIEW_STALE_HOURS + 2),
        ])->save();

        return [
            'in_progress' => $staleProgress,
            'review' => $staleReview,
            'done' => $doneNoProof,
            'todo' => $todoCommit,
        ];
    }

    public function test_skips_when_telegram_env_is_empty(): void
    {
        Http::fake();
        Log::spy();

        $this->seedDeviationFixtures();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('dilewati: 4')
            ->assertExitCode(0);

        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->once()->with('notifikasi dilewati (env belum diisi)');
    }

    public function test_dry_run_prints_without_http_or_notification_rows(): void
    {
        Http::fake();
        $this->setTelegramEnv();
        $this->seedDeviationFixtures();

        $this->artisan('click:health-notify --dry-run')
            ->expectsOutputToContain('CLK-IP')
            ->expectsOutputToContain('1 penyimpangan')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, DeviationNotification::query()->count());
    }

    public function test_dry_run_excludes_old_import_done_without_session_trace(): void
    {
        Http::fake();
        $this->setTelegramEnv();
        Carbon::setTestNow('2026-11-04 12:00:00');

        Task::create([
            'code' => 'CLK-OLD',
            'title' => 'Impor email lama',
            'status' => 'done',
            'priority' => 'normal',
            'source' => 'email',
            'done_at' => now()->subDays(20),
        ]);

        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);
        $recent = Task::create([
            'code' => 'CLK-NEW',
            'title' => 'Done baru tanpa bukti',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now()->subDays(2),
        ]);
        TaskUpdate::create([
            'task_id' => $recent->id,
            'from_status' => 'review',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $this->artisan('click:health-notify --dry-run')
            ->expectsOutputToContain('CLK-NEW')
            ->doesntExpectOutputToContain('CLK-OLD')
            ->assertExitCode(0);
    }

    public function test_days_option_widens_done_without_evidence_window(): void
    {
        Http::fake();
        $this->setTelegramEnv();
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);
        $tenDaysAgo = Task::create([
            'code' => 'CLK-10D',
            'title' => 'Done 10 hari lalu',
            'status' => 'done',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
            'done_at' => now()->subDays(10),
        ]);
        TaskUpdate::create([
            'task_id' => $tenDaysAgo->id,
            'from_status' => 'in_progress',
            'to_status' => 'done',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $this->artisan('click:health-notify --dry-run')
            ->doesntExpectOutputToContain('CLK-10D')
            ->assertExitCode(0);

        $this->artisan('click:health-notify --dry-run --days=30')
            ->expectsOutputToContain('CLK-10D')
            ->assertExitCode(0);
    }

    public function test_sends_one_message_per_category_and_records_notifications(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        $this->setTelegramEnv();
        $this->seedDeviationFixtures();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 4 kategori')
            ->assertExitCode(0);

        Http::assertSentCount(4);

        $recorded = Http::recorded();
        $bodies = collect($recorded)->map(fn ($pair) => $pair[0]->data()['text'] ?? '')->all();
        $this->assertTrue(collect($bodies)->contains(fn ($text) => str_contains($text, 'CLK-IP')));
        $this->assertTrue(collect($bodies)->contains(fn ($text) => str_contains($text, 'CLK-RV')));
        $this->assertTrue(collect($bodies)->contains(fn ($text) => str_contains($text, 'CLK-DN')));
        $this->assertTrue(collect($bodies)->contains(fn ($text) => str_contains($text, 'CLK-TC')));

        $this->assertSame(4, DeviationNotification::query()->count());
    }

    public function test_second_run_same_day_is_idempotent_until_force(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        $this->setTelegramEnv();
        $this->seedDeviationFixtures();

        $this->artisan('click:health-notify')->assertExitCode(0);
        Http::assertSentCount(4);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('click:health-notify')->assertExitCode(0);
        Http::assertNothingSent();

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('click:health-notify --force')->assertExitCode(0);
        Http::assertSentCount(4);
    }

    public function test_normal_status_change_does_not_trigger_notification(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        $this->setTelegramEnv();
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create(['label' => 'S', 'topic_id' => '1', 'is_active' => true]);
        $task = Task::create([
            'code' => 'CLK-OK',
            'title' => 'Perubahan biasa',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);

        TaskUpdate::create([
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ]);

        $this->artisan('click:health-notify')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, DeviationNotification::query()->count());
    }

    private function seedSingleCategoryInProgressFixture(): Task
    {
        Carbon::setTestNow('2026-11-04 12:00:00');

        $session = Session::create(['label' => 'H', 'topic_id' => '801', 'is_active' => true]);

        $staleProgress = Task::create([
            'code' => 'CLK-IP',
            'title' => 'Progress basi',
            'status' => 'in_progress',
            'priority' => 'normal',
            'assignee_session_id' => $session->id,
        ]);
        $staleProgress->forceFill(['updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 1)])->save();

        TaskUpdate::create([
            'task_id' => $staleProgress->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'actor_type' => Session::class,
            'actor_id' => $session->id,
        ])->forceFill([
            'created_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
            'updated_at' => now()->subHours(HealthThresholds::IN_PROGRESS_STALE_HOURS + 2),
        ])->save();

        return $staleProgress;
    }

    public function test_telegram_ok_records_and_summary_shows_sent(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
        $this->setTelegramEnv();
        $this->seedSingleCategoryInProgressFixture();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 1 kategori · gagal: 0 · dilewati: 0')
            ->assertExitCode(0);

        $this->assertSame(1, DeviationNotification::query()->count());
    }

    public function test_telegram_401_does_not_record_and_logs_warning_without_token(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401),
        ]);
        $this->setTelegramEnv();
        $this->seedSingleCategoryInProgressFixture();

        Log::spy();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 0 kategori · gagal: 1 · dilewati: 0')
            ->assertExitCode(0);

        $this->assertSame(0, DeviationNotification::query()->count());

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('notifikasi Telegram gagal', \Mockery::on(function (array $context) {
                return $context['category'] === 'in_progress_stale'
                    && $context['count'] === 1
                    && $context['status'] === 401
                    && ! str_contains(json_encode($context), 'test-bot-token');
            }));
    }

    public function test_telegram_connection_error_does_not_record_or_throw(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });
        $this->setTelegramEnv();
        $this->seedSingleCategoryInProgressFixture();

        Log::spy();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 0 kategori · gagal: 1 · dilewati: 0')
            ->assertExitCode(0);

        $this->assertSame(0, DeviationNotification::query()->count());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_empty_env_skips_one_category_without_http(): void
    {
        Http::fake();
        Log::spy();

        $this->seedSingleCategoryInProgressFixture();

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('dilewati: 1')
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(0, DeviationNotification::query()->count());
    }

    public function test_retry_after_failed_send_delivers_when_telegram_accepts(): void
    {
        $this->setTelegramEnv();
        $this->seedSingleCategoryInProgressFixture();

        Http::fake([
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'Unauthorized'], 401)
                ->push(['ok' => true], 200),
        ]);

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 0 kategori · gagal: 1 · dilewati: 0')
            ->assertExitCode(0);

        $this->assertSame(0, DeviationNotification::query()->count());

        $this->artisan('click:health-notify')
            ->expectsOutputToContain('terkirim: 1 kategori · gagal: 0 · dilewati: 0')
            ->assertExitCode(0);

        Http::assertSentCount(2);
        $this->assertSame(1, DeviationNotification::query()->count());
    }
}
