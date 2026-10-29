<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebUiButtonTest extends TestCase
{
    use RefreshDatabase;

    private const RESPONSIVE_HEIGHT = 'min-h-11 sm:min-h-10';

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    public function test_header_new_task_button_uses_soft_primary_styles(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('aria-label="New task"', false)
            ->assertSee(self::RESPONSIVE_HEIGHT, false)
            ->assertSee('rounded-xl bg-teal-500/90 px-4', false)
            ->assertSee('font-medium text-zinc-950 shadow-sm', false)
            ->assertSee('focus-visible:ring-teal-500/60 focus-visible:ring-offset-1', false)
            ->assertDontSee('font-semibold text-zinc-950', false);
    }

    public function test_header_secondary_buttons_use_soft_border_styles(): void
    {
        $user = $this->actingUser();

        $content = $this->actingAs($user)->get('/')->getContent();

        $this->assertStringContainsString(
            'inline-flex '.self::RESPONSIVE_HEIGHT.' items-center justify-center whitespace-nowrap rounded-xl border border-zinc-800/80 px-3.5',
            $content
        );
        $this->assertStringContainsString('hover:bg-zinc-800/60 hover:text-zinc-100', $content);
    }

    public function test_board_filter_apply_button_uses_responsive_height(): void
    {
        $user = $this->actingUser();
        Task::create(['code' => 'CLK-1', 'title' => 'Todo', 'status' => 'todo', 'priority' => 'normal']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Apply', false)
            ->assertSee(self::RESPONSIVE_HEIGHT.' items-center justify-center whitespace-nowrap rounded-xl bg-teal-500/90 px-3.5', false);
    }

    public function test_login_submit_button_uses_responsive_height(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk', false)
            ->assertSee(self::RESPONSIVE_HEIGHT.' w-full items-center justify-center rounded-xl bg-teal-500/90', false);
    }
}
