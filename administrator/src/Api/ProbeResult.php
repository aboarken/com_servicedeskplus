<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a Find Endpoints check read from one path: the response key that held the data, the rows
 * (one row for a single record) and the totals ServiceDesk Plus gave. Kept in memory only.
 */
final class ProbeResult
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public readonly string $key,
        public readonly array $items,
        public readonly bool $single,
        public readonly ?int $totalCount = null,
        public readonly bool $hasMoreRows = false
    ) {
    }
}
