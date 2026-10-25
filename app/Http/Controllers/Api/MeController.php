<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Support\Actor;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request)
    {
        $actor = Actor::from($request);
        $kind = Actor::kind($actor);

        $payload = [
            'kind' => $kind,
            'id' => $actor->getKey(),
        ];

        if ($actor instanceof Session) {
            $payload['label'] = $actor->label;
            $payload['topic_id'] = $actor->topic_id;
        } else {
            $payload['name'] = $actor->name;
        }

        return ApiResponse::data($payload);
    }
}
