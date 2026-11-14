<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Extension\ServiceDeskPlusComponent;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;

/**
 * Shared by all site controllers. The SDP identity always comes from the server-side mapping.
 *
 * Requires $this->app (CMSApplication) and setRedirect() from BaseController.
 */
trait IdentityGuardTrait
{
    /**
     * Sends guests to the login page.
     *
     * @return bool  false when a redirect was set
     */
    protected function requireLogin(): bool
    {
        $user = $this->app->getIdentity();

        if ($user !== null && !$user->guest) {
            return true;
        }

        $return = base64_encode(Uri::getInstance()->toString(['path', 'query', 'fragment']));
        $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_LOGIN_REQUIRED'), 'warning');
        $this->setRedirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));

        return false;
    }

    /**
     * Resolve the current user. Call requireLogin() first.
     *
     * @throws SdpException when SDP is needed for matching and is unavailable
     */
    protected function currentIdentity(): Identity
    {
        $user = $this->app->getIdentity();

        return $this->sdpComponent()->getIdentityResolver()->resolve((int) $user->id, (string) $user->email);
    }

    protected function sdpComponent(): ServiceDeskPlusComponent
    {
        $component = $this->app->bootComponent('com_servicedeskplus');

        if (!$component instanceof ServiceDeskPlusComponent) {
            throw new \LogicException('Unexpected component class');
        }

        return $component;
    }
}
