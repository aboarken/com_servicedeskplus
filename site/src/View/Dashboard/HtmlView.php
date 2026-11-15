<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestStats;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\DashboardModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

/**
 * Dashboard: summary cards, then requests by status and type, recent requests, approvals by
 * type and decisions, loans and the latest announcements. Each part follows the permission of
 * its own page and fails on its own.
 */
final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    public bool $showRequests = false;

    public ?RequestStats $requests = null;

    public string $requestsError = '';

    /** @var array<string, string> request bucket => status name of the My Requests filter */
    public array $statusNames = [];

    public bool $canCreate = false;

    public bool $showApprovals = false;

    /** @var array<string, int> module => waiting approvals, plus "total" */
    public array $approvals = [];

    /** @var list<string> */
    public array $approvalModules = [];

    /** @var array{approved: int|null, rejected: int|null} */
    public array $decisions = ['approved' => null, 'rejected' => null];

    public string $approvalsError = '';

    public bool $showLoans = false;

    public int $loansTotal = 0;

    public int $loansDueSoon = 0;

    public int $loansOverdue = 0;

    public string $loansError = '';

    public bool $showAnnouncements = false;

    /** @var list<Announcement> Newest announcements */
    public array $announcements = [];

    public int $announcementsTotal = 0;

    public string $announcementsError = '';

    public function display($tpl = null): void
    {
        /** @var DashboardModel $model */
        $model = $this->getModel();

        $this->showRequests      = $model->can('sdp.request.view');
        $this->canCreate         = $model->can('sdp.request.create');
        $this->showApprovals     = $model->can('sdp.approval.view');
        $this->showLoans         = $model->can('sdp.asset.view');
        $this->showAnnouncements = $model->can('sdp.announcement.view');

        // Each part fails on its own: one unavailable list does not hide the others.
        if ($this->showRequests) {
            try {
                $this->requests = $model->getRequestStats();

                foreach ($model->getRequestStatuses() as $status) {
                    $this->statusNames[RequestStats::bucket($status)] ??= $status;
                }
            } catch (SdpException $e) {
                $this->requestsError = Text::_($e->getLanguageKey());
            }
        }

        if ($this->showApprovals) {
            $this->decisions = $model->getDecisionCounts();

            try {
                $this->approvalModules = $model->getApprovalModules();
                $this->approvals       = array_fill_keys([...ApprovalModule::ALL, 'total'], 0);

                foreach ($model->getPendingApprovals() as $approval) {
                    $this->approvals[$approval->module] = ($this->approvals[$approval->module] ?? 0) + 1;
                    $this->approvals['total']++;
                }
            } catch (SdpException $e) {
                $this->approvalsError = Text::_($e->getLanguageKey());
            }
        }

        if ($this->showLoans) {
            try {
                $now = time();

                foreach ($model->getLoans() as $loan) {
                    $this->loansTotal++;
                    $this->loansDueSoon += $loan->isDueSoon($now) ? 1 : 0;
                    $this->loansOverdue += $loan->isOverdue($now) ? 1 : 0;
                }
            } catch (SdpException $e) {
                $this->loansError = Text::_($e->getLanguageKey());
            }
        }

        if ($this->showAnnouncements) {
            try {
                $all                      = $model->getAnnouncements();
                $this->announcementsTotal = \count($all);
                $this->announcements      = \array_slice($all, 0, 5);
            } catch (SdpException $e) {
                $this->announcementsError = Text::_($e->getLanguageKey());
            }
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('dashboard', 'COM_SERVICEDESKPLUS_DASHBOARD_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }

    /**
     * Link to a portal page, e.g. url('requests&status=all').
     */
    public function url(string $viewAndQuery): string
    {
        return Route::_('index.php?option=com_servicedeskplus&view=' . $viewAndQuery);
    }

    /**
     * My Requests filtered on the status of a bucket, or all statuses when the filter has no
     * status for it.
     */
    public function statusUrl(string $bucket): string
    {
        return isset($this->statusNames[$bucket])
            ? $this->url('requests&status=' . rawurlencode($this->statusNames[$bucket]))
            : $this->url('requests&status=all');
    }
}
