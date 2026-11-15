<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\UserMapping;

final class UsermappingsModel extends ListModel
{
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        $config['filter_fields'] ??= [
            'status', 'm.status',
            'u.name', 'm.sdp_user_id', 'm.matched_at', 'm.checked_at',
        ];

        parent::__construct($config, $factory);

        // The filter form keeps its original file name (forms/filter_usermaps.xml).
        $this->filterFormName = 'filter_usermaps';
    }

    protected function populateState($ordering = 'm.checked_at', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search') . ':' . $this->getState('filter.status');

        return parent::getStoreId($id);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'm.*',
                $db->quoteName('u.name'),
                $db->quoteName('u.username'),
                $db->quoteName('u.email', 'joomla_email'),
            ])
            ->from($db->quoteName('#__sdp_user_map', 'm'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('m.joomla_user_id'));

        $status = (string) $this->getState('filter.status');

        if (\in_array($status, UserMapping::STATUSES, true)) {
            $query->where($db->quoteName('m.status') . ' = :status')
                ->bind(':status', $status);
        }

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            if (preg_match('/^[0-9]{1,20}$/', $search)) {
                $query->where('(' . $db->quoteName('m.sdp_user_id') . ' = :sid OR ' . $db->quoteName('m.joomla_user_id') . ' = :jid)')
                    ->bind(':sid', $search)
                    ->bind(':jid', $search, ParameterType::INTEGER);
            } else {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where('(' . implode(' OR ', [
                    $db->quoteName('u.name') . ' LIKE :s1',
                    $db->quoteName('u.username') . ' LIKE :s2',
                    $db->quoteName('u.email') . ' LIKE :s3',
                    $db->quoteName('m.email') . ' LIKE :s4',
                ]) . ')')
                    ->bind(':s1', $like)
                    ->bind(':s2', $like)
                    ->bind(':s3', $like)
                    ->bind(':s4', $like);
            }
        }

        $ordering  = (string) $this->getState('list.ordering', 'm.checked_at');
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        if (!\in_array($ordering, ['u.name', 'm.sdp_user_id', 'm.matched_at', 'm.checked_at', 'm.status'], true)) {
            $ordering = 'm.checked_at';
        }

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        return $query;
    }
}
