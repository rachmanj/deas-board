<?php

namespace App\Providers;

use App\Models\Session;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias `lead` retained for historical polymorphic rows stored before full class names.
        Relation::morphMap([
            'session' => Session::class,
            'user' => User::class,
            'lead' => Session::class,
        ]);

        RateLimiter::for('api-token', function (Request $request) {
            $tokenId = $request->user()?->currentAccessToken()?->id;

            return Limit::perMinute(60)->by($tokenId ?: $request->ip());
        });
    }
}
