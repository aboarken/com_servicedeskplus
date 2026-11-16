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
 * Counts of one user's requests for the Dashboard: open and on-hold service requests and
 * incidents with the newest of those requests (read row by row), and resolved and closed requests
 * (counted by ServiceDesk Plus, without reading them, so the Dashboard stays fast for users with a
 * long history).
 */
final class RequestStats
{
    /** Buckets the Dashboard counts from the rows it reads. */
    public const BUCKETS = ['open', 'on_hold'];

    /**
     * @param  array<string, int>      $service    bucket => count, plus "total"
     * @param  array<string, int>      $incident   bucket => count, plus "total"
     * @param  list<RequestSummary>    $recent     Newest open / on-hold requests
     * @param  bool                    $truncated  More requests exist than were counted
     * @param  array<string, array{incident: ?int, service: ?int, total: ?int}>  $counted
     *         resolved / closed => counts by SDP; null where SDP gave no count
     */
    public function __construct(
        public readonly array $service,
        public readonly array $incident,
        public readonly array $recent,
        public readonly bool $truncated,
        public readonly array $counted = []
    ) {
    }

    /**
     * A copy with the counts of the resolved and closed buckets.
     *
     * @param  array<string, array{incident: ?int, service: ?int, total: ?int}>  $counted
     */
    public function withCounted(array $counted): self
    {
        return new self($this->service, $this->incident, $this->recent, $this->truncated, $counted);
    }

    /**
     * @param  list<array<string, mixed>>  $requests  SDP request list rows (any order); rows in
     *                                               other buckets are ignored
     */
    public static function fromSdp(array $requests, bool $truncated, int $recentCount = 5): self
    {
        $empty    = self::empty();
        $service  = $empty;
        $incident = $empty;
        $active   = [];

        foreach ($requests as $r) {
            $bucket = self::bucket(SdpFields::name($r, 'status'));

            if (!\in_array($bucket, self::BUCKETS, true)) {
                continue;
            }

            if (self::isServiceRequest($r)) {
                $service[$bucket]++;
                $service['total']++;
            } else {
                $incident[$bucket]++;
                $incident['total']++;
            }

            $active[] = RequestSummary::fromSdp($r);
        }

        // Newest first.
        usort($active, static fn (RequestSummary $a, RequestSummary $b): int => ($b->createdAt ?? 0) <=> ($a->createdAt ?? 0));

        return new self($service, $incident, \array_slice($active, 0, $recentCount), $truncated);
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
        return self::serviceFlag($r) ?? (\is_array($r['service_category'] ?? null) && ($r['service_category']['id'] ?? null) !== null);
    }

    /**
     * SDP's own service request flag (is_service_request, else the template's
     * is_service_template), or null when the row has neither.
     *
     * @param array<string, mixed> $r
     */
    public static function serviceFlag(array $r): ?bool
    {
        if (\array_key_exists('is_service_request', $r)) {
            return filter_var($r['is_service_request'], FILTER_VALIDATE_BOOLEAN);
        }

        if (\is_array($r['template'] ?? null) && \array_key_exists('is_service_template', $r['template'])) {
            return filter_var($r['template']['is_service_template'], FILTER_VALIDATE_BOOLEAN);
        }

        return null;
    }

    /** @return array<string, int> */
    private static function empty(): array
    {
        return array_fill_keys([...self::BUCKETS, 'total'], 0);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'service'   => $this->service,
            'incident'  => $this->incident,
            'recent'    => array_map(static fn (RequestSummary $s) => $s->toArray(), $this->recent),
            'truncated' => $this->truncated,
            'counted'   => $this->counted,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            array_map('intval', (array) ($a['service'] ?? [])) + self::empty(),
            array_map('intval', (array) ($a['incident'] ?? [])) + self::empty(),
            array_map(static fn (array $s) => RequestSummary::fromArray($s), (array) ($a['recent'] ?? [])),
            (bool) ($a['truncated'] ?? false),
            array_map(
                static fn ($c): array => [
                    'incident' => isset($c['incident']) ? (int) $c['incident'] : null,
                    'service'  => isset($c['service']) ? (int) $c['service'] : null,
                    'total'    => isset($c['total']) ? (int) $c['total'] : null,
                ],
                array_filter((array) ($a['counted'] ?? []), 'is_array')
            )
        );
    }
}
