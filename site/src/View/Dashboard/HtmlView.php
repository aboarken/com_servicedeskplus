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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\DashboardModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

/**
 * Dashboard: figures for each portal page the user may open, each linking to that page with the
 * matching filter. A section that cannot be read shows its error; the others still show.
 */
final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    /**
     * Sections in display order: [title key, error text, cards]; a card is
     * [label, value (null: unknown), url].
     *
     * @var list<array{title: string, error: string, cards: list<array{label: string, value: int|null, url: string}>}>
     */
    public array $sections = [];

    public function display($tpl = null): void
    {
        /** @var DashboardModel $model */
        $model = $this->getModel();

        if ($model->can('sdp.request.view')) {
            $this->section('COM_SERVICEDESKPLUS_DASHBOARD_REQUESTS', function () use ($model): array {
                $cards = [];

                foreach ($model->getRequestCounts() as $status => $count) {
                    $cards[] = $status === ''
                        ? $this->card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ALL_REQUESTS'), $count, 'requests&status=all')
                        : $this->card($status, $count, 'requests&status=' . rawurlencode($status));
                }

                return $cards;
            });
        }

        if ($model->can('sdp.approval.view')) {
            $this->section('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS', function () use ($model): array {
                $decisions = $model->getDecisionCounts();

                return [
                    $this->card(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_PENDING'), $model->getPendingApprovalCount(), 'approvals'),
                    $this->card(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_APPROVED'), $decisions['approved'], 'approvals&show=approved'),
                    $this->card(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_REJECTED'), $decisions['rejected'], 'approvals&show=rejected'),
                ];
            });
        }

        if ($model->can('sdp.asset.view')) {
            $this->section('COM_SERVICEDESKPLUS_DASHBOARD_LOANS', function () use ($model): array {
                $loans = $model->getLoans();
                $now   = time();

                return [
                    $this->card(Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_ALL'), \count($loans), 'assets'),
                    $this->card(Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_DUE_SOON'), \count(array_filter($loans, static fn (LoanAsset $a) => $a->isDueSoon($now))), 'assets&show=due_soon'),
                    $this->card(Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_OVERDUE'), \count(array_filter($loans, static fn (LoanAsset $a) => $a->isOverdue($now))), 'assets&show=overdue'),
                ];
            });
        }

        if ($model->can('sdp.announcement.view')) {
            $this->section('COM_SERVICEDESKPLUS_DASHBOARD_ANNOUNCEMENTS', fn (): array => [
                $this->card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ALL_ANNOUNCEMENTS'), $model->getAnnouncementCount(), 'announcements'),
            ]);
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('dashboard', 'COM_SERVICEDESKPLUS_DASHBOARD_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }

    /**
     * @param  \Closure(): list<array{label: string, value: int|null, url: string}>  $cards
     */
    private function section(string $titleKey, \Closure $cards): void
    {
        try {
            $this->sections[] = ['title' => $titleKey, 'error' => '', 'cards' => $cards()];
        } catch (SdpException $e) {
            $this->sections[] = ['title' => $titleKey, 'error' => Text::_($e->getLanguageKey()), 'cards' => []];
        }
    }

    /**
     * @return array{label: string, value: int|null, url: string}
     */
    private function card(string $label, ?int $value, string $viewAndQuery): array
    {
        return ['label' => $label, 'value' => $value, 'url' => Route::_('index.php?option=com_servicedeskplus&view=' . $viewAndQuery)];
    }
}
