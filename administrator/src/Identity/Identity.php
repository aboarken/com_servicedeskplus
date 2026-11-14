<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Result of resolving the current Joomla user to an SDP user.
 *
 * This is the only source of the SDP user id used for ownership checks. It is built server-side
 * from #__sdp_user_map and never from request input.
 */
final class Identity
{
    private function __construct(
        public readonly int $joomlaUserId,
        private readonly ?string $sdpUserId,
        public readonly string $status
    ) {
    }

    public static function matched(int $joomlaUserId, string $sdpUserId): self
    {
        return new self($joomlaUserId, $sdpUserId, UserMapping::STATUS_ACTIVE);
    }

    public static function notMatched(int $joomlaUserId, string $status): self
    {
        return new self($joomlaUserId, null, $status);
    }

    public function isMatched(): bool
    {
        return $this->sdpUserId !== null;
    }

    /**
     * @throws \LogicException when called for an unmatched identity (a programming error)
     */
    public function sdpUserId(): string
    {
        if ($this->sdpUserId === null) {
            throw new \LogicException('Identity is not matched');
        }

        return $this->sdpUserId;
    }
}
