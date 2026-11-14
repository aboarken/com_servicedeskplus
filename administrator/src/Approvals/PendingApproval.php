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
        public readonly string $level,
        public readonly string $module = ApprovalModule::REQUEST
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
        // Entries cached by an older version have no "module": they are requests.
        $names = ['requestId', 'levelId', 'approvalId', 'subject', 'requester', 'sentOn', 'level', 'module'];

        return new self(...array_intersect_key($a, array_flip($names)));
    }
}
