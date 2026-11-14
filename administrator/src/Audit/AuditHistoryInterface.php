<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Audit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

interface AuditHistoryInterface
{
    /**
     * Most recent audit rows of one user for the given actions, newest first.
     *
     * @param  list<string>  $actions
     *
     * @return list<array{created: string, action: string, result: string, sdp_request_id: ?string, approval_id: ?string, detail: string}>
     */
    public function recent(int $userId, array $actions, int $limit): array;
}
