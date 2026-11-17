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
 * Per-user cache whose keys always include the Joomla and SDP user ids.
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

    /**
     * Whether get() may return an entry older than the cache time (up to a few days), for a page
     * that is shown at once and then refreshed in the background.
     */
    public function allowStale(bool $allow): void;

    /** When the oldest entry get() returned past its cache time was stored (Unix time), or null. */
    public function staleSince(): ?int;
}
