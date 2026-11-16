<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class TransportResponse
{
    /**
     * @param  array{dns: int, connect: int, tls: int, server: int, total: int, reused: bool}|null  $timing
     *         Milliseconds per phase, when the transport measured them: DNS lookup, TCP connect,
     *         TLS handshake, waiting for ServiceDesk Plus (first byte), whole call; reused = an
     *         open connection was used (no connect or handshake).
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?array $timing = null
    ) {
    }
}
