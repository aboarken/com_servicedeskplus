<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * Counts of one user's requests for the Dashboard: service requests and incidents by status,
 * overdue requests and the newest requests.
 */
final class RequestStats
{
    public const BUCKETS = ['open', 'on_hold', 'resolved', 'closed', 'canceled', 'other'];

    /**
     * @param  array<string, int>      $service    bucket => count, plus "total"
     * @param  array<string, int>      $incident   bucket => count, plus "total"
     * @param  list<RequestSummary>    $recent     Newest requests
     * @param  bool                    $truncated  More requests exist than were counted
     */
    public function __construct(
        public readonly array $service,
        public readonly array $incident,
        public readonly int $overdue,
        public readonly array $recent,
        public readonly bool $truncated
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $requests  SDP request list rows, newest first
     */
    public static function fromSdp(array $requests, bool $truncated, int $recentCount = 5): self
    {
        $empty    = array_fill_keys([...self::BUCKETS, 'total'], 0);
        $service  = $empty;
        $incident = $empty;
        $overdue  = 0;

        foreach ($requests as $r) {
            $bucket = self::bucket(SdpFields::name($r, 'status'));

            if (self::isServiceRequest($r)) {
                $service[$bucket]++;
                $service['total']++;
            } else {
                $incident[$bucket]++;
                $incident['total']++;
            }

            if (!\in_array($bucket, ['resolved', 'closed', 'canceled'], true) && filter_var($r['is_overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $overdue++;
            }
        }

        return new self(
            $service,
            $incident,
            $overdue,
            array_map(static fn (array $r) => RequestSummary::fromSdp($r), \array_slice($requests, 0, $recentCount)),
            $truncated
        );
    }

    public static function bucket(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'open'                  => 'open',
            'on hold', 'onhold'     => 'on_hold',
            'resolved'              => 'resolved',
            'closed'                => 'closed',
            'canceled', 'cancelled' => 'canceled',
            default                 => 'other',
        };
    }

    /**
     * Service request or incident: SDP's is_service_request flag, else the template's
     * is_service_template, else whether a service category is set.
     *
     * @param array<string, mixed> $r
     */
    public static function isServiceRequest(array $r): bool
    {
        if (\array_key_exists('is_service_request', $r)) {
            return filter_var($r['is_service_request'], FILTER_VALIDATE_BOOLEAN);
        }

        if (\is_array($r['template'] ?? null) && \array_key_exists('is_service_template', $r['template'])) {
            return filter_var($r['template']['is_service_template'], FILTER_VALIDATE_BOOLEAN);
        }

        return \is_array($r['service_category'] ?? null) && ($r['service_category']['id'] ?? null) !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service'   => $this->service,
            'incident'  => $this->incident,
            'overdue'   => $this->overdue,
            'recent'    => array_map(static fn (RequestSummary $s) => $s->toArray(), $this->recent),
            'truncated' => $this->truncated,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            array_map('intval', (array) ($a['service'] ?? [])) + array_fill_keys([...self::BUCKETS, 'total'], 0),
            array_map('intval', (array) ($a['incident'] ?? [])) + array_fill_keys([...self::BUCKETS, 'total'], 0),
            (int) ($a['overdue'] ?? 0),
            array_map(static fn (array $s) => RequestSummary::fromArray($s), (array) ($a['recent'] ?? [])),
            (bool) ($a['truncated'] ?? false)
        );
    }
}
