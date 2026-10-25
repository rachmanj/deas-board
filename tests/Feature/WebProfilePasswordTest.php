<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class WebProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'current-pass-12',
        ]);

        $this->actingAs($user)
            ->patch('/profile/password', [
                'current_password' => 'wrong-pass-12',
                'password' => 'new-password-12',
                'password_confirmation' => 'new-password-12',
            ])
            ->assertSessionHasErrors('current_password');
    }

    public function test_user_can_change_password_and_login_with_new_one(): void
    {
        $user = User::factory()->create([
            'email' => 'profile@example.com',
            'password' => 'current-pass-12',
        ]);

        $this->actingAs($user)
            ->patch('/profile/password', [
                'current_password' => 'current-pass-12',
                'password' => 'brand-new-pass-12',
                'password_confirmation' => 'brand-new-pass-12',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('info');

        $user->refresh();
        $this->assertTrue(password_verify('brand-new-pass-12', $user->password));

        $this->assertNotNull(
            ActivityLog::query()->where('action', 'user.password_changed')->first()
        );

        Auth::logout();

        $this->post('/login', [
            'email' => 'profile@example.com',
            'password' => 'brand-new-pass-12',
        ])->assertRedirect('/');
    }

    public function test_profile_page_is_available_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Ganti kata sandi', false);
    }
}
