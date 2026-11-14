<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Cache;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;

/**
 * Short-lived cache whose keys always include the Joomla and SDP user ids.
 * Only data already checked for ownership may be stored.
 */
interface UserCacheInterface
{
    /** @return array<mixed>|null */
    public function get(Identity $identity, string $key): ?array;

    /** @param array<mixed> $value */
    public function set(Identity $identity, string $key, array $value): void;

    /** Remove every entry of this user whose key starts with $prefix. */
    public function clear(Identity $identity, string $prefix = ''): void;
}
