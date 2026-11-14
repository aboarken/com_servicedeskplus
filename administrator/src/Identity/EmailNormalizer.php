<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class EmailNormalizer
{
    /**
     * Trim and lowercase. Nothing else (no dot or plus-tag folding), so matching stays exact.
     */
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email), 'UTF-8');
    }

    public static function isUsable(string $normalized): bool
    {
        return $normalized !== ''
            && \strlen($normalized) <= 320
            && preg_match('/^[^@\s]+@[^@\s]+$/u', $normalized) === 1;
    }
}
