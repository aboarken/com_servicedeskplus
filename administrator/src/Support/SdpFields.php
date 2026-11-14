<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Support;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Readers for SDP v3 field shapes: lookup objects {id, name} and dates {value: epoch ms, display_value}.
 */
final class SdpFields
{
    /** @param array<string, mixed> $entity */
    public static function name(array $entity, string $key): string
    {
        $value = $entity[$key] ?? null;

        if (\is_array($value)) {
            $value = $value['name'] ?? '';
        }

        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $entity */
    public static function id(array $entity, string $key): ?string
    {
        $value = $entity[$key] ?? null;
        $id    = \is_array($value) ? ($value['id'] ?? null) : null;

        return \is_scalar($id) && preg_match('/^[0-9]{1,20}$/', (string) $id) ? (string) $id : null;
    }

    /** @param array<string, mixed> $entity */
    public static function string(array $entity, string $key): string
    {
        $value = $entity[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Unix timestamp (seconds) of an SDP date field, or null.
     *
     * @param array<string, mixed> $entity
     */
    public static function timestamp(array $entity, string $key): ?int
    {
        $value = $entity[$key] ?? null;
        $ms    = \is_array($value) ? ($value['value'] ?? null) : $value;

        if (!\is_scalar($ms) || !preg_match('/^[0-9]{10,16}$/', (string) $ms)) {
            return null;
        }

        $ms = (int) $ms;

        return $ms > 0 ? intdiv($ms, 1000) : null;
    }
}
