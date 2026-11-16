<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;

/**
 * Counts the ServiceDesk Plus calls of one page load and writes one summary line when the page
 * is done: how many calls, how long they took together, and the slowest one. Only API paths and
 * times are logged; ids in paths are replaced by {id}.
 */
final class PageStats
{
    private static int $calls = 0;

    private static int $ms = 0;

    private static string $slowestPath = '';

    private static int $slowestMs = -1;

    private static ?LoggerInterface $logger = null;

    private static bool $registered = false;

    /** Called by every SdpClient; the summary is written once, at the end of the page load. */
    public static function start(LoggerInterface $logger): void
    {
        self::$logger ??= $logger;

        if (!self::$registered) {
            self::$registered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /**
     * One call. $waited = false for a call of a parallel batch, whose waiting time is added once
     * for the whole batch with waited().
     */
    public static function add(string $path, int $ms, bool $waited = true): void
    {
        self::$calls++;

        if ($waited) {
            self::$ms += max(0, $ms);
        }

        if ($ms > self::$slowestMs) {
            self::$slowestMs   = $ms;
            self::$slowestPath = (string) preg_replace('#/[0-9]{1,20}(?=/|$)#', '/{id}', $path);
        }
    }

    /** Time the page waited for a parallel batch. */
    public static function waited(int $ms): void
    {
        self::$ms += max(0, $ms);
    }

    /**
     * @return array{calls: int, ms: int, slowest_path: string, slowest_ms: int}
     */
    public static function snapshot(): array
    {
        return ['calls' => self::$calls, 'ms' => self::$ms, 'slowest_path' => self::$slowestPath, 'slowest_ms' => max(0, self::$slowestMs)];
    }

    public static function flush(): void
    {
        if (self::$calls === 0 || self::$logger === null) {
            return;
        }

        self::$logger->info(\sprintf(
            'SDP page summary: %d calls, %d ms waiting for ServiceDesk Plus, slowest call %s %d ms',
            self::$calls,
            self::$ms,
            self::$slowestPath,
            self::$slowestMs
        ));

        self::$calls       = 0;
        self::$ms          = 0;
        self::$slowestPath = '';
        self::$slowestMs   = -1;
    }
}
