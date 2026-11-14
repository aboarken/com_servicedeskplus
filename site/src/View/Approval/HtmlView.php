<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Approval;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalDetail;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalNotFound;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\ApprovalModel;

final class HtmlView extends BaseHtmlView
{
    public ?ApprovalDetail $item = null;

    public bool $canDecide = false;

    public string $submissionToken = '';

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var ApprovalModel $model */
        $model = $this->getModel();

        try {
            $this->item = $model->getItem();
        } catch (ApprovalNotFound) {
            Factory::getApplication()->setHeader('status', '404', true);
            $this->error = Text::_('COM_SERVICEDESKPLUS_APPROVAL_NOT_FOUND');
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $this->canDecide = $model->canDecide();

        if ($this->item !== null && $this->item->pending && $this->canDecide) {
            $this->submissionToken = $model->issueSubmissionToken();
        }

        $document = $this->getDocument();
        $document->setTitle(Text::_('COM_SERVICEDESKPLUS_APPROVAL_TITLE'));
        $document->setMetaData('robots', 'noindex, nofollow');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $wa->useScript('com_servicedeskplus.submit-once');

        parent::display($tpl);
    }
}
