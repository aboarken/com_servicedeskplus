<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\PendingApproval;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestStats;

/**
 * "My Dashboard": counts from the lists the portal already reads (the user's requests, pending
 * approvals and loans). Each part follows the same permission as its own page.
 */
final class DashboardModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getRequestStats(): RequestStats
    {
        return $this->sdp()->getRequestService()->statsForUser($this->identity());
    }

    /**
     * @return list<PendingApproval>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getPendingApprovals(): array
    {
        return $this->sdp()->getApprovalService()->listPending($this->identity());
    }

    /**
     * @return list<LoanAsset>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getLoans(): array
    {
        return $this->sdp()->getAssetService()->listForUser($this->identity());
    }

    /**
     * Modules whose approvals My Approvals checks (Integration Settings).
     *
     * @return list<string>
     */
    public function getApprovalModules(): array
    {
        return $this->sdp()->getSettingsRepository()->load()->approvalModules();
    }

    public function can(string $action): bool
    {
        return Factory::getApplication()->getIdentity()->authorise($action, 'com_servicedeskplus');
    }
}
