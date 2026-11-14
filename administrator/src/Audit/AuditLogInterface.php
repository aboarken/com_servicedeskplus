<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Audit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Writes to #__sdp_audit. Implementations must never throw: a failed audit write is logged.
 */
interface AuditLogInterface
{
    public const RESULT_OK     = 'ok';
    public const RESULT_DENIED = 'denied';
    public const RESULT_ERROR  = 'error';

    /**
     * @param  int          $userId       Joomla user the action concerns
     * @param  string       $action       e.g. identity.matched, request.create, approval.approve
     * @param  string       $result       ok | denied | error
     * @param  string       $detail       Short, secret-free text (max 255 chars)
     * @param  int          $actorId      Joomla user who performed the action (admin), 0 = same as user
     */
    public function record(
        int $userId,
        string $action,
        string $result,
        ?string $sdpRequestId = null,
        ?string $approvalId = null,
        string $detail = '',
        int $actorId = 0
    ): void;
}
