<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\Actor;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'unread' => ['sometimes', 'in:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $actor = Actor::from($request);

        $query = Notification::query()
            ->where('notifiable_type', Actor::morphType($actor))
            ->where('notifiable_id', $actor->getKey())
            ->orderByDesc('id');

        if ($request->string('unread') === '1') {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(25);

        $data = $paginator->getCollection()->map(fn (Notification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'payload' => $n->payload,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
            'subject_type' => $n->subject_type,
            'subject_id' => $n->subject_id,
        ]);

        return ApiResponse::paginated($paginator, $data);
    }

    public function markRead(Request $request, Notification $notification)
    {
        $actor = Actor::from($request);

        if ($notification->notifiable_type !== Actor::morphType($actor)
            || $notification->notifiable_id !== $actor->getKey()) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        return ApiResponse::data([
            'id' => $notification->id,
            'read_at' => $notification->read_at->toIso8601String(),
        ]);
    }
}
