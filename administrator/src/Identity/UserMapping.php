<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One row of #__sdp_user_map.
 */
final class UserMapping
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_UNMATCHED = 'unmatched';
    public const STATUS_AMBIGUOUS = 'ambiguous';
    public const STATUS_SUSPENDED = 'suspended';
    /** Found by e-mail, waiting for an administrator to confirm it (User Mappings → Confirm). */
    public const STATUS_PENDING   = 'pending';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_PENDING, self::STATUS_UNMATCHED, self::STATUS_AMBIGUOUS, self::STATUS_SUSPENDED];

    public const SOURCE_AUTO  = 'auto';
    public const SOURCE_ADMIN = 'admin';

    public function __construct(
        public readonly int $joomlaUserId,
        public readonly ?string $sdpUserId,
        public readonly string $email,
        public readonly string $status,
        public readonly string $source,
        public readonly ?\DateTimeImmutable $matchedAt,
        public readonly \DateTimeImmutable $checkedAt,
        public readonly int $modifiedBy = 0
    ) {
        if ($joomlaUserId <= 0) {
            throw new \InvalidArgumentException('Invalid Joomla user id');
        }

        if (!\in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid mapping status');
        }

        if (!\in_array($source, [self::SOURCE_AUTO, self::SOURCE_ADMIN], true)) {
            throw new \InvalidArgumentException('Invalid mapping source');
        }

        if ($sdpUserId !== null && !preg_match('/^[0-9]{1,20}$/', $sdpUserId)) {
            throw new \InvalidArgumentException('Invalid SDP user id');
        }

        if ($status === self::STATUS_ACTIVE && $sdpUserId === null) {
            throw new \InvalidArgumentException('An active mapping needs an SDP user id');
        }
    }

    /**
     * Copy with changes. Keys: sdpUserId, email, status, source, matchedAt, checkedAt, modifiedBy.
     *
     * @param  array<string, mixed>  $changes
     */
    public function with(array $changes): self
    {
        $get = fn (string $k, mixed $current): mixed => \array_key_exists($k, $changes) ? $changes[$k] : $current;

        return new self(
            $this->joomlaUserId,
            $get('sdpUserId', $this->sdpUserId),
            $get('email', $this->email),
            $get('status', $this->status),
            $get('source', $this->source),
            $get('matchedAt', $this->matchedAt),
            $get('checkedAt', $this->checkedAt),
            $get('modifiedBy', $this->modifiedBy)
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
