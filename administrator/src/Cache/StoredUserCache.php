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
 * Per-user cache over a simple array store. In Joomla the store is the user's own session, so
 * entries are never shared between users and disappear at logout.
 */
final class StoredUserCache implements UserCacheInterface
{
    private const MAX_ENTRIES = 30;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param  \Closure(): array<string, mixed>   $load
     * @param  \Closure(array<string, mixed>): void $save
     * @param  int                                 $ttl  Seconds; 0 disables the cache
     */
    public function __construct(
        private readonly \Closure $load,
        private readonly \Closure $save,
        private readonly int $ttl = 60,
        ?\Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function get(Identity $identity, string $key): ?array
    {
        if ($this->ttl <= 0 || !$identity->isMatched()) {
            return null;
        }

        $entry = ($this->load)()[$this->key($identity, $key)] ?? null;

        if (!\is_array($entry) || ($entry['e'] ?? 0) <= ($this->clock)() || !\is_array($entry['v'] ?? null)) {
            return null;
        }

        return $entry['v'];
    }

    public function set(Identity $identity, string $key, array $value): void
    {
        if ($this->ttl <= 0 || !$identity->isMatched()) {
            return;
        }

        $now   = ($this->clock)();
        $store = array_filter(($this->load)(), static fn ($e) => \is_array($e) && ($e['e'] ?? 0) > $now);

        $store[$this->key($identity, $key)] = ['e' => $now + $this->ttl, 'v' => $value];

        if (\count($store) > self::MAX_ENTRIES) {
            uasort($store, static fn (array $a, array $b): int => $a['e'] <=> $b['e']);
            $store = \array_slice($store, -self::MAX_ENTRIES, null, true);
        }

        ($this->save)($store);
    }

    public function clear(Identity $identity, string $prefix = ''): void
    {
        $start = $this->key($identity, $prefix);
        $store = array_filter(
            ($this->load)(),
            static fn ($k) => !str_starts_with((string) $k, $start),
            ARRAY_FILTER_USE_KEY
        );

        ($this->save)($store);
    }

    private function key(Identity $identity, string $key): string
    {
        $sdp = $identity->isMatched() ? $identity->sdpUserId() : '-';

        return $identity->joomlaUserId . '|' . $sdp . '|' . $key;
    }
}
