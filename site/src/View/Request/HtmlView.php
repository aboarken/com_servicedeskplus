<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Request;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestDetail;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestNotFound;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\RequestModel;

final class HtmlView extends BaseHtmlView
{
    public ?RequestDetail $item = null;

    public string $error = '';

    /** @var list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\ConversationEntry> */
    public array $conversation = [];

    /** @var list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestApproval> */
    public array $approvals = [];

    public function display($tpl = null): void
    {
        /** @var RequestModel $model */
        $model = $this->getModel();

        try {
            $this->item = $model->getItem();
            $this->getDocument()->setTitle(Text::sprintf('COM_SERVICEDESKPLUS_REQUEST_TITLE', $this->item->id));
            $this->approvals    = $model->getApprovals($this->item->id);
            $this->conversation = $model->getConversation($this->item->id);
        } catch (RequestNotFound) {
            // Same answer for "does not exist" and "not yours".
            Factory::getApplication()->setHeader('status', '404', true);
            $this->error = Text::_('COM_SERVICEDESKPLUS_REQUEST_NOT_FOUND');
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
        $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $this->getDocument()->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }
}
