<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Asset;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\AssetNotFound;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AssetModel;

final class HtmlView extends BaseHtmlView
{
    public ?LoanAsset $item = null;

    public string $error = '';

    public int $now = 0;

    public function display($tpl = null): void
    {
        /** @var AssetModel $model */
        $model     = $this->getModel();
        $this->now = time();

        try {
            $this->item = $model->getItem();
            $this->getDocument()->setTitle($this->item->name !== '' ? $this->item->name : Text::_('COM_SERVICEDESKPLUS_ASSETS_TITLE'));
        } catch (AssetNotFound) {
            Factory::getApplication()->setHeader('status', '404', true);
            $this->error = Text::_('COM_SERVICEDESKPLUS_ASSET_NOT_FOUND');
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
