<?php

namespace App\Support;

final class TextNormalizer
{
    public static function clean(?string $value): ?string
    {
        return Mojibake::repair($value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function cleanFields(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $field = $data[$key];
            if (is_string($field) || $field === null) {
                $data[$key] = self::clean($field);
            }
        }

        return $data;
    }
}
