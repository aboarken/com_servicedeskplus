<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Users;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Cache\UserCacheInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;

/**
 * Reads the logged-in user's own SDP user record (never another user's: the id comes from the
 * server-side mapping) for the Dashboard header. Cached like the other results.
 */
final class UserProfileService
{
    private const CACHE_KEY = 'profile:v1';

    /**
     * @param  \Closure(): SdpClient  $clientFactory
     */
    public function __construct(
        private readonly \Closure $clientFactory,
        private readonly UserCacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Null when ServiceDesk Plus cannot give the record: the Dashboard then shows no header.
     */
    public function forUser(Identity $identity): ?UserProfile
    {
        $cached = $this->cache->get($identity, self::CACHE_KEY);

        if ($cached !== null) {
            return UserProfile::fromArray($cached);
        }

        try {
            $profile = UserProfile::fromSdp(($this->clientFactory)()->getUser($identity->sdpUserId()));
        } catch (SdpException $e) {
            $this->logger->warning(\sprintf('Profile of SDP user %s could not be read (%s)', $identity->sdpUserId(), $e->getKind()));

            return null;
        }

        $this->cache->set($identity, self::CACHE_KEY, $profile->toArray());

        return $profile;
    }
}
