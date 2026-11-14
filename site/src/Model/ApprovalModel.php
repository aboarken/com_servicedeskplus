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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalDetail;

final class ApprovalModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Approver is re-checked against SDP on every call.
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalNotFound
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItem(): ApprovalDetail
    {
        $input = Factory::getApplication()->getInput();

        return $this->sdp()->getApprovalService()->getForUser(
            $this->identity(),
            $input->getString('request_id', ''),
            $input->getString('level_id', ''),
            $input->getString('id', '')
        );
    }

    public function canDecide(): bool
    {
        return Factory::getApplication()->getIdentity()->authorise('sdp.approval.decide', 'com_servicedeskplus');
    }

    public function issueSubmissionToken(): string
    {
        return $this->sdp()->getSubmissionGuard()->issue();
    }
}
