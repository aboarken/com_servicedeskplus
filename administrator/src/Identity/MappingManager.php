<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Identity;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditLogInterface;

/**
 * Admin operations on #__sdp_user_map. Every change is audited with the admin as actor.
 */
final class MappingManager
{
    /** @var \Closure(): \DateTimeImmutable */
    private \Closure $clock;

    /**
     * @param  \Closure(): SdpClient                  $clientFactory
     * @param  (\Closure(): \DateTimeImmutable)|null  $clock
     */
    public function __construct(
        private readonly UserMapRepositoryInterface $repository,
        private readonly \Closure $clientFactory,
        private readonly AuditLogInterface $audit,
        ?\Closure $clock = null
    ) {
        $this->clock = $clock ?? static fn (): \DateTimeImmutable => new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * Set or override the mapping. The SDP user must exist and must not be mapped to someone else.
     * The current Joomla email is stored, so a later Joomla email change suspends the mapping.
     *
     * @return array<string, mixed>  The SDP user record (for showing name/email to the admin)
     *
     * @throws MappingException|SdpException
     */
    public function assign(int $joomlaUserId, string $joomlaEmail, string $sdpUserId, int $actorId): array
    {
        if ($joomlaUserId <= 0) {
            throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_USER');
        }

        try {
            $sdpUserId = SdpClient::id(trim($sdpUserId));
        } catch (\InvalidArgumentException) {
            throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_SDP_ID');
        }

        $sdpUser = $this->fetchSdpUser($sdpUserId);
        $owner   = $this->repository->findBySdpUserId($sdpUserId);

        if ($owner !== null && $owner->joomlaUserId !== $joomlaUserId) {
            $this->audit->record($joomlaUserId, 'mapping.assign', AuditLogInterface::RESULT_DENIED, null, null, 'sdp_user_already_mapped:' . $owner->joomlaUserId, $actorId);

            throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_TAKEN');
        }

        $now = ($this->clock)();

        $this->repository->save(new UserMapping(
            $joomlaUserId,
            $sdpUserId,
            EmailNormalizer::normalize($joomlaEmail),
            UserMapping::STATUS_ACTIVE,
            UserMapping::SOURCE_ADMIN,
            $now,
            $now,
            $actorId
        ));

        $this->audit->record($joomlaUserId, 'mapping.assign', AuditLogInterface::RESULT_OK, null, null, 'sdp_user:' . $sdpUserId, $actorId);

        return $sdpUser;
    }

    /**
     * Re-confirm a suspended mapping: keeps the SDP user, accepts the user's current Joomla email.
     *
     * @return array<string, mixed>
     *
     * @throws MappingException|SdpException
     */
    public function confirm(int $joomlaUserId, string $joomlaEmail, int $actorId): array
    {
        $mapping = $this->repository->find($joomlaUserId);

        if ($mapping === null || $mapping->sdpUserId === null) {
            throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_NOTHING_TO_CONFIRM');
        }

        return $this->assign($joomlaUserId, $joomlaEmail, $mapping->sdpUserId, $actorId);
    }

    public function suspend(int $joomlaUserId, int $actorId): void
    {
        $mapping = $this->repository->find($joomlaUserId);

        if ($mapping === null) {
            throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_NOT_FOUND');
        }

        $this->repository->save($mapping->with([
            'status'     => UserMapping::STATUS_SUSPENDED,
            'checkedAt'  => ($this->clock)(),
            'modifiedBy' => $actorId,
        ]));

        $this->audit->record($joomlaUserId, 'mapping.suspend', AuditLogInterface::RESULT_OK, null, null, '', $actorId);
    }

    /**
     * Delete the mapping so the next visit runs automatic matching again.
     */
    public function rematch(int $joomlaUserId, int $actorId): void
    {
        $this->repository->delete($joomlaUserId);
        $this->audit->record($joomlaUserId, 'mapping.rematch', AuditLogInterface::RESULT_OK, null, null, '', $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchSdpUser(string $sdpUserId): array
    {
        try {
            return ($this->clientFactory)()->getUser($sdpUserId);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::NOT_FOUND) {
                throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_SDP_NOT_FOUND');
            }

            throw $e;
        }
    }
}
