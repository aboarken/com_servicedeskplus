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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestListResult;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;

final class RequestsModel extends BaseModel
{
    use SiteServicesTrait;

    private ?RequestQuery $query = null;

    public function getQuery(): RequestQuery
    {
        if ($this->query !== null) {
            return $this->query;
        }

        $input = Factory::getApplication()->getInput()->get;

        [$sort, $direction] = array_pad(explode('_', $input->getCmd('sort', 'created_desc'), 2), 2, 'desc');

        $statuses = $this->getStatuses();

        // No status in the URL (menu link, "Back to My Requests"): show open requests.
        // "All statuses" is sent as status=all.
        $status = RequestQuery::statusFromInput(
            $input->exists('status') ? $input->getString('status', '') : null,
            $statuses
        );

        return $this->query = new RequestQuery(
            $input->getInt('page', 1),
            $input->getString('search', ''),
            $status,
            $sort,
            $direction,
            $statuses
        );
    }

    /**
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getResult(): RequestListResult
    {
        return $this->sdp()->getRequestService()->listForUser($this->identity(), $this->getQuery());
    }

    /** @return list<string> */
    public function getStatuses(): array
    {
        return $this->sdp()->getRequestService()->allowedStatuses();
    }
}
