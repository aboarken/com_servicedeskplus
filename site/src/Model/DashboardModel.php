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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;

/**
 * Dashboard figures, each from the service behind its own page (so each follows that page's
 * ownership and visibility rules).
 */
final class DashboardModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Total ('' key) and per status of the My Requests filter; null where SDP gave no count.
     *
     * @return array<string, int|null>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getRequestCounts(): array
    {
        return $this->sdp()->getRequestService()->countsForUser($this->identity());
    }

    /**
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getPendingApprovalCount(): int
    {
        return \count($this->sdp()->getApprovalService()->listPending($this->identity()));
    }

    /** @return array{approved: int|null, rejected: int|null} */
    public function getDecisionCounts(): array
    {
        return $this->sdp()->getApprovalService()->decisionCounts($this->identity());
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
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getAnnouncementCount(): int
    {
        return \count($this->sdp()->getAnnouncementService()->listForUser($this->identity()));
    }

    public function can(string $action): bool
    {
        return Factory::getApplication()->getIdentity()->authorise($action, 'com_servicedeskplus');
    }
}
