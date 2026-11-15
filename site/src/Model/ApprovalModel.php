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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;

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
            $input->getString('id', ''),
            $input->getCmd('module', '') ?: ApprovalModule::REQUEST
        );
    }

    /**
     * Conversation of the request being approved (visible-to-requester items only). Call only
     * after getItem() confirmed the user is the approver.
     *
     * @return list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\ConversationEntry>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getConversation(string $requestId): array
    {
        return $this->sdp()->getConversationReader()->forRequest($this->identity(), $requestId);
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
