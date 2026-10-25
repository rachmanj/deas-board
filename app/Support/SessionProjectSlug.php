<?php

namespace App\Support;

class SessionProjectSlug
{
    public static function fromLabel(string $label): string
    {
        $label = trim($label);
        $label = mb_strtolower($label, 'UTF-8');
        $label = preg_replace('/[^a-z0-9]+/', '-', $label) ?? $label;
        $label = preg_replace('/-+/', '-', $label) ?? $label;
        $label = trim($label, '-');

        return $label !== '' ? $label : 'proyek';
    }
}
