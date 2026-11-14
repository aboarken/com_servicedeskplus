<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Security;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One-time form tokens against double submission (double click, back + resubmit, replay).
 *
 * Each form render issues a token stored in the user's session. The first POST with a token
 * consumes it; later POSTs with the same token are rejected and can show the first result.
 */
final class SubmissionGuard
{
    public const OK      = 'ok';
    public const USED    = 'used';
    public const UNKNOWN = 'unknown';

    private const MAX_TOKENS = 20;
    private const LIFETIME   = 7200;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param  \Closure(): array<string, mixed>     $load
     * @param  \Closure(array<string, mixed>): void $save
     */
    public function __construct(
        private readonly \Closure $load,
        private readonly \Closure $save,
        ?\Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function issue(): string
    {
        $token = bin2hex(random_bytes(16));
        $store = $this->live();

        $store[$token] = ['s' => 'issued', 't' => ($this->clock)(), 'r' => null];

        if (\count($store) > self::MAX_TOKENS) {
            $store = \array_slice($store, -self::MAX_TOKENS, null, true);
        }

        ($this->save)($store);

        return $token;
    }

    /**
     * Mark the token used. Returns OK only the first time.
     */
    public function consume(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return self::UNKNOWN;
        }

        $store = $this->live();

        if (!isset($store[$token])) {
            return self::UNKNOWN;
        }

        if ($store[$token]['s'] !== 'issued') {
            return self::USED;
        }

        $store[$token]['s'] = 'used';
        ($this->save)($store);

        return self::OK;
    }

    public function recordResult(string $token, string $result): void
    {
        $store = $this->live();

        if (isset($store[$token])) {
            $store[$token]['r'] = $result;
            ($this->save)($store);
        }
    }

    public function resultFor(string $token): ?string
    {
        $result = $this->live()[$token]['r'] ?? null;

        return \is_string($result) ? $result : null;
    }

    /** @return array<string, array{s: string, t: int, r: ?string}> */
    private function live(): array
    {
        $now = ($this->clock)();

        return array_filter(
            ($this->load)(),
            static fn ($e) => \is_array($e) && isset($e['s'], $e['t']) && $e['t'] > $now - self::LIFETIME
        );
    }
}
