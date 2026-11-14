<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditLogInterface;

/**
 * Maps the logged-in Joomla user to exactly one SDP user by email.
 *
 * Rules:
 *  - Emails are compared after trim + lowercase, and must be equal (not "contains").
 *  - Zero matches means "not matched". If several SDP users have the email, the newest one
 *    (highest SDP user id, i.e. created last) is used and the choice is written to the audit log.
 *  - One SDP user can only be mapped to one Joomla user. If the chosen SDP user is already mapped
 *    to another Joomla user, the result is "ambiguous" and an admin must decide.
 *  - If the Joomla email no longer equals the email stored with an active mapping, the mapping is
 *    suspended until an admin re-confirms it. It is never re-matched automatically.
 *  - Automatic active mappings are re-verified against SDP once a day; if the SDP user is gone or
 *    its email changed, the mapping is suspended.
 *  - Unmatched/ambiguous results are retried at most every RECHECK_SECONDS.
 */
final class IdentityResolver
{
    public const RECHECK_SECONDS  = 900;
    public const REVERIFY_SECONDS = 86400;

    /** @var array<int, Identity> */
    private array $memo = [];

    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $clock;

    /**
     * @param  \Closure(): SdpClient                    $clientFactory  Lazy, so active mappings need no SDP call
     * @param  (\Closure(): \DateTimeImmutable)|null    $clock
     */
    public function __construct(
        private readonly UserMapRepositoryInterface $repository,
        private readonly \Closure $clientFactory,
        private readonly AuditLogInterface $audit,
        private readonly LoggerInterface $logger,
        ?\Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @throws SdpException when SDP must be asked and cannot answer (nothing is stored in that case)
     */
    public function resolve(int $joomlaUserId, string $joomlaEmail): Identity
    {
        if ($joomlaUserId <= 0) {
            return Identity::notMatched(0, UserMapping::STATUS_UNMATCHED);
        }

        if (isset($this->memo[$joomlaUserId])) {
            return $this->memo[$joomlaUserId];
        }

        return $this->memo[$joomlaUserId] = $this->doResolve($joomlaUserId, EmailNormalizer::normalize($joomlaEmail));
    }

    /**
     * Forget per-request results (used after an admin change).
     */
    public function reset(): void
    {
        $this->memo = [];
    }

    private function doResolve(int $userId, string $email): Identity
    {
        $now     = ($this->clock)();
        $mapping = $this->repository->find($userId);

        if ($mapping !== null) {
            if ($mapping->status === UserMapping::STATUS_SUSPENDED) {
                return Identity::notMatched($userId, UserMapping::STATUS_SUSPENDED);
            }

            if ($mapping->isActive()) {
                if ($mapping->email !== $email) {
                    $this->suspend($mapping, $now, 'joomla_email_changed');

                    return Identity::notMatched($userId, UserMapping::STATUS_SUSPENDED);
                }

                if ($mapping->source === UserMapping::SOURCE_AUTO && $this->age($mapping, $now) >= self::REVERIFY_SECONDS) {
                    return $this->reverify($mapping, $email, $now);
                }

                return Identity::matched($userId, (string) $mapping->sdpUserId);
            }

            // Unmatched or ambiguous: reuse the recent result unless the email changed.
            if ($mapping->email === $email && $this->age($mapping, $now) < self::RECHECK_SECONDS) {
                return Identity::notMatched($userId, $mapping->status);
            }
        }

        return $this->lookup($userId, $email, $now);
    }

    private function lookup(int $userId, string $email, \DateTimeImmutable $now): Identity
    {
        if (!EmailNormalizer::isUsable($email)) {
            return $this->storeNotMatched($userId, $email, UserMapping::STATUS_UNMATCHED, $now, 'invalid_email');
        }

        $candidates = $this->client()->findUsersByEmail($email);
        $ids        = [];

        foreach ($candidates as $user) {
            $id = isset($user['id']) ? (string) $user['id'] : '';

            if (preg_match('/^[0-9]{1,20}$/', $id) && EmailNormalizer::normalize($user['email_id'] ?? '') === $email) {
                $ids[$id] = true;
            }
        }

        if (\count($ids) === 0) {
            return $this->storeNotMatched($userId, $email, UserMapping::STATUS_UNMATCHED, $now, 'no_sdp_user');
        }

        // Several SDP users with this email: use the newest (highest id). Ids are compared as
        // numbers without converting them, since they can exceed PHP_INT_MAX on some builds.
        $candidates = array_map('strval', array_keys($ids));
        usort($candidates, static fn (string $a, string $b): int => [\strlen($b), $b] <=> [\strlen($a), $a]);

        $sdpUserId = $candidates[0];
        $detail    = 'sdp_user:' . $sdpUserId . (\count($candidates) > 1 ? ' newest_of:' . implode(',', $candidates) : '');
        $owner     = $this->repository->findBySdpUserId($sdpUserId);

        if ($owner !== null && $owner->joomlaUserId !== $userId) {
            $this->logger->warning(\sprintf('SDP user %s is already mapped to Joomla user %d; not mapping user %d', $sdpUserId, $owner->joomlaUserId, $userId));

            return $this->storeNotMatched($userId, $email, UserMapping::STATUS_AMBIGUOUS, $now, 'sdp_user_already_mapped');
        }

        $this->repository->save(new UserMapping($userId, $sdpUserId, $email, UserMapping::STATUS_ACTIVE, UserMapping::SOURCE_AUTO, $now, $now));
        $this->audit->record($userId, 'identity.matched', AuditLogInterface::RESULT_OK, null, null, $detail);

        return Identity::matched($userId, $sdpUserId);
    }

    private function reverify(UserMapping $mapping, string $email, \DateTimeImmutable $now): Identity
    {
        $userId = $mapping->joomlaUserId;

        try {
            $sdpUser = $this->client()->getUser((string) $mapping->sdpUserId);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::NOT_FOUND) {
                $this->suspend($mapping, $now, 'sdp_user_not_found');

                return Identity::notMatched($userId, UserMapping::STATUS_SUSPENDED);
            }

            // SDP unavailable: keep the verified mapping; try again on a later request.
            $this->logger->warning(\sprintf('Could not re-verify mapping for Joomla user %d (%s)', $userId, $e->getKind()));

            return Identity::matched($userId, (string) $mapping->sdpUserId);
        }

        if (EmailNormalizer::normalize($sdpUser['email_id'] ?? '') !== $email) {
            $this->suspend($mapping, $now, 'sdp_email_changed');

            return Identity::notMatched($userId, UserMapping::STATUS_SUSPENDED);
        }

        $this->repository->save($mapping->with(['checkedAt' => $now]));

        return Identity::matched($userId, (string) $mapping->sdpUserId);
    }

    private function suspend(UserMapping $mapping, \DateTimeImmutable $now, string $reason): void
    {
        $this->repository->save($mapping->with(['status' => UserMapping::STATUS_SUSPENDED, 'checkedAt' => $now]));
        $this->audit->record($mapping->joomlaUserId, 'identity.suspended', AuditLogInterface::RESULT_DENIED, null, null, $reason);
    }

    private function storeNotMatched(int $userId, string $email, string $status, \DateTimeImmutable $now, string $reason): Identity
    {
        $this->repository->save(new UserMapping($userId, null, $email, $status, UserMapping::SOURCE_AUTO, null, $now));
        $this->audit->record($userId, 'identity.' . $status, AuditLogInterface::RESULT_DENIED, null, null, $reason);

        return Identity::notMatched($userId, $status);
    }

    private function age(UserMapping $mapping, \DateTimeImmutable $now): int
    {
        return $now->getTimestamp() - $mapping->checkedAt->getTimestamp();
    }

    private function client(): SdpClient
    {
        return ($this->clientFactory)();
    }
}
