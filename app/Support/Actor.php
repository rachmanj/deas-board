<?php

namespace App\Support;

use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;

class Actor
{
    public static function from(Request $request): object
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        return $user;
    }

    public static function kind(object $actor): string
    {
        return $actor instanceof Session ? 'session' : 'user';
    }

    public static function morphType(object $actor): string
    {
        return $actor::class;
    }
}
