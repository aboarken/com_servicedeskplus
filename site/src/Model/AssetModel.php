<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;

final class AssetModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * Assignment and loan rule are re-checked on every call (IDOR protection).
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\AssetNotFound
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItem(): LoanAsset
    {
        $id = Factory::getApplication()->getInput()->getString('id', '');

        return $this->sdp()->getAssetService()->getForUser($this->identity(), $id);
    }
}
