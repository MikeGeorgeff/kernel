<?php

namespace Georgeff\Kernel\Support;

final class Env
{
    public static function get(string $name, mixed $default = null): mixed
    {
        $value = getenv($name);

        if (false === $value) {
            return $default;
        }

        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            if (is_array($arr = json_decode($value, true))) {
                return $arr;
            }
        }

        return match ($value) {
            'true',
            'TRUE',
            '(true)'  => true,
            'false',
            'FALSE',
            '(false)' => false,
            'null',
            'NULL',
            '(null)'  => null,
            default   => $value
        };
    }
}
