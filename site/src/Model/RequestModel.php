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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestDetail;

final class RequestModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Ownership is checked inside RequestService::getForUser() on every call (IDOR protection).
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestNotFound
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItem(): RequestDetail
    {
        $id = Factory::getApplication()->getInput()->getString('id', '');

        return $this->sdp()->getRequestService()->getForUser($this->identity(), $id);
    }

    /**
     * Conversation visible to the requester. Call only after getItem() confirmed the user owns it.
     *
     * @return list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\ConversationEntry>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getConversation(string $requestId): array
    {
        return $this->sdp()->getConversationReader()->forRequest($this->identity(), $requestId);
    }
}
