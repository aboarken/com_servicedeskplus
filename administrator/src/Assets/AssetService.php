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

    /** Most past loans read per user (newest first). */
    public const MAX_PAST_LOANS = 300;

    /**
     * The user's returned loans, newest first. Only loans whose loaned_to is the user's SDP user
     * are kept (checked here as well, whatever ServiceDesk Plus returns for the search).
     *
     * @return list<PastLoan>
     *
     * @throws SdpException
     */
    public function pastLoansForUser(Identity $identity, bool $withAssetDetails = true): array
    {
        $sdpUserId = $identity->sdpUserId();
        // Two entries: the loans as ServiceDesk Plus lists them (enough to count them), and the
        // same loans with the type, model and serial number of their assets (the list page).
        $cacheKey  = 'assets:' . self::CACHE_VERSION . ($withAssetDetails ? ':past2' : ':past1');
        $cached    = $this->cache->get($identity, $cacheKey);

        if ($cached !== null) {
            return array_map(static fn (array $a) => PastLoan::fromArray($a), $cached);
        }

        if ($withAssetDetails) {
            $loans = $this->withAssetDetails($this->pastLoansForUser($identity, false));
            $this->cache->set($identity, $cacheKey, array_map(static fn (PastLoan $l) => $l->toArray(), $loans));

            return $loans;
        }

        $items = ($this->clientFactory)()->listAllAssetLoans(
            new ListInfo(
                sortField: 'start_time',
                sortOrder: 'desc',
                searchCriteria: [['field' => 'loaned_to.id', 'condition' => 'is', 'value' => $sdpUserId]]
            ),
            self::MAX_PAST_LOANS
        );

        $loans = [];

        foreach ($items as $item) {
            $to = \is_array($item['loaned_to'] ?? null) ? (string) ($item['loaned_to']['id'] ?? '') : '';

            if ($sdpUserId === '' || $to !== $sdpUserId || !PastLoan::isOver($item)) {
                continue;
            }

            $loans[] = PastLoan::fromSdp($item);
        }

        usort($loans, static fn (PastLoan $a, PastLoan $b): int => [$b->returned ?? $b->due ?? 0, $b->start ?? 0] <=> [$a->returned ?? $a->due ?? 0, $a->start ?? 0]);

        $this->cache->set($identity, $cacheKey, array_map(static fn (PastLoan $l) => $l->toArray(), $loans));

        return $loans;
    }

    /** Most assets whose type, model and serial number are read for the past loans (newest first). */
    public const MAX_PAST_ASSET_DETAILS = 100;

    /**
     * Type, model and serial number of the assets of past loans, read from the assets when the
     * loan does not carry them (GET /api/v3/assets/{id}, several at once). Only these three
     * fields and the name are kept; an asset that cannot be read keeps what the loan said.
     *
     * @param  list<PastLoan>  $loans  newest first
     *
     * @return list<PastLoan>
     */
    private function withAssetDetails(array $loans): array
    {
        $ids = [];

        foreach ($loans as $loan) {
            foreach ($loan->idsWithoutDetails() as $id) {
                $ids[$id] = true;
            }
        }

        $ids = \array_slice(array_keys($ids), 0, self::MAX_PAST_ASSET_DETAILS);

        if ($ids === []) {
            return $loans;
        }

        $calls = [];

        foreach ($ids as $id) {
            $calls[(string) $id] = ['/api/v3/assets/' . SdpClient::id((string) $id), null];
        }

        $details = [];

        foreach (($this->clientFactory)()->getMany($calls) as $id => $result) {
            if ($result instanceof SdpException || !\is_array($result['asset'] ?? null)) {
                continue;
            }

            $details[(string) $id] = PastLoan::assetRow($result['asset']);
        }

        if (\count($details) < \count($ids)) {
            $this->logger->info(\sprintf('Previous loans: details of %d of %d assets could not be read (removed or not visible).', \count($ids) - \count($details), \count($ids)));
        }

        return array_map(static fn (PastLoan $loan): PastLoan => $loan->withDetails($details), $loans);
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
