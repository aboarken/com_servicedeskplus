<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\RateLimiter;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\RateLimitExceeded;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * Features 1 and 2: create a request as the user, list and show the user's own requests.
 *
 * SDP sees every call as the service technician, so ownership is enforced here on every read:
 * a request is shown only when requester.id equals the user's mapped SDP user id.
 */
final class RequestService
{
    public const SUBJECT_MAX     = 250;
    public const DESCRIPTION_MAX = 20000;
    public const ACTION_CREATE   = 'request.create';

    /**
     * @param  \Closure(): SdpClient  $clientFactory
     * @param  list<string>           $allowedStatuses
     */
    public function __construct(
        private readonly \Closure $clientFactory,
        private readonly UserCacheInterface $cache,
        private readonly AuditLogInterface $audit,
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger,
        private readonly array $allowedStatuses,
        private readonly int $createPerHour
    ) {
    }

    /** @return list<string> */
    public function allowedStatuses(): array
    {
        return $this->allowedStatuses;
    }

    public static function isOwnedBy(array $request, string $sdpUserId): bool
    {
        return $sdpUserId !== '' && SdpFields::id($request, 'requester') === $sdpUserId;
    }

    /**
     * Counts for the Dashboard. Open and on-hold requests are read (at most $maxPerStatus of each
     * status, one search per status; rows SDP returns for other requesters are ignored, as in the
     * request list). Resolved and closed requests are only counted by SDP (get_total_count with
     * one row), so a long history does not slow the Dashboard down.
     *
     * @throws SdpException
     */
    public function statsForUser(Identity $identity, int $maxPerStatus = 500): RequestStats
    {
        $sdpUserId = $identity->sdpUserId();
        // "requests:" prefix: cleared with the lists when the user creates a request.
        $cacheKey = 'requests:stats:v4';
        $cached   = $this->cache->get($identity, $cacheKey);

        if ($cached !== null) {
            return RequestStats::fromArray($cached);
        }

        $client    = ($this->clientFactory)();
        $rows      = [];
        $truncated = false;

        foreach ($this->statusNames(['open' => 'Open', 'on_hold' => 'On Hold']) as $status) {
            for ($start = 1; ; $start += ListInfo::MAX_ROW_COUNT) {
                $page = $client->listRequests(new ListInfo(
                    rowCount: ListInfo::MAX_ROW_COUNT,
                    startIndex: $start,
                    sortField: 'created_time',
                    sortOrder: 'desc',
                    searchCriteria: [
                        ['field' => 'requester.id', 'condition' => 'is', 'value' => $sdpUserId],
                        ['field' => 'status.name', 'condition' => 'is', 'value' => $status, 'logical_operator' => 'AND'],
                    ]
                ));

                foreach ($page->items as $item) {
                    if (self::isOwnedBy($item, $sdpUserId)) {
                        $rows[] = $item;
                    }
                }

                if (!$page->hasMoreRows || $page->items === []) {
                    break;
                }

                if ($start + ListInfo::MAX_ROW_COUNT > $maxPerStatus) {
                    $truncated = true;
                    break;
                }
            }
        }

        $stats = RequestStats::fromSdp($rows, $truncated)->withCounted($this->countDone($client, $sdpUserId));
        $this->cache->set($identity, $cacheKey, $stats->toArray());

        return $stats;
    }

    /**
     * Resolved and closed requests of the user, counted by SDP: incidents, service requests and
     * all. The split is left out (null) when SDP cannot search on the request type or its counts
     * do not add up; a count SDP does not give stays null.
     *
     * @return array<string, array{incident: ?int, service: ?int, total: ?int}>
     */
    private function countDone(SdpClient $client, string $sdpUserId): array
    {
        $counted    = [];
        $typeSearch = true;

        foreach ($this->statusNames(['resolved' => 'Resolved', 'closed' => 'Closed']) as $bucket => $status) {
            $counts = ['incident' => null, 'service' => null, 'total' => null];

            try {
                $counts['total'] = $this->countRequests($client, $sdpUserId, $status, null);

                if ($typeSearch) {
                    try {
                        $counts['incident'] = $this->countRequests($client, $sdpUserId, $status, false);
                        $counts['service']  = $this->countRequests($client, $sdpUserId, $status, true);
                    } catch (SdpException $e) {
                        if ($e->getKind() !== SdpException::VALIDATION) {
                            throw $e;
                        }

                        $typeSearch = false;
                        $this->logger->info('SDP rejected the is_service_request search criterion; resolved and closed requests are counted without the type split.');
                    }
                }

                // SDP ignored the type criterion (both halves equal the total) or gave no count.
                if ($counts['incident'] === null || $counts['service'] === null || $counts['incident'] + $counts['service'] !== $counts['total']) {
                    $counts['incident'] = null;
                    $counts['service']  = null;
                }
            } catch (SdpException $e) {
                $this->logger->warning(\sprintf('%s requests of SDP user %s could not be counted (%s).', $status, $sdpUserId, $e->getKind()));
            }

            $counted[$bucket] = $counts;
        }

        return $counted;
    }

    /**
     * Number of the user's requests with a status (and optionally a type), counted by SDP.
     *
     * @throws SdpException
     */
    private function countRequests(SdpClient $client, string $sdpUserId, string $status, ?bool $service): ?int
    {
        $criteria = [
            ['field' => 'requester.id', 'condition' => 'is', 'value' => $sdpUserId],
            ['field' => 'status.name', 'condition' => 'is', 'value' => $status, 'logical_operator' => 'AND'],
        ];

        if ($service !== null) {
            $criteria[] = ['field' => 'is_service_request', 'condition' => 'is', 'value' => $service, 'logical_operator' => 'AND'];
        }

        return $client->listRequests(new ListInfo(rowCount: 1, startIndex: 1, searchCriteria: $criteria, getTotalCount: true))->totalCount;
    }

    /**
     * Statuses as spelled in the My Requests filter for the given buckets, or SDP's default names
     * when the filter has none.
     *
     * @param  array<string, string>  $defaults  bucket => SDP's default status name
     *
     * @return array<string, string>  bucket => status name
     */
    private function statusNames(array $defaults): array
    {
        $found = [];

        foreach ($this->allowedStatuses as $status) {
            $bucket = RequestStats::bucket($status);

            if (isset($defaults[$bucket])) {
                $found[$bucket] ??= $status;
            }
        }

        return array_intersect_key($found + $defaults, $defaults);
    }

    /**
     * @throws SdpException
     */
    public function listForUser(Identity $identity, RequestQuery $query): RequestListResult
    {
        $sdpUserId = $identity->sdpUserId();
        $cached    = $this->cache->get($identity, $query->cacheKey());

        if ($cached !== null) {
            return RequestListResult::fromArray($cached);
        }

        $result = $query->isIdSearch()
            ? $this->findById($identity, $query)
            : $this->search($sdpUserId, $query);

        $this->cache->set($identity, $query->cacheKey(), $result->toArray());

        return $result;
    }

    /**
     * @throws RequestNotFound|SdpException
     */
    public function getForUser(Identity $identity, string $requestId): RequestDetail
    {
        try {
            $requestId = SdpClient::id($requestId);
        } catch (\InvalidArgumentException) {
            throw new RequestNotFound();
        }

        $cacheKey = 'request:' . $requestId;
        $cached   = $this->cache->get($identity, $cacheKey);

        if ($cached !== null) {
            return RequestDetail::fromArray($cached);
        }

        $request = $this->fetchOwned($identity, $requestId);
        $detail  = RequestDetail::fromSdp($request, $this->resolutionText($request, $requestId));

        $this->cache->set($identity, $cacheKey, $detail->toArray());

        return $detail;
    }

    /**
     * Create the request, then attach the files (already checked by AttachmentValidator).
     * A file that cannot be attached does not undo the request; its name is reported back.
     *
     * @param  list<Attachment>  $attachments
     *
     * @throws InvalidRequestInput|RateLimitExceeded|SdpException
     */
    public function createForUser(Identity $identity, string $subject, string $description, array $attachments = []): CreatedRequest
    {
        [$subject, $description] = self::validate($subject, $description);

        if (\count($attachments) > AttachmentValidator::MAX_FILES) {
            throw new InvalidRequestInput(['COM_SERVICEDESKPLUS_ATTACH_ERR_COUNT']);
        }

        $userId = $identity->joomlaUserId;

        if (!$this->rateLimiter->allow($userId, self::ACTION_CREATE, $this->createPerHour)) {
            $this->audit->record($userId, self::ACTION_CREATE, AuditLogInterface::RESULT_DENIED, null, null, 'rate_limited');

            throw new RateLimitExceeded();
        }

        try {
            $created = ($this->clientFactory)()->createRequest($subject, HtmlText::fromPlain($description), $identity->sdpUserId());
            $id      = SdpClient::id(SdpFields::string($created, 'id'));
        } catch (SdpException $e) {
            $this->audit->record($userId, self::ACTION_CREATE, AuditLogInterface::RESULT_ERROR, null, null, $e->getKind());

            throw $e;
        } catch (\InvalidArgumentException) {
            $this->audit->record($userId, self::ACTION_CREATE, AuditLogInterface::RESULT_ERROR, null, null, 'no_id_in_response');

            throw new SdpException(SdpException::BAD_RESPONSE, 'Created request has no id');
        }

        $this->audit->record($userId, self::ACTION_CREATE, AuditLogInterface::RESULT_OK, $id);
        $this->cache->clear($identity, 'requests:');

        $failed = [];

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof Attachment) {
                continue;
            }

            try {
                $content = @file_get_contents($attachment->path);

                if ($content === false) {
                    throw new SdpException(SdpException::VALIDATION, 'Attachment file could not be read');
                }

                ($this->clientFactory)()->uploadRequestAttachment($id, $attachment->name, $attachment->contentType, $content);
                $this->audit->record($userId, 'request.attach', AuditLogInterface::RESULT_OK, $id, null, $attachment->name);
            } catch (SdpException $e) {
                $failed[] = $attachment->name;
                $this->audit->record($userId, 'request.attach', AuditLogInterface::RESULT_ERROR, $id, null, $e->getKind() . ':' . $attachment->name);
            }
        }

        $this->cache->clear($identity, 'request:' . $id);

        return new CreatedRequest($id, $failed);
    }

    /**
     * Normalise and check the two form fields.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidRequestInput
     */
    public static function validate(string $subject, string $description): array
    {
        // Subject: single line. Control characters (including newlines) become spaces.
        $subject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject) ?? '';
        $subject = trim(preg_replace('/\s{2,}/u', ' ', $subject) ?? '');

        $description = str_replace(["\r\n", "\r"], "\n", $description);
        $description = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $description) ?? '');

        $errors = [];

        if ($subject === '') {
            $errors[] = 'COM_SERVICEDESKPLUS_REQUEST_ERR_SUBJECT_REQUIRED';
        } elseif (mb_strlen($subject) > self::SUBJECT_MAX) {
            $errors[] = 'COM_SERVICEDESKPLUS_REQUEST_ERR_SUBJECT_TOO_LONG';
        }

        if ($description === '') {
            $errors[] = 'COM_SERVICEDESKPLUS_REQUEST_ERR_DESCRIPTION_REQUIRED';
        } elseif (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors[] = 'COM_SERVICEDESKPLUS_REQUEST_ERR_DESCRIPTION_TOO_LONG';
        }

        if ($errors !== []) {
            throw new InvalidRequestInput($errors);
        }

        return [$subject, $description];
    }

    private function search(string $sdpUserId, RequestQuery $query): RequestListResult
    {
        $criteria = [['field' => 'requester.id', 'condition' => 'is', 'value' => $sdpUserId]];

        if ($query->status !== '') {
            $criteria[] = ['field' => 'status.name', 'condition' => 'is', 'value' => $query->status, 'logical_operator' => 'AND'];
        }

        if ($query->search !== '') {
            $criteria[] = ['field' => 'subject', 'condition' => 'contains', 'value' => $query->search, 'logical_operator' => 'AND'];
        }

        if ($query->type !== '') {
            $criteria[] = ['field' => 'is_service_request', 'condition' => 'is', 'value' => $query->type === RequestQuery::TYPE_SERVICE, 'logical_operator' => 'AND'];
        }

        $listInfo = new ListInfo(
            rowCount: $query->limit,
            startIndex: $query->startIndex(),
            sortField: $query->sortField(),
            sortOrder: $query->direction,
            searchCriteria: \count($criteria) === 1 ? $criteria[0] : $criteria,
            getTotalCount: true
        );

        try {
            $page = ($this->clientFactory)()->listRequests($listInfo);
        } catch (SdpException $e) {
            if ($query->type === '' || $e->getKind() !== SdpException::VALIDATION) {
                throw $e;
            }

            // This SDP build does not search on the request type: search without it; the rows
            // are filtered on their type below.
            $this->logger->info('SDP rejected the is_service_request search criterion; the request type is filtered in PHP.');
            array_pop($criteria);

            $page = ($this->clientFactory)()->listRequests(new ListInfo(
                rowCount: $query->limit,
                startIndex: $query->startIndex(),
                sortField: $query->sortField(),
                sortOrder: $query->direction,
                searchCriteria: \count($criteria) === 1 ? $criteria[0] : $criteria,
                getTotalCount: true
            ));
        }

        $owned = array_values(array_filter($page->items, static fn (array $r) => self::isOwnedBy($r, $sdpUserId)));

        if (\count($owned) !== \count($page->items)) {
            // SDP ignored or mis-applied the requester filter. Others' requests are dropped here.
            $this->logger->warning(\sprintf(
                'SDP returned %d requests not owned by SDP user %s; they were hidden. Check the requester.id search criteria on this build.',
                \count($page->items) - \count($owned),
                $sdpUserId
            ));
        }

        // Re-check the type in PHP, in case SDP ignored the criterion.
        $matching = array_values(array_filter($owned, static fn (array $r) => $query->matchesType($r)));

        return new RequestListResult(
            array_map(static fn (array $r) => RequestSummary::fromSdp($r), $matching),
            $query->page,
            $page->hasMoreRows,
            \count($matching) === \count($page->items) ? $page->totalCount : null
        );
    }

    private function findById(Identity $identity, RequestQuery $query): RequestListResult
    {
        try {
            $request = $this->fetchOwned($identity, $query->searchId());
        } catch (RequestNotFound) {
            return new RequestListResult([], 1, false, 0);
        }

        if ($query->status !== '' && SdpFields::name($request, 'status') !== $query->status) {
            return new RequestListResult([], 1, false, 0);
        }

        if (!$query->matchesType($request)) {
            return new RequestListResult([], 1, false, 0);
        }

        return new RequestListResult([RequestSummary::fromSdp($request)], 1, false, 1);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestNotFound|SdpException
     */
    private function fetchOwned(Identity $identity, string $requestId): array
    {
        try {
            $request = ($this->clientFactory)()->getRequest($requestId);
        } catch (SdpException $e) {
            if (\in_array($e->getKind(), [SdpException::NOT_FOUND, SdpException::VALIDATION], true)) {
                throw new RequestNotFound();
            }

            throw $e;
        } catch (\InvalidArgumentException) {
            throw new RequestNotFound();
        }

        if (!self::isOwnedBy($request, $identity->sdpUserId())) {
            $this->audit->record($identity->joomlaUserId, 'request.view', AuditLogInterface::RESULT_DENIED, $requestId, null, 'not_requester');
            // No older copy of it, its conversation or its approvals stays for this user.
            foreach (['request:', 'conversation:', 'request_approvals:'] as $prefix) {
                $this->cache->clear($identity, $prefix . $requestId);
            }

            throw new RequestNotFound();
        }

        return $request;
    }

    /** @param array<string, mixed> $request */
    private function resolutionText(array $request, string $requestId): string
    {
        $embedded = $request['resolution'] ?? null;

        if (\is_array($embedded) && SdpFields::string($embedded, 'content') !== '') {
            return HtmlText::toPlain(SdpFields::string($embedded, 'content'));
        }

        try {
            $resolution = ($this->clientFactory)()->getResolution($requestId);
        } catch (SdpException $e) {
            if ($e->getKind() !== SdpException::NOT_FOUND && $e->getKind() !== SdpException::VALIDATION) {
                $this->logger->warning(\sprintf('Resolution of request %s could not be read (%s)', $requestId, $e->getKind()));
            }

            return '';
        }

        return HtmlText::toPlain(SdpFields::string($resolution, 'content'));
    }
}
