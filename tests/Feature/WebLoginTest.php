<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_board(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_user_can_login_and_reach_board(): void
    {
        $user = User::factory()->create([
            'email' => 'web@example.com',
            'password' => 'secret-pass',
        ]);

        $this->post('/login', [
            'email' => 'web@example.com',
            'password' => 'secret-pass',
        ])->assertRedirect('/');

        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_login_page_renders_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke '.config('app.name'))
            ->assertSee('Email', false);
    }
}
