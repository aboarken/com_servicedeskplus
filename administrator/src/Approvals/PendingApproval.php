<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class PendingApproval
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $levelId,
        public readonly string $approvalId,
        public readonly string $subject,
        public readonly string $requester,
        public readonly ?int $sentOn,
        public readonly string $level
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
        return new self(...$a);
    }
}
