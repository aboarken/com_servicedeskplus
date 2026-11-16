<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Security;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One decision at a time per approval level. A second decision on the same level (a double
 * submit, a second tab, another approver of the level) waits until the first is finished, then
 * reads the level again and finds it decided. Uses a lock file in Joomla's tmp folder; the lock
 * ends with the request at the latest.
 */
final class DecisionLock
{
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Run $work while holding the lock for $key. Without a usable lock folder the work runs
     * unlocked (logged by the caller's own checks): a decision is never refused for that.
     *
     * @template T
     * @param  \Closure(): T  $work
     * @return T
     */
    public function run(string $key, \Closure $work): mixed
    {
        $file   = rtrim($this->directory, '/\\') . \DIRECTORY_SEPARATOR . 'sdp-decide-' . hash('sha256', $key) . '.lock';
        $handle = @fopen($file, 'c');

        if ($handle === false) {
            return $work();
        }

        try {
            flock($handle, LOCK_EX);

            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
