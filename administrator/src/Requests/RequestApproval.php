<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * One approver's entry on an approval level of a request, as shown on the request page.
 */
final class RequestApproval
{
    public function __construct(
        public readonly int $levelNumber,
        public readonly string $levelName,
        public readonly string $levelStatus,
        public readonly bool $isCurrentLevel,
        public readonly string $approver,
        public readonly string $status,
        public readonly ?int $sentOn,
        public readonly ?int $actedOn,
        public readonly string $comments
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
            (int) ($a['levelNumber'] ?? 0),
            (string) ($a['levelName'] ?? ''),
            (string) ($a['levelStatus'] ?? ''),
            (bool) ($a['isCurrentLevel'] ?? false),
            (string) ($a['approver'] ?? ''),
            (string) ($a['status'] ?? ''),
            isset($a['sentOn']) ? (int) $a['sentOn'] : null,
            isset($a['actedOn']) ? (int) $a['actedOn'] : null,
            (string) ($a['comments'] ?? '')
        );
    }
}
