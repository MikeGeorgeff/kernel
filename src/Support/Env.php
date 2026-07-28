<?php

namespace Georgeff\Kernel\Support;

/**
 * Optional convenience wrapper around getenv() with basic type coercion. Nothing in the
 * package depends on this; use it or read the environment however you prefer.
 */
final class Env
{
    /**
     * Coerces a raw environment string: JSON objects/arrays decode to an array, 'true'/
     * 'false'/'null' (and parenthesized/uppercase variants) coerce to their PHP types,
     * everything else is returned as-is. Numeric strings are intentionally left as strings,
     * since most consumers (e.g. a DB port) expect a string and silent int coercion is a footgun.
     */
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
