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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestStats;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\DashboardModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    public ?RequestStats $requests = null;

    public string $requestsError = '';

    public bool $showApprovals = false;

    /** @var array<string, int> module => pending approvals, plus "total" */
    public array $approvals = [];

    /** @var list<string> Modules shown in the approvals table */
    public array $approvalModules = [];

    public string $approvalsError = '';

    public bool $showLoans = false;

    public int $loansTotal = 0;

    public int $loansOverdue = 0;

    public string $loansError = '';

    public bool $canCreate = false;

    public function display($tpl = null): void
    {
        /** @var DashboardModel $model */
        $model = $this->getModel();

        // Each part fails on its own: one unavailable list does not hide the others.
        try {
            $this->requests = $model->getRequestStats();
        } catch (SdpException $e) {
            $this->requestsError = Text::_($e->getLanguageKey());
        }

        $this->canCreate     = $model->can('sdp.request.create');
        $this->showApprovals = $model->can('sdp.approval.view');
        $this->showLoans     = $model->can('sdp.asset.view');

        if ($this->showApprovals) {
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
                    $this->loansOverdue += $loan->isOverdue($now) ? 1 : 0;
                }
            } catch (SdpException $e) {
                $this->loansError = Text::_($e->getLanguageKey());
            }
        }

        $this->prepareMenuPage('dashboard', 'COM_SERVICEDESKPLUS_DASHBOARD_TITLE');
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
        $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $this->getDocument()->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }
}
