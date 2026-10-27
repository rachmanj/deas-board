<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class WebSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_sessions_index_lists_sessions(): void
    {
        $user = $this->actingUser();
        Session::create(['label' => 'Alpha', 'topic_id' => '100', 'is_active' => true]);
        Session::create(['label' => 'Beta', 'topic_id' => '200', 'is_active' => false]);

        $response = $this->actingAs($user)->get('/sessions');

        $response->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSeeInOrder(['Alpha', 'Beta']);
    }

    public function test_user_can_create_session(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)->post('/sessions', [
            'label' => 'Hermes',
            'topic_id' => '42001',
            'chat_id' => 'chat-1',
            'host' => 'host.local',
            'notes' => 'Catatan uji',
        ]);

        $response->assertRedirect('/sessions');
        $this->assertDatabaseHas('sessions', [
            'label' => 'Hermes',
            'topic_id' => '42001',
            'is_active' => true,
        ]);
        $this->assertNotNull(ActivityLog::query()->where('action', 'session.created')->first());
    }

    public function test_issue_token_shows_plaintext_once_and_stores_hash(): void
    {
        $user = $this->actingUser();
        $session = Session::create(['label' => 'Tok', 'topic_id' => '77', 'is_active' => true]);

        $issue = $this->actingAs($user)->post("/sessions/{$session->id}/tokens");
        $issue->assertOk()
            ->assertSee('tidak akan ditampilkan lagi', false);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $stored = PersonalAccessToken::query()->first();
        $this->assertNotNull($stored);
        $this->assertNotEmpty($stored->token);

        $content = $issue->getContent();
        $this->assertSame(1, preg_match('/id="issued-token"[^>]*value="(\d+\|[^"]+)"/', $content, $matches));
        $plainTextToken = $matches[1];
        $this->assertNotNull(PersonalAccessToken::findToken($plainTextToken));
        $this->assertNotNull(ActivityLog::query()->where('action', 'token.issued')->first());

        $index = $this->actingAs($user)->get('/sessions');
        $index->assertOk()
            ->assertDontSee($plainTextToken);

        $this->assertStringContainsString('execCommand', $content);
        $this->assertStringContainsString('isSecureContext', $content);
        $this->assertStringNotContainsString('navigator.clipboard?.writeText(input.value)', $content);
        $issue->assertSee('id="copy-status"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_revoke_token_deletes_row(): void
    {
        $user = $this->actingUser();
        $session = Session::create(['label' => 'Rev', 'topic_id' => '88', 'is_active' => true]);
        $token = $session->createToken('uji');
        $tokenId = $token->accessToken->id;

        $this->actingAs($user)
            ->delete("/sessions/{$session->id}/tokens/{$tokenId}")
            ->assertRedirect('/sessions');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
        $this->assertNotNull(ActivityLog::query()->where('action', 'token.revoked')->first());
    }

    public function test_patch_session_updates_label_and_logs_activity(): void
    {
        $user = $this->actingUser();
        $session = Session::create(['label' => 'Lama', 'topic_id' => '9001', 'is_active' => true]);

        $this->actingAs($user)
            ->patch("/sessions/{$session->id}", [
                '_editing_session' => $session->id,
                'label' => 'Baru',
            ])
            ->assertRedirect('/sessions')
            ->assertSessionHas('info');

        $session->refresh();
        $this->assertSame('Baru', $session->label);

        $log = ActivityLog::query()->where('action', 'session.updated')->first();
        $this->assertNotNull($log);
        $this->assertSame('Lama', $log->changes['label']['old']);
        $this->assertSame('Baru', $log->changes['label']['new']);
    }

    public function test_patch_session_does_not_change_topic_id_from_payload(): void
    {
        $user = $this->actingUser();
        $session = Session::create(['label' => 'S', 'topic_id' => '42', 'is_active' => true]);

        $this->actingAs($user)
            ->patch("/sessions/{$session->id}", [
                '_editing_session' => $session->id,
                'label' => 'S',
                'topic_id' => '99999',
            ])
            ->assertRedirect('/sessions');

        $this->assertSame('42', $session->fresh()->topic_id);
    }

    public function test_sessions_search_and_filters(): void
    {
        $user = $this->actingUser();
        Session::create(['label' => 'Hermes Alpha', 'topic_id' => '100', 'chat_id' => 'c-1', 'is_active' => true]);
        Session::create(['label' => 'Beta', 'topic_id' => '200', 'is_active' => false]);
        $busy = Session::create(['label' => 'Busy', 'topic_id' => '300', 'is_active' => true]);
        Session::create(['label' => 'Quiet', 'topic_id' => '400', 'is_active' => true]);

        Task::create(['code' => 'CLK-1', 'title' => 'T1', 'status' => 'todo', 'priority' => 'normal', 'assignee_session_id' => $busy->id]);
        Task::create(['code' => 'CLK-2', 'title' => 'T2', 'status' => 'todo', 'priority' => 'normal', 'assignee_session_id' => $busy->id]);
        Task::create(['code' => 'CLK-3', 'title' => 'T3', 'status' => 'done', 'priority' => 'normal', 'assignee_session_id' => $busy->id]);
        Task::create(['code' => 'CLK-4', 'title' => 'T4', 'status' => 'todo', 'priority' => 'normal', 'assignee_session_id' => Session::query()->where('topic_id', '400')->value('id')]);

        $this->actingAs($user)->get('/sessions?q=Alpha')
            ->assertOk()
            ->assertSee('Hermes Alpha')
            ->assertDontSee('Beta');

        $this->actingAs($user)->get('/sessions?q=200')
            ->assertOk()
            ->assertSee('Beta')
            ->assertDontSee('Hermes Alpha');

        $this->actingAs($user)->get('/sessions?status=nonaktif')
            ->assertOk()
            ->assertSee('Beta')
            ->assertDontSee('Hermes Alpha');

        $response = $this->actingAs($user)->get('/sessions?sort=tasks');
        $response->assertOk();
        $content = $response->getContent();
        $busyPos = strpos($content, 'Busy');
        $quietPos = strpos($content, 'Quiet');
        $this->assertNotFalse($busyPos);
        $this->assertNotFalse($quietPos);
        $this->assertLessThan($quietPos, $busyPos);
    }

    public function test_sessions_search_with_no_results_shows_empty_state(): void
    {
        $user = $this->actingUser();
        Session::create(['label' => 'Only', 'topic_id' => '1', 'is_active' => true]);

        $this->actingAs($user)->get('/sessions?q=zzznomatch')
            ->assertOk()
            ->assertSee('Tidak ada sesi yang cocok dengan filter')
            ->assertDontSee('Only');
    }
}
