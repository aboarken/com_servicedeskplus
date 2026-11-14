<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Support;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * SDP stores descriptions and resolutions as HTML written by many people. The portal never
 * renders that HTML: it is converted to plain text and escaped on output.
 */
final class HtmlText
{
    public static function toPlain(string $html): string
    {
        $text = preg_replace('#<(script|style|head|title)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? '';
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? '';
        $text = preg_replace('#<li\b[^>]*>#i', '• ', $text) ?? '';
        $text = preg_replace('#</(p|div|li|tr|h[1-6]|blockquote|pre|table|ul|ol)\s*>#i', "\n", $text) ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';

        return trim($text);
    }

    /**
     * Plain text typed by the user, made safe to store as SDP HTML.
     */
    public static function fromPlain(string $text): string
    {
        return nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'), false);
    }
}
