<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AssetsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    /** @var list<LoanAsset> */
    public array $items = [];

    public string $error = '';

    public int $now = 0;

    public function display($tpl = null): void
    {
        /** @var AssetsModel $model */
        $model = $this->getModel();

        $this->now = time();

        try {
            $this->items = $model->getItems();
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('assets', 'COM_SERVICEDESKPLUS_ASSETS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }
}
