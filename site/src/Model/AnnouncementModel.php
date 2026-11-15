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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;

final class AnnouncementModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Visibility is checked inside AnnouncementService::getForUser() on every call.
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\AnnouncementNotFound
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItem(): Announcement
    {
        $id = Factory::getApplication()->getInput()->getString('id', '');

        return $this->sdp()->getAnnouncementService()->getForUser($this->identity(), $id);
    }
}
