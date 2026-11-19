<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Model\BaseModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;

final class AssetsModel extends BaseModel
{
    use SiteServicesTrait;

    /**
     * @return list<LoanAsset>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getItems(): array
    {
        return $this->sdp()->getAssetService()->listForUser($this->identity());
    }

    /**
     * The user's returned loans, newest first.
     *
     * @return list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\PastLoan>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getPastLoans(): array
    {
        return $this->sdp()->getAssetService()->pastLoansForUser($this->identity());
    }
}
