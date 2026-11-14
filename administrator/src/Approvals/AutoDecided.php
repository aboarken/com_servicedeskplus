<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ServiceDesk Plus decided the API technician's added approval by itself, the other way than the
 * user chose (its "Auto-approve ..." settings under Advanced Portal Settings > Approval), so the
 * user's decision was not recorded. The user's own approval is left pending.
 */
final class AutoDecided extends \RuntimeException
{
    public function __construct(public readonly bool $entryRemoved)
    {
        parent::__construct('ServiceDesk Plus decided the added approval by itself');
    }
}
