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
     * Number of the user's requests in total ('' key) and per status of the My Requests filter,
     * from SDP's total_count (one call each). A count is null when SDP gives no total, or when the
     * row it returns is not the user's (the requester filter was not applied).
     *
     * @return array<string, int|null>
     *
     * @throws SdpException
     */
    public function countsForUser(Identity $identity): array
    {
        $sdpUserId = $identity->sdpUserId();
        // "requests:" prefix: cleared with the lists when the user creates a request.
        $cached = $this->cache->get($identity, 'requests:counts');

        if ($cached !== null) {
            return $cached;
        }

        $client = ($this->clientFactory)();
        $counts = [];

        foreach (['', ...$this->allowedStatuses] as $status) {
            $criteria = [['field' => 'requester.id', 'condition' => 'is', 'value' => $sdpUserId]];

            if ($status !== '') {
                $criteria[] = ['field' => 'status.name', 'condition' => 'is', 'value' => $status, 'logical_operator' => 'AND'];
            }

            $page = $client->listRequests(new ListInfo(
                rowCount: 1,
                searchCriteria: \count($criteria) === 1 ? $criteria[0] : $criteria,
                getTotalCount: true
            ));

            $trusted         = $page->items === [] || self::isOwnedBy($page->items[0], $sdpUserId);
            $counts[$status] = $trusted ? ($page->totalCount ?? ($page->items === [] ? 0 : null)) : null;
        }

        $this->cache->set($identity, 'requests:counts', $counts);

        return $counts;
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

        $listInfo = new ListInfo(
            rowCount: $query->limit,
            startIndex: $query->startIndex(),
            sortField: $query->sortField(),
            sortOrder: $query->direction,
            searchCriteria: \count($criteria) === 1 ? $criteria[0] : $criteria,
            getTotalCount: true
        );

        $page  = ($this->clientFactory)()->listRequests($listInfo);
        $owned = array_values(array_filter($page->items, static fn (array $r) => self::isOwnedBy($r, $sdpUserId)));

        if (\count($owned) !== \count($page->items)) {
            // SDP ignored or mis-applied the requester filter. Others' requests are dropped here.
            $this->logger->warning(\sprintf(
                'SDP returned %d requests not owned by SDP user %s; they were hidden. Check the requester.id search criteria on this build.',
                \count($page->items) - \count($owned),
                $sdpUserId
            ));
        }

        return new RequestListResult(
            array_map(static fn (array $r) => RequestSummary::fromSdp($r), $owned),
            $query->page,
            $page->hasMoreRows,
            \count($owned) === \count($page->items) ? $page->totalCount : null
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
