<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Support\ActivityLogger;
use App\Support\Actor;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = Session::query()
            ->withCount([
                'tokens as active_tokens_count',
                'assignedTasks as open_tasks_count' => fn ($query) => $query->where('status', '!=', 'done'),
            ])
            ->with(['tokens' => fn ($query) => $query->orderByDesc('id')])
            ->orderBy('label')
            ->get();

        return view('sessions.index', [
            'sessions' => $sessions,
            'pageTitle' => 'Sesi',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'topic_id' => ['required', 'string', 'max:255', 'unique:sessions,topic_id'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $session = Session::create([
            ...$validated,
            'is_active' => true,
        ]);

        $actor = Actor::from($request);
        ActivityLogger::log('session.created', $actor, $session, [
            'label' => $session->label,
            'topic_id' => $session->topic_id,
        ]);

        return redirect()->route('sessions');
    }

    public function activate(Request $request, Session $session)
    {
        if (! $session->is_active) {
            $session->update(['is_active' => true]);
            ActivityLogger::log('session.activated', Actor::from($request), $session);
        }

        return redirect()->route('sessions');
    }

    public function deactivate(Request $request, Session $session)
    {
        if ($session->is_active) {
            $session->update(['is_active' => false]);
            ActivityLogger::log('session.deactivated', Actor::from($request), $session);
        }

        return redirect()->route('sessions');
    }

    public function issueToken(Request $request, Session $session)
    {
        $tokenName = 'API '.now()->format('Y-m-d H:i');
        $accessToken = $session->createToken($tokenName);
        $plainTextToken = $accessToken->plainTextToken;

        ActivityLogger::log('token.issued', Actor::from($request), $session, [
            'token_id' => $accessToken->accessToken->id,
            'name' => $tokenName,
        ]);

        return response()->view('sessions.token-issued', [
            'session' => $session,
            'plainTextToken' => $plainTextToken,
            'tokenName' => $tokenName,
            'pageTitle' => 'Token diterbitkan',
        ]);
    }

    public function revokeToken(Request $request, Session $session, int $token)
    {
        $accessToken = $session->tokens()->whereKey($token)->firstOrFail();

        ActivityLogger::log('token.revoked', Actor::from($request), $session, [
            'token_id' => $accessToken->id,
            'name' => $accessToken->name,
        ]);

        $accessToken->delete();

        return redirect()->route('sessions');
    }
}
