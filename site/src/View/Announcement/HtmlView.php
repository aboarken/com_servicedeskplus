<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Announcement;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\AnnouncementNotFound;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AnnouncementModel;

final class HtmlView extends BaseHtmlView
{
    public ?Announcement $item = null;

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var AnnouncementModel $model */
        $model = $this->getModel();

        try {
            $this->item = $model->getItem();
            $this->setDocumentTitle($this->item->title !== '' ? $this->item->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_TITLE'));
        } catch (AnnouncementNotFound) {
            // Same answer for "does not exist" and "not shown to you".
            Factory::getApplication()->setHeader('status', '404', true);
            $this->error = Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_NOT_FOUND');
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }
}
