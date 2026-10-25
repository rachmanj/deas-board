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

        $session = Session::query()->where('topic_id', '2')->first();
        $this->assertNotNull($session);
        $this->assertSame('POS-ATK', $session->label);
        $this->assertStringContainsString('penulis', (string) $session->notes);
        $this->assertDatabaseHas('sessions', [
            'topic_id' => '2',
            'label' => 'POS-ATK',
            'chat_id' => 'chat-fixture',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sessions', [
            'topic_id' => '29724',
            'label' => 'AccountingOne',
        ]);
        $this->assertDatabaseHas('sessions', [
            'topic_id' => '12488',
            'label' => 'ARK-GS',
        ]);
        $this->assertNotNull(ActivityLog::query()->where('action', 'session.imported')->first());
        $this->assertSame(3, Session::count());

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(3, Session::count());
    }

    public function test_does_not_deactivate_existing_session(): void
    {
        Session::create([
            'label' => 'Lama',
            'topic_id' => '2',
            'is_active' => true,
        ]);

        $path = base_path('tests/fixtures/sessions-sample.md');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('sessions', [
            'topic_id' => '2',
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
            ->expectsOutputToContain('Sesi baru: 3');

        $this->assertSame(0, Session::count());
    }

    public function test_extra_sessions_json_adds_rows_not_in_markdown(): void
    {
        $path = base_path('tests/fixtures/sessions-sample.md');
        $extra = base_path('tests/fixtures/sessions-extra.json');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--extra-sessions' => $extra,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Sesi baru: 4');

        $this->artisan('click:import-sessions', [
            '--from' => $path,
            '--extra-sessions' => $extra,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(4, Session::count());
        $this->assertDatabaseHas('sessions', [
            'topic_id' => '99901',
            'label' => 'Sesi Catatan Saja',
        ]);
    }
}
