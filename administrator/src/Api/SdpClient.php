<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport\TransportException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport\TransportInterface;

/**
 * The single gateway to ServiceDesk Plus On-Premises REST API v3.
 *
 * Handles authentication headers, input_data encoding, error mapping, pagination and logging.
 * Controllers and views never call SDP directly; models call this class.
 *
 * Every call uses the service (technician) key. SDP therefore sees all actions as that
 * technician; the services in this component enforce per-user ownership before each call.
 */
final class SdpClient
{
    public const ACCEPT = 'application/vnd.manageengine.sdp.v3+json';

    /** Safety cap for listAll() so a bad has_more_rows value cannot loop forever. */
    private const MAX_PAGES = 50;

    /** @var list<string> */
    private array $secrets;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly ClientConfig $config,
        #[\SensitiveParameter] private readonly string $serviceKey,
        private readonly LoggerInterface $logger
    ) {
        if (trim($serviceKey) === '') {
            throw new SdpException(SdpException::NOT_CONFIGURED, 'The service API key is not configured');
        }

        $this->secrets = [$serviceKey];
    }

    /**
     * @param  array<string, mixed>|null  $input  Decoded input_data, sent as a query parameter
     *
     * @return array<string, mixed>
     */
    public function get(string $path, ?array $input = null): array
    {
        return $this->call('GET', $path, $input, $this->serviceKey);
    }

    /**
     * @param  array<string, mixed>  $input  Decoded input_data, sent as a form field
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $input): array
    {
        return $this->call('POST', $path, $input, $this->serviceKey);
    }

    /**
     * Fetch one page of a list endpoint.
     *
     * @param  string                $collectionKey  Response key holding the list, e.g. "requests"
     * @param  array<string, mixed>  $extra          Extra top-level input_data keys (e.g. fields_required)
     */
    public function listPage(string $path, string $collectionKey, ListInfo $listInfo, array $extra = []): ListPage
    {
        $input = ['list_info' => $listInfo->toArray()] + $extra;
        $data  = $this->get($path, $input);
        $items = $data[$collectionKey] ?? [];

        if (!\is_array($items) || !array_is_list($items)) {
            throw new SdpException(SdpException::BAD_RESPONSE, 'Unexpected list response shape');
        }

        $meta = \is_array($data['list_info'] ?? null) ? $data['list_info'] : [];

        return new ListPage(
            array_values(array_filter($items, 'is_array')),
            self::toBool($meta['has_more_rows'] ?? false),
            (int) ($meta['start_index'] ?? $listInfo->startIndex),
            (int) ($meta['row_count'] ?? \count($items)),
            isset($meta['total_count']) && is_numeric($meta['total_count']) ? (int) $meta['total_count'] : null
        );
    }

    /**
     * Fetch pages until has_more_rows is false or $maxItems is reached.
     *
     * Pagination per the docs: next start_index = previous start_index + row_count.
     *
     * @param  array<string, mixed>  $extra
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(string $path, string $collectionKey, ListInfo $listInfo, int $maxItems = 500, array $extra = []): array
    {
        $all      = [];
        $listInfo = $listInfo->withRowCount(ListInfo::MAX_ROW_COUNT);

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->listPage($path, $collectionKey, $listInfo, $extra);

            foreach ($result->items as $item) {
                $all[] = $item;
            }

            if (!$result->hasMoreRows || $result->items === [] || \count($all) >= $maxItems) {
                break;
            }

            $listInfo = $listInfo->withStartIndex($listInfo->startIndex + $listInfo->rowCount);
        }

        return \array_slice($all, 0, $maxItems);
    }

    /**
     * Harmless read used by "Test connection": GET /api/v3/requests with one row and only the id.
     *
     * @throws SdpException
     */
    public function testConnection(): void
    {
        $this->listPage('/api/v3/requests', 'requests', new ListInfo(rowCount: 1));
    }

    /**
     * GET /api/v3/requests (requests/request.html, "Get List Request").
     */
    public function listRequests(ListInfo $listInfo): ListPage
    {
        return $this->listPage('/api/v3/requests', 'requests', $listInfo);
    }

    /**
     * GET /api/v3/requests/{request_id}/approval_levels (named in the project spec).
     * UNVERIFIED for requests: the On-Premises reference documents this shape for changes only.
     *
     * @return list<array<string, mixed>>
     */
    public function getApprovalLevels(string $requestId): array
    {
        $data = $this->get(
            '/api/v3/requests/' . self::id($requestId) . '/approval_levels',
            ['list_info' => ['row_count' => ListInfo::MAX_ROW_COUNT, 'start_index' => 1]]
        );

        return $this->collection($data, 'approval_levels');
    }

    /**
     * GET /api/v3/requests/{request_id}/approval_levels/{level_id}/approvals (named in the spec).
     * UNVERIFIED for requests, see getApprovalLevels().
     *
     * @return list<array<string, mixed>>
     */
    public function getApprovals(string $requestId, string $levelId): array
    {
        $data = $this->get(
            '/api/v3/requests/' . self::id($requestId) . '/approval_levels/' . self::id($levelId) . '/approvals',
            ['list_info' => ['row_count' => ListInfo::MAX_ROW_COUNT, 'start_index' => 1]]
        );

        return $this->collection($data, 'approvals');
    }

    /**
     * Approve or reject one request approval with the service (technician) key.
     *
     * PUT /api/v3/requests/{request_id}/approval_levels/{level_id}/approvals/{approval_id}/approve|reject
     * with input_data {"approval": {"comments": "..."}}.
     * UNVERIFIED for requests: mirrors the documented change-approval endpoint
     * (changes/change_approval.html). Confirm on build 16030 before production use.
     *
     * SDP records the technician as the actor; ApprovalService only calls this after confirming
     * that the logged-in user is the approver, and puts the user's name in the comment.
     *
     * @param  'approve'|'reject'  $decision
     *
     * @return array<string, mixed>
     */
    public function decideApproval(string $requestId, string $levelId, string $approvalId, string $decision, string $comment): array
    {
        if (!\in_array($decision, ['approve', 'reject'], true)) {
            throw new \InvalidArgumentException('Decision must be approve or reject');
        }

        $path = '/api/v3/requests/' . self::id($requestId)
            . '/approval_levels/' . self::id($levelId)
            . '/approvals/' . self::id($approvalId)
            . '/' . $decision;

        $approval = $comment !== '' ? ['comments' => $comment] : [];

        return $this->call('PUT', $path, ['approval' => (object) $approval], $this->serviceKey);
    }

    /**
     * GET /api/v3/assets.
     *
     * UNVERIFIED: assets are not in the On-Premises v3 reference (see docs/ENDPOINTS.md).
     * Confirm with "Test asset endpoint" in Settings. Only these two methods name the path.
     */
    public function listAssets(ListInfo $listInfo): ListPage
    {
        return $this->listPage('/api/v3/assets', 'assets', $listInfo);
    }

    /**
     * All pages of GET /api/v3/assets. UNVERIFIED, see listAssets().
     *
     * @return list<array<string, mixed>>
     */
    public function listAllAssets(ListInfo $listInfo, int $maxItems): array
    {
        return $this->listAll('/api/v3/assets', 'assets', $listInfo, $maxItems);
    }

    /**
     * GET /api/v3/assets/{asset_id}. UNVERIFIED, see listAssets().
     *
     * @return array<string, mixed>
     */
    public function getAsset(string $assetId): array
    {
        return $this->entity($this->get('/api/v3/assets/' . self::id($assetId)), 'asset');
    }

    /**
     * GET /api/v3/requests/{request_id} (requests/request.html, "Get Request").
     *
     * @return array<string, mixed>
     */
    public function getRequest(string $requestId): array
    {
        return $this->entity($this->get('/api/v3/requests/' . self::id($requestId)), 'request');
    }

    /**
     * Attach one file to an existing request.
     *
     * UNVERIFIED: the On-Premises v3 reference documents no upload endpoint (request responses only
     * show "attachments" with content_url /api/v3/requests/{id}/attachments/{id}/download). Two
     * forms used by SDP builds are tried in order, and the one that worked is logged:
     *   1. PUT  /api/v3/requests/{id}/upload    multipart field "input_file"
     *   2. POST /api/v3/requests/{id}/_uploads  multipart field "filename"
     * The second is only tried when the first answers "not found" / "method not allowed".
     *
     * @return array<string, mixed>  SDP response
     *
     * @throws SdpException
     */
    public function uploadRequestAttachment(string $requestId, string $filename, string $contentType, string $content): array
    {
        $id = self::id($requestId);

        try {
            $data = $this->call('PUT', '/api/v3/requests/' . $id . '/upload', null, $this->serviceKey, self::multipart('input_file', $filename, $contentType, $content));
            $this->logger->info('Attachment uploaded with PUT /api/v3/requests/{id}/upload');

            return $data;
        } catch (SdpException $e) {
            $fallback = $e->getKind() === SdpException::NOT_FOUND
                || \in_array($e->getHttpStatus(), [404, 405], true);

            if (!$fallback) {
                throw $e;
            }
        }

        $data = $this->call('POST', '/api/v3/requests/' . $id . '/_uploads', null, $this->serviceKey, self::multipart('filename', $filename, $contentType, $content));
        $this->logger->info('Attachment uploaded with POST /api/v3/requests/{id}/_uploads');

        return $data;
    }

    /**
     * GET /api/v3/requests/{request_id}/resolutions (requests/request.html, "Get Resolution").
     *
     * @return array<string, mixed>
     */
    public function getResolution(string $requestId): array
    {
        return $this->entity($this->get('/api/v3/requests/' . self::id($requestId) . '/resolutions'), 'resolution');
    }

    /**
     * POST /api/v3/requests (requests/request.html, "Add Request").
     * Only subject, description and requester are sent; SDP defaults apply to everything else.
     * Never retried automatically, so a timeout cannot create duplicates.
     *
     * @return array<string, mixed>  The created request
     */
    public function createRequest(string $subject, string $descriptionHtml, string $requesterId): array
    {
        $data = $this->post('/api/v3/requests', ['request' => [
            'subject'     => $subject,
            'description' => $descriptionHtml,
            'requester'   => ['id' => self::id($requesterId)],
        ]]);

        return $this->entity($data, 'request');
    }

    /**
     * Users whose email matches, per GET /api/v3/users with list_info.search_fields.email_id
     * (admin/user.html, "Get List User"). SDP may return partial matches, so callers must compare
     * email_id exactly; IdentityResolver does.
     *
     * @return list<array<string, mixed>>
     */
    public function findUsersByEmail(string $email): array
    {
        $email = trim($email);

        if ($email === '' || \strlen($email) > 320) {
            throw new \InvalidArgumentException('Invalid email');
        }

        return $this->listAll(
            '/api/v3/users',
            'users',
            new ListInfo(rowCount: ListInfo::MAX_ROW_COUNT, searchFields: ['email_id' => $email]),
            ListInfo::MAX_ROW_COUNT,
            ['fields_required' => ['id', 'name', 'email_id']]
        );
    }

    /**
     * GET /api/v3/users/{user_id} (admin/user.html, "Get User").
     *
     * @return array<string, mixed>
     */
    public function getUser(string $sdpUserId): array
    {
        return $this->entity($this->get('/api/v3/users/' . self::id($sdpUserId)), 'user');
    }

    /**
     * Validate an SDP id taken from a route or from SDP itself before putting it in a path.
     */
    public static function id(string|int $id): string
    {
        $id = (string) $id;

        if (!preg_match('/^[0-9]{1,20}$/', $id)) {
            throw new \InvalidArgumentException('Invalid ServiceDesk Plus id');
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return list<array<string, mixed>>
     */
    private function collection(array $data, string $key): array
    {
        $items = $data[$key] ?? [];

        if (!\is_array($items) || !array_is_list($items)) {
            throw new SdpException(SdpException::BAD_RESPONSE, 'Unexpected response shape for ' . $key);
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function entity(array $data, string $key): array
    {
        $entity = $data[$key] ?? null;

        if (!\is_array($entity) || array_is_list($entity) && $entity !== []) {
            throw new SdpException(SdpException::BAD_RESPONSE, 'Unexpected response shape for ' . $key);
        }

        return $entity;
    }

    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->config->baseUrl, 'serviceKey' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('SdpClient cannot be serialised');
    }

    /**
     * Build a multipart/form-data body with one file part.
     *
     * @return array{0: string, 1: string}  [body, Content-Type header]
     */
    private static function multipart(string $field, string $filename, string $contentType, string $content): array
    {
        $boundary = '----SdpPortal' . bin2hex(random_bytes(12));
        $name     = str_replace(['"', '\\', "\r", "\n"], '_', $filename);

        $body = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $field . '"; filename="' . $name . '"' . "\r\n"
            . 'Content-Type: ' . $contentType . "\r\n\r\n"
            . $content . "\r\n"
            . '--' . $boundary . "--\r\n";

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }

    /**
     * @param  array<string, mixed>|null               $input
     * @param  array{0: string, 1: string}|null        $raw    Prebuilt [body, Content-Type] (uploads)
     *
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?array $input, #[\SensitiveParameter] string $authtoken, ?array $raw = null): array
    {
        if (!preg_match('#^/api/v3(/[a-z0-9_]+)+$#', $path)) {
            throw new \InvalidArgumentException('Invalid SDP API path');
        }

        $url     = $this->config->baseUrl . $path;
        $headers = ['Accept' => self::ACCEPT, 'authtoken' => $authtoken];
        $body    = null;

        if ($raw !== null) {
            [$body, $headers['Content-Type']] = $raw;
        } else {
            if ($input !== null) {
                $json = json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($method === 'GET') {
                    $url .= '?' . http_build_query(['input_data' => $json], '', '&', PHP_QUERY_RFC3986);
                } else {
                    $body = http_build_query(['input_data' => $json], '', '&', PHP_QUERY_RFC1738);
                }
            }

            if ($method !== 'GET') {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        $secrets = array_values(array_unique([...$this->secrets, $authtoken]));
        $started = hrtime(true);

        try {
            $response = $this->transport->send($method, $url, $headers, $body);
        } catch (TransportException $e) {
            $this->logger->warning(\sprintf('SDP %s %s failed: %s', $method, $path, Redactor::scrub($e->getMessage(), $secrets)));

            throw new SdpException(SdpException::NETWORK, 'Could not reach ServiceDesk Plus', 0, null, '', $e);
        }

        $elapsed = (int) ((hrtime(true) - $started) / 1_000_000);
        $decoded = $this->decode($response->body);
        [$failed, $sdpCode, $sdpMessage] = $this->readStatus($decoded);

        if ($response->status >= 400 || $failed) {
            $sdpMessage = Redactor::scrub($sdpMessage, $secrets);
            $exception  = $decoded === null && $response->status < 400
                ? new SdpException(SdpException::BAD_RESPONSE, 'ServiceDesk Plus returned an unreadable response', $response->status)
                : SdpException::fromResponse($response->status, $sdpCode, $sdpMessage);

            $this->logger->warning(\sprintf(
                'SDP %s %s -> HTTP %d, code %s, kind %s, %d ms: %s',
                $method,
                $path,
                $response->status,
                $sdpCode ?? '-',
                $exception->getKind(),
                $elapsed,
                $sdpMessage
            ));

            throw $exception;
        }

        if ($decoded === null) {
            $this->logger->warning(\sprintf('SDP %s %s -> HTTP %d with non-JSON body', $method, $path, $response->status));

            throw new SdpException(SdpException::BAD_RESPONSE, 'ServiceDesk Plus returned an unreadable response', $response->status);
        }

        $this->logger->info(\sprintf('SDP %s %s -> HTTP %d, %d ms', $method, $path, $response->status, $elapsed));

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null  null when the body is not a JSON object
     */
    private function decode(string $body): ?array
    {
        if (trim($body) === '') {
            return null;
        }

        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * Read response_status, which SDP returns either as an object or as an array of objects.
     *
     * @param  array<string, mixed>|null  $data
     *
     * @return array{0: bool, 1: ?int, 2: string}  [failed, first message status_code, message text]
     */
    private function readStatus(?array $data): array
    {
        $raw = $data['response_status'] ?? null;

        if (!\is_array($raw)) {
            return [false, null, ''];
        }

        $statuses = array_is_list($raw) ? $raw : [$raw];
        $failed   = false;
        $code     = null;
        $messages = [];

        foreach ($statuses as $status) {
            if (!\is_array($status)) {
                continue;
            }

            if (strtolower((string) ($status['status'] ?? '')) === 'failed') {
                $failed = true;
            }

            foreach ((array) ($status['messages'] ?? []) as $message) {
                if (!\is_array($message)) {
                    continue;
                }

                if ($code === null && isset($message['status_code']) && is_numeric($message['status_code'])) {
                    $candidate = (int) $message['status_code'];
                    $code      = $candidate >= 4000 ? $candidate : null;
                }

                if (isset($message['message']) && \is_string($message['message'])) {
                    $messages[] = $message['message'];
                }

                if (strtolower((string) ($message['type'] ?? '')) === 'failed') {
                    $failed = true;
                }
            }

            if ($code === null && isset($status['status_code']) && is_numeric($status['status_code'])) {
                $candidate = (int) $status['status_code'];
                $code      = $candidate >= 4000 && $candidate < 10000 ? $candidate : null;
            }
        }

        return [$failed, $code, implode(' ', $messages)];
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
