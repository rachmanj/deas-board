<?php

namespace Tests\Concerns;

use App\Models\Session;
use App\Models\User;

trait ActsAsApiActor
{
    protected function sessionToken(Session $session): string
    {
        return $session->createToken('test')->plainTextToken;
    }

    protected function userToken(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function asSession(Session $session)
    {
        return $this->withToken($this->sessionToken($session));
    }

    protected function asUser(User $user)
    {
        return $this->withToken($this->userToken($user));
    }
}
