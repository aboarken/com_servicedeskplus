<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;

/**
 * Every site page passes through here:
 *  1. login required,
 *  2. a known view and the Joomla ACL permission for its feature,
 *  3. a matched SDP identity (otherwise only the "could not be matched" notice).
 */
final class DisplayController extends BaseController
{
    use IdentityGuardTrait;

    /** view => required permission */
    private const VIEWS = [
        'dashboard'   => 'sdp.request.view',
        'requests'    => 'sdp.request.view',
        'request'     => 'sdp.request.view',
        'requestform' => 'sdp.request.create',
        'assets'      => 'sdp.asset.view',
        'asset'       => 'sdp.asset.view',
        'approvals'   => 'sdp.approval.view',
        'approval'    => 'sdp.approval.view',
    ];

    protected $default_view = 'requests';

    public function display($cachable = false, $urlparams = [])
    {
        if (!$this->requireLogin()) {
            return $this;
        }

        $view = $this->input->getCmd('view', $this->default_view);

        if (!isset(self::VIEWS[$view])) {
            throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        if (!$this->app->getIdentity()->authorise(self::VIEWS[$view], 'com_servicedeskplus')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $identity = $this->currentIdentity();
        } catch (SdpException) {
            $this->input->set('view', 'unmatched');
            $this->input->set('layout', 'unavailable');

            return parent::display(false, $urlparams);
        }

        if (!$identity->isMatched()) {
            $this->input->set('view', 'unmatched');
            $this->input->set('layout', 'default');
        }

        // Never cached: every page is per user.
        return parent::display(false, $urlparams);
    }
}
