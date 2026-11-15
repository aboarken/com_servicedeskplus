<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Announcements;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AnnouncementsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    /** @var list<Announcement> */
    public array $items = [];

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var AnnouncementsModel $model */
        $model = $this->getModel();

        try {
            $this->items = $model->getItems();
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('announcements', 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }
}
