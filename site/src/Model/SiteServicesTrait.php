<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Extension\ServiceDeskPlusComponent;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;

/**
 * Site models resolve the identity themselves (memoised per request), so no model can be used
 * without a matched SDP user even if a controller check were missed.
 */
trait SiteServicesTrait
{
    protected function identity(): Identity
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user === null || $user->guest) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $identity = $this->sdp()->getIdentityResolver()->resolve((int) $user->id, (string) $user->email);

        if (!$identity->isMatched()) {
            throw new NotAllowed(Text::_('COM_SERVICEDESKPLUS_UNMATCHED'), 403);
        }

        return $identity;
    }

    protected function sdp(): ServiceDeskPlusComponent
    {
        $component = Factory::getApplication()->bootComponent('com_servicedeskplus');

        if (!$component instanceof ServiceDeskPlusComponent) {
            throw new \LogicException('Unexpected component class');
        }

        return $component;
    }
}
