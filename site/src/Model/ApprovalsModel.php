<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Model\BaseModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\PendingApproval;

final class ApprovalsModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * @return list<PendingApproval>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getPending(): array
    {
        return $this->sdp()->getApprovalService()->listPending($this->identity());
    }

    /** @return list<array<string, mixed>> */
    public function getHistory(): array
    {
        return $this->sdp()->getApprovalService()->history($this->identity());
    }

    /**
     * Modules My Approvals checks (Integration Settings), for the type filter.
     *
     * @return list<string>
     */
    public function getModules(): array
    {
        return $this->sdp()->getSettingsRepository()->load()->approvalModules();
    }
}
