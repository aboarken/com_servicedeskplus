<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Uri\Uri;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\UserMapping;

/**
 * Every site page passes through here:
 *  1. login required,
 *  2. a known view and the Joomla ACL permission for its feature,
 *  3. a matched SDP identity (otherwise only the "could not be matched" notice).
 *
 * The Dashboard and the lists are shown at once: when the user's cached ServiceDesk Plus results
 * are older than the cache time (but at most a few days), they are shown as they are, with
 * "Updating…", and the page asks itself again in the background (sdp_fresh=1) and replaces its
 * content. Pages of one record are never shown from results older than the cache time.
 */
final class DisplayController extends BaseController
{
    use IdentityGuardTrait;

    /** view => required permission; a list means any one of them */
    private const VIEWS = [
        'dashboard'     => ['sdp.request.view', 'sdp.approval.view', 'sdp.asset.view'],
        'requests'      => 'sdp.request.view',
        'request'       => 'sdp.request.view',
        'requestform'   => 'sdp.request.create',
        'assets'        => 'sdp.asset.view',
        'asset'         => 'sdp.asset.view',
        'approvals'     => 'sdp.approval.view',
        'approval'      => 'sdp.approval.view',
        'announcements' => 'sdp.announcement.view',
        'announcement'  => 'sdp.announcement.view',
    ];

    /**
     * Views that may be shown with older results while they refresh: the Dashboard and the lists,
     * which only hold the user's own rows. Pages of one record (a request with its conversation, a
     * loan, an approval, an announcement) always check ServiceDesk Plus once the cache time has
     * passed, so a record that is no longer the user's is never shown from an older copy.
     */
    private const STALE_VIEWS = ['dashboard', 'requests', 'approvals', 'assets', 'announcements'];

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

        $user    = $this->app->getIdentity();
        $allowed = array_filter((array) self::VIEWS[$view], static fn (string $action): bool => $user->authorise($action, 'com_servicedeskplus'));

        if ($allowed === []) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        // Normal page loads may show older results while refreshing; the refresh itself may not.
        $fresh = $this->input->getBool('sdp_fresh', false);
        $cache = $this->sdpComponent()->getUserCache();
        $cache->allowStale(!$fresh && \in_array($view, self::STALE_VIEWS, true) && $this->input->getCmd('format', 'html') === 'html');

        try {
            $identity = $this->currentIdentity();
        } catch (SdpException) {
            $this->input->set('view', 'unmatched');
            $this->input->set('layout', 'unavailable');

            return parent::display(false, $urlparams);
        }

        if (!$identity->isMatched()) {
            $this->input->set('view', 'unmatched');
            $this->input->set('layout', $identity->status === UserMapping::STATUS_PENDING ? 'pending' : 'default');
        }

        // Never cached: every page is per user.
        $result = parent::display(false, $urlparams);
        $since  = $cache->staleSince();

        if ($since !== null && !$fresh) {
            $this->refreshInBackground($since);
        }

        return $result;
    }

    /**
     * Ask the page for itself again (content only, no older results) and replace it: see
     * media/com_servicedeskplus/js/refresh.js.
     */
    private function refreshInBackground(int $since): void
    {
        // Path and query of this page on this site only: never a "//host" (protocol-relative) path.
        $page = new Uri('/' . ltrim(Uri::getInstance()->toString(['path', 'query']), '/'));
        $page->setVar('sdp_fresh', '1');
        $part = clone $page;
        $part->setVar('tmpl', 'component');

        $document = $this->app->getDocument();
        $document->addScriptOptions('com_servicedeskplus.refresh', [
            'url'   => $part->toString(['path', 'query']),
            'page'  => $page->toString(['path', 'query']),
            'since' => HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $since), Text::_('DATE_FORMAT_LC2')),
        ]);

        // Without JavaScript nothing would replace the older results: reload the page for current
        // ones at once (the reloaded page is never served from older results, so this runs once).
        $document->addCustomTag('<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($page->toString(['path', 'query']), ENT_QUOTES, 'UTF-8') . '"></noscript>');

        Text::script('COM_SERVICEDESKPLUS_REFRESH_UPDATING');
        Text::script('COM_SERVICEDESKPLUS_REFRESH_FAILED');
        Text::script('COM_SERVICEDESKPLUS_REFRESH_READY');
        Text::script('COM_SERVICEDESKPLUS_REFRESH_RELOAD');

        $assets = $document->getWebAssetManager();
        $assets->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $assets->useScript('com_servicedeskplus.refresh');
    }
}
