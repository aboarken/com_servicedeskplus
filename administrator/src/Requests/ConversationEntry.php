<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One e-mail or note of a request's conversation, as plain text.
 */
final class ConversationEntry
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_NOTE  = 'note';

    public function __construct(
        public readonly string $type,
        public readonly string $from,
        public readonly ?int $time,
        public readonly string $subject,
        public readonly string $text
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) ($a['type'] ?? self::TYPE_EMAIL),
            (string) ($a['from'] ?? ''),
            isset($a['time']) ? (int) $a['time'] : null,
            (string) ($a['subject'] ?? ''),
            (string) ($a['text'] ?? '')
        );
    }
}
