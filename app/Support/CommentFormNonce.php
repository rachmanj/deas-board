<?php

namespace App\Support;

class CommentFormNonce
{
    private const SESSION_KEY = 'comment_form_nonces';

    public static function issue(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $nonces = session()->get(self::SESSION_KEY, []);
        $nonces[$nonce] = true;
        session()->put(self::SESSION_KEY, $nonces);

        return $nonce;
    }

    public static function consume(?string $nonce): bool
    {
        if ($nonce === null || $nonce === '') {
            return false;
        }

        $nonces = session()->get(self::SESSION_KEY, []);
        if (! isset($nonces[$nonce])) {
            return false;
        }

        unset($nonces[$nonce]);
        session()->put(self::SESSION_KEY, $nonces);

        return true;
    }
}
