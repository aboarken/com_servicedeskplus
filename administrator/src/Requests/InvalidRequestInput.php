<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Create-form validation failure. Carries language keys.
 */
final class InvalidRequestInput extends \RuntimeException
{
    /** @param list<string> $keys */
    public function __construct(private readonly array $keys)
    {
        parent::__construct(implode(', ', $keys));
    }

    /** @return list<string> */
    public function getKeys(): array
    {
        return $this->keys;
    }
}
