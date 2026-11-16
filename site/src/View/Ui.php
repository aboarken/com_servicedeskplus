<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Small helpers shared by the portal templates (badges in the portal's own design).
 */
final class Ui
{
    /**
     * Colour ("tone") of a ServiceDesk Plus status: request statuses (Open, On Hold, Resolved,
     * Closed, Canceled) and approval statuses (Pending, Approved, Rejected).
     */
    public static function tone(string $status): string
    {
        $s = mb_strtolower(trim($status));

        return match (true) {
            $s === 'open'                                          => 'blue',
            \in_array($s, ['on hold', 'onhold'], true)            => 'amber',
            $s === 'resolved', str_contains($s, 'approved')       => 'green',
            $s === 'closed'                                        => 'slate',
            \in_array($s, ['canceled', 'cancelled'], true),
            str_contains($s, 'reject'), str_contains($s, 'denied') => 'red',
            str_contains($s, 'pending'), str_contains($s, 'waiting') => 'purple',
            default                                                => 'muted',
        };
    }

    /**
     * Badge with the status text, coloured by tone(); "—" when empty.
     */
    public static function badge(string $text, ?string $tone = null): string
    {
        if (trim($text) === '') {
            return '—';
        }

        return '<span class="sdp-badge sdp-tone-' . ($tone ?? self::tone($text)) . '">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }
}
