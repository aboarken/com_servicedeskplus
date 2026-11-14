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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\PendingApproval;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\ApprovalsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    /** @var list<PendingApproval> */
    public array $pending = [];

    /** @var list<array<string, mixed>> */
    public array $history = [];

    public bool $canDecide = false;

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ApprovalsModel $model */
        $model = $this->getModel();

        $this->canDecide = Factory::getApplication()->getIdentity()->authorise('sdp.approval.decide', 'com_servicedeskplus');
        $this->history   = $model->getHistory();

        try {
            $this->pending = $model->getPending();
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('approvals', 'COM_SERVICEDESKPLUS_APPROVALS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
