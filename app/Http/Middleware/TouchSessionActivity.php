<?php

namespace App\Http\Middleware;

use App\Models\Session;
use App\Support\HealthThresholds;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $actor = $request->user();
        if (! $actor instanceof Session) {
            return $response;
        }

        $session = $actor->fresh() ?? $actor;
        $data = [];
        $commitRef = $request->header('X-Commit-Ref');
        if ($commitRef !== null && $commitRef !== '') {
            $data['last_commit_ref'] = mb_substr((string) $commitRef, 0, 64);
        }

        $shouldTouchSeen = $session->last_seen_at === null
            || $session->last_seen_at->lte(now()->subSeconds(HealthThresholds::SESSION_TOUCH_INTERVAL_SECONDS));

        if ($shouldTouchSeen) {
            $data['last_seen_at'] = now();
            $data['last_seen_ip'] = $request->ip();
        }

        if ($data !== []) {
            $session->forceFill($data)->save();
        }

        return $response;
    }
}
