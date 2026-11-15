<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

final class DisplayController extends BaseController
{
    protected $default_view = 'integrationsettings';

    /** Views renamed in 1.11.0: old links and bookmarks are sent to the new ones. */
    private const RENAMED_VIEWS = [
        'settings' => 'integrationsettings',
        'usermaps' => 'usermappings',
    ];

    public function display($cachable = false, $urlparams = [])
    {
        // Settings and identity mappings are security-sensitive: super-user level permission only.
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_servicedeskplus')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $view = $this->input->getCmd('view', $this->default_view);

        if (isset(self::RENAMED_VIEWS[$view])) {
            $this->setRedirect(Route::_('index.php?option=com_servicedeskplus&view=' . self::RENAMED_VIEWS[$view], false));

            return $this;
        }

        return parent::display(false, $urlparams);
    }
}
