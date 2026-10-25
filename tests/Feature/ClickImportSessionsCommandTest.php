<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickImportSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_imports_new_sessions_and_is_idempotent(): void
    {
        $path = base_path('tests/fixtures/sessions-sample.md');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--apply' => true,
            '--chat-id' => 'chat-fixture',
        ])->assertSuccessful();

        $this->assertDatabaseHas('sessions', [
            'topic_id' => '35147',
            'label' => 'Arka Click',
            'chat_id' => 'chat-fixture',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sessions', [
            'topic_id' => '42099',
            'label' => 'Beta Node',
        ]);
        $this->assertNotNull(ActivityLog::query()->where('action', 'session.imported')->first());
        $this->assertSame(2, Session::count());

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Session::count());
    }

    public function test_does_not_deactivate_existing_session(): void
    {
        Session::create([
            'label' => 'Lama',
            'topic_id' => '35147',
            'is_active' => true,
        ]);

        $path = base_path('tests/fixtures/sessions-sample.md');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('sessions', [
            'topic_id' => '35147',
            'label' => 'Lama',
            'is_active' => true,
        ]);
    }

    public function test_dry_run_does_not_write_database(): void
    {
        $path = base_path('tests/fixtures/sessions-sample.md');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Sesi baru: 2');

        $this->assertSame(0, Session::count());
    }
}
