<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Session;
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
}
