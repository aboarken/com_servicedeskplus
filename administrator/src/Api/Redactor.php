<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Removes secrets from text before it is logged or displayed.
 */
final class Redactor
{
    /**
     * @param  list<string>  $secrets  Exact values to remove (API keys)
     */
    public static function scrub(string $text, array $secrets = [], int $maxLength = 300): string
    {
        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $text = str_ireplace($secret, '[redacted]', $text);
            }
        }

        // Header or parameter style "authtoken: xyz" / "authtoken=xyz" / "technician_key=xyz".
        $text = preg_replace('/\b(authtoken|technician_key|api_key|apikey)\b(["\']?\s*[:=]\s*["\']?)[^\s"\'&,;}]+/i', '$1$2[redacted]', $text) ?? '';

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '…';
        }

        return $text;
    }
}
