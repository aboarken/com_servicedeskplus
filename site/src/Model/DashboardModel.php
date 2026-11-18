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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Users\UserProfile;

/**
 * Dashboard figures, each from the service behind its own page (so each follows that page's
 * ownership and visibility rules).
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
     * Statuses of the My Requests filter (Integration Settings), as spelled in ServiceDesk Plus.
     *
     * @return list<string>
     */
    public function getRequestStatuses(): array
    {
        return $this->sdp()->getSettingsRepository()->load()->requestStatuses();
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

    /** @return array{approved: int|null, rejected: int|null} */
    public function getDecisionCounts(): array
    {
        return $this->sdp()->getApprovalService()->decisionCounts($this->identity());
    }

    /**
     * Modules My Approvals checks (Integration Settings).
     *
     * @return list<string>
     */
    public function getApprovalModules(): array
    {
        return $this->sdp()->getSettingsRepository()->load()->approvalModules();
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
     * The user's own name, employee ID, department and job title; null when not available.
     */
    public function getProfile(): ?UserProfile
    {
        return $this->sdp()->getUserProfileService()->forUser($this->identity());
    }

    public function can(string $action): bool
    {
        return Factory::getApplication()->getIdentity()->authorise($action, 'com_servicedeskplus');
    }
}
