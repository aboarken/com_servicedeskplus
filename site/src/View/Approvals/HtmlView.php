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

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ApprovalsModel $model */
        $model = $this->getModel();

        $this->readListInput(self::SHOWS, self::SORTS);
        $this->modules   = $model->getModules();
        $this->type      = ApprovalModule::normalize(Factory::getApplication()->getInput()->getCmd('type', '')) ?? '';
        $this->type      = \in_array($this->type, $this->modules, true) ? $this->type : '';

        if ($this->show === 'pending') {
            try {
                $items = array_values(array_filter(
                    $model->getPending(),
                    fn (PendingApproval $p): bool => ($this->type === '' || $p->module === $this->type)
                        && ($this->search === '' || $p->requestId === $this->search || self::matches($this->search, $p->subject, $p->requester))
                ));
                usort($items, fn (PendingApproval $a, PendingApproval $b): int => $this->sort === 'date_asc'
                    ? [$a->sentOn ?? 0, (int) $a->requestId] <=> [$b->sentOn ?? 0, (int) $b->requestId]
                    : [$b->sentOn ?? 0, (int) $b->requestId] <=> [$a->sentOn ?? 0, (int) $a->requestId]);
                $this->pending = $this->pageOf($items);
            } catch (SdpException $e) {
                $this->error = Text::_($e->getLanguageKey());
            }
        } else {
            $items = array_values(array_filter($model->getHistory(), function (array $h): bool {
                $module = ApprovalModule::normalize((string) ($h['module'] ?? '')) ?? ApprovalModule::REQUEST;
                $ok     = ($h['result'] ?? '') === AuditLogInterface::RESULT_OK;

                return match ($this->show) {
                    'approved' => $ok && ($h['action'] ?? '') === ApprovalService::ACTION_APPROVE,
                    'rejected' => $ok && ($h['action'] ?? '') === ApprovalService::ACTION_REJECT,
                    default    => true,
                } && ($this->type === '' || $module === $this->type)
                  && ($this->search === '' || (string) ($h['sdp_request_id'] ?? '') === $this->search || self::matches($this->search, (string) ($h['subject'] ?? '')));
            }));

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

    public function pageUrl(int $page): string
    {
        return $this->listUrl('approvals', $page, 'pending', 'date_desc', ['type' => $this->type]);
    }
}
