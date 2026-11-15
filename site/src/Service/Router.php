<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * SEF router. Ids in URLs are SDP ids; they are never trusted for access (models re-check ownership).
 */
final class Router extends RouterView
{
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $requests = new RouterViewConfiguration('requests');
        $this->registerView($requests);

        $request = new RouterViewConfiguration('request');
        $request->setKey('id')->setParent($requests);
        $this->registerView($request);

        $this->registerView(new RouterViewConfiguration('requestform'));

        $assets = new RouterViewConfiguration('assets');
        $this->registerView($assets);

        $asset = new RouterViewConfiguration('asset');
        $asset->setKey('id')->setParent($assets);
        $this->registerView($asset);

        $approvals = new RouterViewConfiguration('approvals');
        $this->registerView($approvals);

        // request_id and level_id stay in the query string.
        $approval = new RouterViewConfiguration('approval');
        $approval->setKey('id')->setParent($approvals);
        $this->registerView($approval);

        $announcements = new RouterViewConfiguration('announcements');
        $this->registerView($announcements);

        $announcement = new RouterViewConfiguration('announcement');
        $announcement->setKey('id')->setParent($announcements);
        $this->registerView($announcement);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @return array<string, string>
     */
    public function getRequestSegment($id, $query): array
    {
        $id = (string) $id;

        return preg_match('/^[0-9]{1,20}$/', $id) ? [$id => $id] : [];
    }

    public function getRequestId($segment, $query): string|false
    {
        return preg_match('/^[0-9]{1,20}$/', (string) $segment) ? (string) $segment : false;
    }

    /**
     * @return array<string, string>
     */
    public function getAssetSegment($id, $query): array
    {
        return $this->getRequestSegment($id, $query);
    }

    public function getAssetId($segment, $query): string|false
    {
        return $this->getRequestId($segment, $query);
    }

    /**
     * @return array<string, string>
     */
    public function getApprovalSegment($id, $query): array
    {
        return $this->getRequestSegment($id, $query);
    }

    public function getApprovalId($segment, $query): string|false
    {
        return $this->getRequestId($segment, $query);
    }

    /**
     * @return array<string, string>
     */
    public function getAnnouncementSegment($id, $query): array
    {
        return $this->getRequestSegment($id, $query);
    }

    public function getAnnouncementId($segment, $query): string|false
    {
        return $this->getRequestId($segment, $query);
    }
}
