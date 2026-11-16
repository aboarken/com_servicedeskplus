<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A transport that can send several reads at once. Only GET requests are sent this way: a write
 * is never batched, so its order and its single attempt stay as they are.
 */
interface ParallelTransportInterface extends TransportInterface
{
    /**
     * Send GET requests, at most $concurrency at a time.
     *
     * @param  array<int|string, array{0: string, 1: array<string, string>}>  $requests  key => [url, headers]
     *
     * @return array<int|string, TransportResponse|TransportException>  Same keys; a failed connection
     *                                                                   gives a TransportException, never a throw
     */
    public function getAll(array $requests, int $concurrency): array;
}
