<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One page of an SDP list response.
 */
final class ListPage
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly bool $hasMoreRows,
        public readonly ?int $totalCount = null
    ) {
    }
}
