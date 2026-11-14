<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Result of creating a request: its id, and the names of any files that could not be attached.
 */
final class CreatedRequest
{
    /** @param list<string> $failedAttachments */
    public function __construct(
        public readonly string $id,
        public readonly array $failedAttachments = []
    ) {
    }
}
