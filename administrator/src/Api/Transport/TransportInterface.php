<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Minimal HTTP transport used by SdpClient. Kept small so tests can replace it with a fake.
 */
interface TransportInterface
{
    /**
     * @param  array<string, string>  $headers
     *
     * @throws TransportException on network failure (DNS, TLS, timeout, connection refused)
     */
    public function send(string $method, string $url, array $headers, ?string $body): TransportResponse;
}
