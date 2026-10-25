<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MentionController extends Controller
{
    public function index(Request $request)
    {
        $q = Str::lower(trim($request->string('q')));

        $sessions = Session::query()
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereRaw('LOWER(label) LIKE ?', ['%'.$q.'%'])
                        ->orWhereRaw('LOWER(topic_id) LIKE ?', ['%'.$q.'%']);
                });
            })
            ->orderBy('label')
            ->limit(15)
            ->get(['id', 'label', 'topic_id']);

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%']);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name']);

        $items = [];

        foreach ($sessions as $session) {
            $items[] = [
                'id' => (string) $session->topic_id,
                'label' => $session->label,
                'topic' => $session->topic_id,
                'type' => 'session',
            ];
        }

        foreach ($users as $user) {
            $items[] = [
                'id' => (string) $user->id,
                'label' => $user->name,
                'topic' => null,
                'type' => 'user',
            ];
        }

        return response()->json($items);
    }
}
