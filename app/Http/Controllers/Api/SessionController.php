<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Support\ApiResponse;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = Session::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'label', 'topic_id', 'is_active']);

        return ApiResponse::data($sessions);
    }
}
