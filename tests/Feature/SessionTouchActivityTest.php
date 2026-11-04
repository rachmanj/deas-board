<?php

namespace Tests\Feature;

use App\Models\Session;
use App\Models\Task;
use App\Models\User;
use App\Support\HealthThresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsApiActor;
use Tests\TestCase;

class SessionTouchActivityTest extends TestCase
{
    use ActsAsApiActor;
    use RefreshDatabase;

    public function test_api_token_touch_updates_last_seen_at(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');

        $session = Session::create([
            'label' => 'Touch',
            'topic_id' => '501',
            'is_active' => true,
        ]);

        $this->asSession($session)->getJson('/api/me')->assertOk();

        $session->refresh();
        $this->assertNotNull($session->last_seen_at);
        $this->assertSame('2026-11-04 10:00:00', $session->last_seen_at->toDateTimeString());
        $this->assertNotNull($session->last_seen_ip);
    }

    public function test_api_token_touch_does_not_write_twice_within_one_minute(): void
    {
        Carbon::setTestNow('2026-11-04 10:00:00');

        $session = Session::create([
            'label' => 'Throttle',
            'topic_id' => '502',
            'is_active' => true,
        ]);

        $client = $this->asSession($session);
        $client->getJson('/api/me')->assertOk();

        $firstSeen = $session->fresh()->last_seen_at;
        Carbon::setTestNow('2026-11-04 10:00:30');

        $client->getJson('/api/me')->assertOk();

        $this->assertTrue($firstSeen->equalTo($session->fresh()->last_seen_at));
    }

    public function test_api_token_touch_stores_truncated_commit_ref_header(): void
    {
        $session = Session::create([
            'label' => 'Commit',
            'topic_id' => '503',
            'is_active' => true,
        ]);

        $longRef = str_repeat('a', 80);

        $this->asSession($session)
            ->withHeader('X-Commit-Ref', $longRef)
            ->getJson('/api/me')
            ->assertOk();

        $this->assertSame(str_repeat('a', 64), $session->fresh()->last_commit_ref);
    }

    public function test_user_api_token_does_not_touch_session_row(): void
    {
        $user = User::factory()->create();
        Session::create(['label' => 'S', 'topic_id' => '1', 'is_active' => true]);

        $this->asUser($user)->getJson('/api/me')->assertOk();

        $this->assertNull(Session::query()->first()->last_seen_at);
    }
}
