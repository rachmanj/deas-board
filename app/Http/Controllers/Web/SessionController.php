<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Support\ActivityLogger;
use App\Support\Actor;
use App\Support\SessionFilterQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function index(Request $request)
    {
        $totalSessions = Session::query()->count();
        $hasActiveFilters = SessionFilterQuery::hasActiveFilters($request);
        $sessions = SessionFilterQuery::fromRequest($request)->get();

        return view('sessions.index', [
            'sessions' => $sessions,
            'totalSessions' => $totalSessions,
            'hasActiveFilters' => $hasActiveFilters,
            'filterQuery' => SessionFilterQuery::redirectParams($request),
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

        return $this->redirectToSessions($request);
    }

    public function update(Request $request, Session $session): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'label' => ['required', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw (new ValidationException($validator))
                ->redirectTo(route('sessions', SessionFilterQuery::redirectParams($request)));
        }

        $validated = $validator->validated();

        $changes = [];
        $label = $validated['label'];
        $chatId = isset($validated['chat_id']) && $validated['chat_id'] !== '' ? $validated['chat_id'] : null;
        $host = isset($validated['host']) && $validated['host'] !== '' ? $validated['host'] : null;
        $notes = $validated['notes'] ?? null;

        foreach (['label' => $label, 'chat_id' => $chatId, 'host' => $host, 'notes' => $notes] as $field => $new) {
            $old = $session->{$field};
            if ($old != $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        $session->fill([
            'label' => $label,
            'chat_id' => $chatId,
            'host' => $host,
            'notes' => $notes,
        ]);
        $session->save();

        if ($changes !== []) {
            ActivityLogger::log('session.updated', Actor::from($request), $session, $changes);
        }

        return $this->redirectToSessions($request, 'Sesi diperbarui.');
    }

    public function activate(Request $request, Session $session)
    {
        if (! $session->is_active) {
            $session->update(['is_active' => true]);
            ActivityLogger::log('session.activated', Actor::from($request), $session);
        }

        return $this->redirectToSessions($request);
    }

    public function deactivate(Request $request, Session $session)
    {
        if ($session->is_active) {
            $session->update(['is_active' => false]);
            ActivityLogger::log('session.deactivated', Actor::from($request), $session);
        }

        return $this->redirectToSessions($request);
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
            'filterQuery' => SessionFilterQuery::redirectParams($request),
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

        return $this->redirectToSessions($request);
    }

    private function redirectToSessions(Request $request, ?string $info = null): RedirectResponse
    {
        $redirect = redirect()->route('sessions', SessionFilterQuery::redirectParams($request));

        if ($info !== null) {
            $redirect->with('info', $info);
        }

        return $redirect;
    }
}
