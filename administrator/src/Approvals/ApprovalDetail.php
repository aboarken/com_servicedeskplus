<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class ApprovalDetail
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $levelId,
        public readonly string $approvalId,
        public readonly string $subject,
        public readonly string $description,
        public readonly string $requester,
        public readonly ?int $sentOn,
        public readonly string $comments,
        public readonly string $status,
        public readonly bool $pending
    ) {
    }
}
