<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Model\BaseModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;

final class AnnouncementsModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Announcements ServiceDesk Plus shows to this user (see AnnouncementService).
     *
     * @return list<Announcement>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItems(): array
    {
        return $this->sdp()->getAnnouncementService()->listForUser($this->identity());
    }
}
