<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

final class DbUserMapRepository implements UserMapRepositoryInterface
{
    private const TABLE  = '#__sdp_user_map';
    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function find(int $joomlaUserId): ?UserMapping
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName(self::TABLE))
            ->where($this->db->quoteName('joomla_user_id') . ' = :id')
            ->bind(':id', $joomlaUserId, ParameterType::INTEGER);

        $row = $this->db->setQuery($query)->loadObject();

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySdpUserId(string $sdpUserId): ?UserMapping
    {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName(self::TABLE))
            ->where($this->db->quoteName('sdp_user_id') . ' = :sid')
            ->bind(':sid', $sdpUserId, ParameterType::STRING);

        $row = $this->db->setQuery($query, 0, 1)->loadObject();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(UserMapping $mapping): void
    {
        $row = (object) [
            'joomla_user_id' => $mapping->joomlaUserId,
            'sdp_user_id'    => $mapping->sdpUserId,
            'email'          => $mapping->email,
            'matched_at'     => $mapping->matchedAt?->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
            'checked_at'     => $mapping->checkedAt->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT),
            'status'         => $mapping->status,
            'source'         => $mapping->source,
            'modified_by'    => $mapping->modifiedBy,
        ];

        if ($this->find($mapping->joomlaUserId) !== null) {
            $this->db->updateObject(self::TABLE, $row, 'joomla_user_id', true);

            return;
        }

        try {
            $this->db->insertObject(self::TABLE, $row);
        } catch (\RuntimeException $e) {
            // Another request inserted the row first; fall back to an update.
            if ($this->find($mapping->joomlaUserId) === null) {
                throw $e;
            }

            $this->db->updateObject(self::TABLE, $row, 'joomla_user_id', true);
        }
    }

    public function delete(int $joomlaUserId): void
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName(self::TABLE))
            ->where($this->db->quoteName('joomla_user_id') . ' = :id')
            ->bind(':id', $joomlaUserId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    private function hydrate(object $row): UserMapping
    {
        $utc = new \DateTimeZone('UTC');

        return new UserMapping(
            (int) $row->joomla_user_id,
            $row->sdp_user_id !== null && $row->sdp_user_id !== '' ? (string) $row->sdp_user_id : null,
            (string) $row->email,
            (string) $row->status,
            (string) $row->source,
            $row->matched_at ? new \DateTimeImmutable((string) $row->matched_at, $utc) : null,
            new \DateTimeImmutable((string) $row->checked_at, $utc),
            (int) $row->modified_by
        );
    }
}
