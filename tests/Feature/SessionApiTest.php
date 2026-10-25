<?php

namespace Tests\Feature;

use App\Models\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class SessionApiTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_me_returns_session_actor(): void
    {
        $session = Session::create([
            'label' => 'Hermes',
            'topic_id' => '42001',
            'is_active' => true,
        ]);

        $response = $this->asSession($session)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.kind', 'session')
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonPath('data.label', 'Hermes')
            ->assertJsonPath('data.topic_id', '42001');
    }

    public function test_sessions_lists_active_only(): void
    {
        Session::create(['label' => 'A', 'topic_id' => '1', 'is_active' => true]);
        Session::create(['label' => 'B', 'topic_id' => '2', 'is_active' => false]);

        $actor = Session::create(['label' => 'Actor', 'topic_id' => '99', 'is_active' => true]);

        $response = $this->asSession($actor)->getJson('/api/sessions');

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();
        $this->assertContains('A', $labels);
        $this->assertNotContains('B', $labels);
    }
}
