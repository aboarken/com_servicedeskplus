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
            $statuses,
            RequestQuery::PAGE_SIZE,
            $input->getCmd('type', ''),
            $input->getString('category', '')
        );
    }

    /**
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getResult(): RequestListResult
    {
        return $this->sdp()->getRequestService()->listForUser($this->identity(), $this->getQuery());
    }

    /**
     * Open and on-hold requests per category for the cards ('' = all of them), from the same
     * figures as the Dashboard (shared cache); categories without such requests are left out.
     *
     * @return array<string, int>
     *
     * @throws \SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException
     */
    public function getCategoryTotals(): array
    {
        $category = $this->sdp()->getRequestService()->statsForUser($this->identity())->category;
        $totals   = [];

        foreach ($category['named'] ?? [] as $c) {
            $totals[(string) $c['name']] = (int) $c['count'];
        }

        $other = (int) ($category['other'] ?? 0);
        $none  = (int) ($category['none'] ?? 0);
        $all   = array_sum($totals) + $other + $none;

        // Requests without a category get their own card (key "\0none", not a category name).
        return $category === [] ? [] : ['' => $all] + $totals + ($none > 0 ? ["\0none" => $none] : []);
    }

    /**
     * Categories for the category filter: those of ServiceDesk Plus, else those of the cards;
     * the chosen one is always included.
     *
     * @param  array<string, int>  $totals  the cards' categories (keys)
     *
     * @return list<string>
     */
    public function getCategoryNames(array $totals): array
    {
        $names = $this->sdp()->getRequestService()->categoryNames($this->identity());

        if ($names === []) {
            $names = array_values(array_filter(array_map('strval', array_keys($totals)), static fn (string $n): bool => $n !== '' && $n !== "\0none"));
        }

        $chosen = $this->getQuery()->category;

        if ($chosen !== '' && !\in_array($chosen, $names, true)) {
            $names[] = $chosen;
        }

        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /** @return list<string> */
    public function getStatuses(): array
    {
        return $this->sdp()->getRequestService()->allowedStatuses();
    }
}
