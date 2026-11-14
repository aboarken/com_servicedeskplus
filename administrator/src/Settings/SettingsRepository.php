<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Settings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Reads and writes #__sdp_settings (name/value rows).
 */
final class SettingsRepository
{
    private ?Settings $cache = null;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function load(): Settings
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['name', 'value']))
            ->from($this->db->quoteName('#__sdp_settings'));

        $values = [];

        foreach ($this->db->setQuery($query)->loadObjectList() as $row) {
            if (\array_key_exists($row->name, Settings::DEFAULTS)) {
                $values[$row->name] = (string) $row->value;
            }
        }

        return $this->cache = new Settings($values);
    }

    /**
     * Upsert the given settings. Unknown names are ignored.
     *
     * @param  array<string, string>  $values
     */
    public function save(array $values): void
    {
        $values = array_intersect_key($values, Settings::DEFAULTS);

        if ($values === []) {
            return;
        }

        $names = array_keys($values);

        $this->db->transactionStart();

        try {
            $delete = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__sdp_settings'))
                ->whereIn($this->db->quoteName('name'), $names, ParameterType::STRING);
            $this->db->setQuery($delete)->execute();

            foreach ($values as $name => $value) {
                $row = (object) ['name' => $name, 'value' => (string) $value];
                $this->db->insertObject('#__sdp_settings', $row);
            }

            $this->db->transactionCommit();
        } catch (\Throwable $e) {
            $this->db->transactionRollback();

            throw $e;
        }

        $this->cache = null;
    }
}
