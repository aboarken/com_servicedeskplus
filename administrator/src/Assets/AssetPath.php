<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reads values from SDP JSON by dot path ("user.id", "udf_fields.udf_date1").
 */
final class AssetPath
{
    /** @param array<string, mixed> $data */
    public static function get(array $data, string $path): mixed
    {
        if ($path === '') {
            return null;
        }

        $value = $data;

        foreach (explode('.', $path) as $part) {
            if (!\is_array($value) || !\array_key_exists($part, $value)) {
                return null;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Scalar text of a value. Lookup objects ({id, name}) give their name.
     *
     * @param array<string, mixed> $data
     */
    public static function text(array $data, string $path): string
    {
        $value = self::get($data, $path);

        if (\is_array($value)) {
            $value = $value['name'] ?? $value['display_value'] ?? $value['value'] ?? null;
        }

        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Unix timestamp of a date value: {value: epoch ms} or a bare epoch ms.
     *
     * @param array<string, mixed> $data
     */
    public static function timestamp(array $data, string $path): ?int
    {
        $value = self::get($data, $path);
        $ms    = \is_array($value) ? ($value['value'] ?? null) : $value;

        if (!\is_scalar($ms) || !preg_match('/^[0-9]{10,16}$/', (string) $ms)) {
            return null;
        }

        $ms = (int) $ms;

        return $ms > 0 ? intdiv($ms, 1000) : null;
    }

    /**
     * True when the field holds anything at all (a date, text, or a lookup object).
     *
     * @param array<string, mixed> $data
     */
    public static function isSet(array $data, string $path): bool
    {
        return self::timestamp($data, $path) !== null || self::text($data, $path) !== '';
    }

    /**
     * Field paths of an asset (names only), up to three levels deep, for the admin test.
     *
     * @param  array<string, mixed>  $data
     *
     * @return list<string>
     */
    public static function listPaths(array $data, string $prefix = '', int $depth = 0): array
    {
        $paths = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key) || !preg_match('/^[a-z0-9_]{1,64}$/', $key)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix . '.' . $key;

            if (\is_array($value) && !array_is_list($value) && $depth < 2) {
                $paths = [...$paths, ...self::listPaths($value, $path, $depth + 1)];
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
