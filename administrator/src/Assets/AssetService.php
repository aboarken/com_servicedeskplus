<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\ListInfo;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditLogInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Cache\UserCacheInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;

/**
 * Feature 3: the user's loan assets, read-only.
 *
 * An asset is shown only if it is assigned to the user's SDP user AND SDP marks it as loaned.
 * Both are checked in PHP on every list and detail read.
 */
final class AssetService
{
    /** A user rarely has more; the list is not paginated. */
    public const MAX_ASSETS = 200;

    /** Bump when LoanAsset changes shape, so older cache entries are ignored. */
    private const CACHE_VERSION = 'v4';

    /**
     * @param  \Closure(): SdpClient  $clientFactory
     */
    public function __construct(
        private readonly \Closure $clientFactory,
        private readonly LoanAssetConfig $config,
        private readonly UserCacheInterface $cache,
        private readonly AuditLogInterface $audit,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return list<LoanAsset>
     *
     * @throws SdpException
     */
    public function listForUser(Identity $identity): array
    {
        $sdpUserId = $identity->sdpUserId();
        $cacheKey  = 'assets:' . self::CACHE_VERSION . ':list';
        $cached    = $this->cache->get($identity, $cacheKey);

        if ($cached !== null) {
            return array_map(static fn (array $a) => LoanAsset::fromArray($a), $cached);
        }

        $items = ($this->clientFactory)()->listAllAssets(
            new ListInfo(searchCriteria: $this->config->userCriterion($sdpUserId)),
            self::MAX_ASSETS
        );

        $assets  = [];
        $foreign = 0;

        foreach ($items as $item) {
            if (!$this->config->isAssignedTo($item, $sdpUserId)) {
                $foreign++;

                continue;
            }

            if ($this->config->isLoan($item)) {
                $assets[] = LoanAsset::fromSdp($item, $this->config);
            }
        }

        if ($foreign > 0) {
            $this->logger->warning(\sprintf(
                'SDP returned %d assets not assigned to SDP user %s; they were hidden (filter field %s).',
                $foreign,
                $sdpUserId,
                LoanAssetConfig::USER_FIELD
            ));
        }

        usort($assets, static fn (LoanAsset $a, LoanAsset $b): int => strnatcasecmp($a->name, $b->name));

        $this->cache->set($identity, $cacheKey, array_map(static fn (LoanAsset $a) => $a->toArray(), $assets));

        return $assets;
    }

    /**
     * @throws AssetNotFound|SdpException
     */
    public function getForUser(Identity $identity, string $assetId): LoanAsset
    {
        $sdpUserId = $identity->sdpUserId();

        try {
            $assetId = SdpClient::id($assetId);
        } catch (\InvalidArgumentException) {
            throw new AssetNotFound();
        }

        $cacheKey = 'asset:' . self::CACHE_VERSION . ':' . $assetId;
        $cached   = $this->cache->get($identity, $cacheKey);

        if ($cached !== null) {
            return LoanAsset::fromArray($cached);
        }

        try {
            $asset = ($this->clientFactory)()->getAsset($assetId);
        } catch (SdpException $e) {
            if (\in_array($e->getKind(), [SdpException::NOT_FOUND, SdpException::VALIDATION], true)) {
                throw new AssetNotFound();
            }

            throw $e;
        }

        if (!$this->config->isAssignedTo($asset, $sdpUserId) || !$this->config->isLoan($asset)) {
            $this->audit->record($identity->joomlaUserId, 'asset.view', AuditLogInterface::RESULT_DENIED, null, null, 'asset:' . $assetId);
            $this->cache->clear($identity, $cacheKey);

            throw new AssetNotFound();
        }

        $loan = LoanAsset::fromSdp($asset, $this->config);
        $this->cache->set($identity, $cacheKey, $loan->toArray());

        return $loan;
    }
}
