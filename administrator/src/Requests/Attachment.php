<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * An uploaded file that passed AttachmentValidator: safe name, type taken from the checked content.
 */
final class Attachment
{
    public function __construct(
        public readonly string $name,
        public readonly string $contentType,
        public readonly string $path,
        public readonly int $size
    ) {
    }
}
