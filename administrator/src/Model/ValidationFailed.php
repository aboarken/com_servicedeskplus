<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Form validation failure carrying user-facing (already translated) messages.
 */
final class ValidationFailed extends \RuntimeException
{
    /** @param list<string> $messages */
    public function __construct(private readonly array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }

    /** @return list<string> */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
