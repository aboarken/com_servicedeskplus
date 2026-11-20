<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalService;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\PendingApproval;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditLogInterface;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\ApprovalsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\ListPageTrait;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

/**
 * My Approvals: one list at a time, chosen with "Show" (waiting for me, approved by me, rejected
 * by me, all my decisions), with search, type filter, sort and pages, like My Requests.
 */
final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;
    use ListPageTrait;

    public const SHOWS = ['pending', 'approved', 'rejected', 'decided'];

    public const SORTS = ['date_desc', 'date_asc'];

    /** @var list<PendingApproval> Current page when showing approvals waiting for the user */
    public array $pending = [];

    /** @var list<array<string, mixed>> Current page when showing decisions */
    public array $history = [];

    /** @var list<string> Modules offered in the type filter */
    public array $modules = [];

    public string $type = '';

    /** @var array<string, int> Cards above the list: module => total for the current "Show" ('' = all types) */
    public array $totals = [];

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ApprovalsModel $model */
        $model = $this->getModel();

        $this->readListInput(self::SHOWS, self::SORTS);
        $this->modules   = $model->getModules();
        // No type chosen = all types. (normalize() reads an empty module as "request", which is
        // right for old decisions without a module but not for this filter.)
        $type            = Factory::getApplication()->getInput()->getCmd('type', '');
        $this->type      = $type === '' ? '' : (ApprovalModule::normalize($type) ?? '');
        $this->type      = \in_array($this->type, $this->modules, true) ? $this->type : '';

        if ($this->show === 'pending') {
            try {
                $all = array_values(array_filter(
                    $model->getPending(),
                    fn (PendingApproval $p): bool => $this->search === '' || $p->requestId === $this->search || self::matches($this->search, $p->subject, $p->requester)
                ));
                $this->countTotals(array_map(static fn (PendingApproval $p): string => $p->module, $all));
                $items = array_values(array_filter($all, fn (PendingApproval $p): bool => $this->type === '' || $p->module === $this->type));
                usort($items, fn (PendingApproval $a, PendingApproval $b): int => $this->sort === 'date_asc'
                    ? [$a->sentOn ?? 0, (int) $a->requestId] <=> [$b->sentOn ?? 0, (int) $b->requestId]
                    : [$b->sentOn ?? 0, (int) $b->requestId] <=> [$a->sentOn ?? 0, (int) $a->requestId]);
                $this->pending = $this->pageOf($items);
            } catch (SdpException $e) {
                $this->error = Text::_($e->getLanguageKey());
            }
        } else {
            // Decisions recorded before 1.10.0 have no module (they were all requests).
            $moduleOf = static fn (array $h): string => ApprovalModule::normalize((string) ($h['module'] ?? '')) ?: ApprovalModule::REQUEST;

            $all = array_values(array_filter($model->getHistory(), function (array $h): bool {
                $ok = ($h['result'] ?? '') === AuditLogInterface::RESULT_OK;

                return match ($this->show) {
                    'approved' => $ok && ($h['action'] ?? '') === ApprovalService::ACTION_APPROVE,
                    'rejected' => $ok && ($h['action'] ?? '') === ApprovalService::ACTION_REJECT,
                    default    => true,
                } && ($this->search === '' || (string) ($h['sdp_request_id'] ?? '') === $this->search || self::matches($this->search, (string) ($h['subject'] ?? '')));
            }));
            $this->countTotals(array_map($moduleOf, $all));
            $items = array_values(array_filter($all, fn (array $h): bool => $this->type === '' || $moduleOf($h) === $this->type));

            // Newest first as read; oldest first on request.
            if ($this->sort === 'date_asc') {
                $items = array_reverse($items);
            }

            $this->history = $this->pageOf($items);
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('approvals', 'COM_SERVICEDESKPLUS_APPROVALS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal')->useScript('com_servicedeskplus.filters');

        parent::display($tpl);
    }

    /**
     * Totals of the type cards: all types, then each module checked in Integration Settings.
     *
     * @param  list<string>  $modules  module of each row of the current "Show"
     */
    private function countTotals(array $modules): void
    {
        $this->totals = ['' => \count($modules)] + array_fill_keys($this->modules, 0);

        foreach ($modules as $module) {
            if (isset($this->totals[$module]) && $module !== '') {
                $this->totals[$module]++;
            }
        }
    }

    /** Link to the list with the current "Show", search and sort, and the given type. */
    public function typeUrl(string $type): string
    {
        $params = array_filter([
            'show'   => $this->show !== 'pending' ? $this->show : '',
            'search' => $this->search,
            'sort'   => $this->sort !== 'date_desc' ? $this->sort : '',
            'type'   => $type,
        ], static fn (string $v): bool => $v !== '');

        return Route::_('index.php?option=com_servicedeskplus&view=approvals' . ($params !== [] ? '&' . http_build_query($params) : ''));
    }

    public function pageUrl(int $page): string
    {
        return $this->listUrl('approvals', $page, 'pending', 'date_desc', ['type' => $this->type]);
    }
}
