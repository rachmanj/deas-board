<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickImportEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fixturesDir(): string
    {
        return base_path('tests/fixtures/emails');
    }

    public function test_apply_imports_metadata_without_body(): void
    {
        $this->artisan('click:import-emails', [
            '--dir' => $this->fixturesDir(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Email::count());

        $first = Email::query()->where('uid', '901')->first();
        $this->assertNotNull($first);
        $this->assertSame('INBOX', $first->mailbox);
        $this->assertSame('alice@example.com', $first->from_email);
        $this->assertSame('Alice Example', $first->from_name);
        $this->assertSame('Fixture email satu', $first->subject);
        $this->assertSame('arsip', $first->status);
        $this->assertFalse($first->has_full_body);
        $this->assertNull($first->body_text);

        $second = Email::query()->where('uid', '902')->first();
        $this->assertNotNull($second);
        $this->assertSame('cc@example.com', $second->cc);

        $this->assertNotNull(ActivityLog::query()->where('action', 'email.imported')->first());
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('click:import-emails', [
            '--dir' => $this->fixturesDir(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->artisan('click:import-emails', [
            '--dir' => $this->fixturesDir(),
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Email::count());
    }

    public function test_dry_run_reports_counts_without_writing(): void
    {
        $this->artisan('click:import-emails', [
            '--dir' => $this->fixturesDir(),
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Folder terbaca: 2')
            ->expectsOutputToContain('Berkas .eml: 2')
            ->expectsOutputToContain('Akan diimpor: 2');

        $this->assertSame(0, Email::count());
    }

    public function test_skips_duplicate_message_id_in_same_uid_folder(): void
    {
        $dir = base_path('tests/fixtures/emails-dedupe');

        $this->artisan('click:import-emails', [
            '--dir' => $dir,
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Duplikat Message-ID (dilewati): 1');

        $this->artisan('click:import-emails', [
            '--dir' => $dir,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Email::count());
        $this->assertNotNull(Email::query()->where('uid', '903')->first());
    }

    public function test_uid_from_filename_when_multiple_eml_in_one_folder(): void
    {
        $dir = base_path('tests/fixtures/emails-multi-uid');

        $this->artisan('click:import-emails', [
            '--dir' => $dir,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Email::count());
        $this->assertNotNull(Email::query()->where('uid', '115')->first());
        $this->assertNotNull(Email::query()->where('uid', '116')->first());
    }
}
