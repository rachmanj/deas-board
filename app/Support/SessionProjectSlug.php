<?php

namespace App\Support;

class SessionProjectSlug
{
    public static function fromLabel(string $label): string
    {
        $label = trim($label);
        $label = mb_strtolower($label);
        $label = preg_replace('/\s+/', '-', $label) ?? $label;

        return $label;
    }
}
