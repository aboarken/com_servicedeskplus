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
 * incidents, by age and by priority (read row by row), and resolved and closed requests
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
     * @param  bool                    $truncated  More requests exist than were counted
     * @param  array<string, array{incident: ?int, service: ?int, total: ?int}>  $counted
     *         resolved / closed => counts by SDP; null where SDP gave no count
     * @param  array<string, int>      $age        AGE_BUCKETS => open and on-hold requests
     * @param  list<array{name: string, count: int, other: bool}>  $priority  most frequent first
     */
    public function __construct(
        public readonly array $service,
        public readonly array $incident,
        public readonly bool $truncated,
        public readonly array $counted = [],
        public readonly array $age = [],
        public readonly array $priority = []
    ) {
    }

    /**
     * A copy with the counts of the resolved and closed buckets.
     *
     * @param  array<string, array{incident: ?int, service: ?int, total: ?int}>  $counted
     */
    public function withCounted(array $counted): self
    {
        return new self($this->service, $this->incident, $this->truncated, $counted, $this->age, $this->priority);
    }

    /**
     * Open and on-hold requests counted by bucket and type, by age (from created_time) and by
     * priority, for the Dashboard figures and charts.
     *
     * @param  list<array<string, mixed>>  $requests  SDP request list rows (any order); rows in
     *                                               other buckets are ignored
     */
    public static function fromSdp(array $requests, bool $truncated, ?int $now = null): self
    {
        $now      ??= time();
        $empty    = self::empty();
        $service  = $empty;
        $incident = $empty;
        $age      = array_fill_keys(self::AGE_BUCKETS, 0);
        $priority = [];

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

            $created = SdpFields::timestamp($r, 'created_time');

            if ($created !== null) {
                $age[self::ageBucket($now - $created)]++;
            }

            $name            = mb_substr(SdpFields::name($r, 'priority'), 0, 60);
            $priority[$name] = ($priority[$name] ?? 0) + 1;
        }

        return new self($service, $incident, $truncated, [], $age, self::topPriorities($priority));
    }

    /** Age buckets of open requests, youngest first. */
    public const AGE_BUCKETS = ['week', 'month', 'quarter', 'older'];

    /** Most priorities shown; the rest are added up under "other". */
    private const MAX_PRIORITIES = 6;

    public static function ageBucket(int $seconds): string
    {
        return match (true) {
            $seconds < 7 * 86400  => 'week',
            $seconds < 30 * 86400 => 'month',
            $seconds < 90 * 86400 => 'quarter',
            default               => 'older',
        };
    }

    /** Usual SDP priority names, in their natural order (lower case). */
    public const PRIORITY_ORDER = ['low', 'normal', 'medium', 'high', 'urgent', 'critical'];

    /**
     * The six most frequent priorities, shown in their natural order (Low, Normal, Medium, High,
     * Urgent, Critical, then other names alphabetically), so a priority keeps its place and colour
     * when counts change; then requests without a priority (name ""), then the rest as "other".
     *
     * @param  array<string, int>  $counts
     *
     * @return list<array{name: string, count: int, other: bool}>
     */
    private static function topPriorities(array $counts): array
    {
        $none = (int) ($counts[''] ?? 0);
        unset($counts['']);
        arsort($counts);

        $kept  = \array_slice($counts, 0, self::MAX_PRIORITIES, true);
        $other = array_sum($counts) - array_sum($kept);

        uksort($kept, static function ($a, $b): int {
            $ra = array_search(mb_strtolower(trim((string) $a)), self::PRIORITY_ORDER, true);
            $rb = array_search(mb_strtolower(trim((string) $b)), self::PRIORITY_ORDER, true);

            return [$ra === false ? 99 : $ra, mb_strtolower((string) $a)] <=> [$rb === false ? 99 : $rb, mb_strtolower((string) $b)];
        });

        $out = [];

        foreach ($kept as $name => $count) {
            $out[] = ['name' => (string) $name, 'count' => $count, 'other' => false];
        }

        if ($none > 0) {
            $out[] = ['name' => '', 'count' => $none, 'other' => false];
        }

        if ($other > 0) {
            $out[] = ['name' => '', 'count' => $other, 'other' => true];
        }

        return $out;
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
            'truncated' => $this->truncated,
            'counted'   => $this->counted,
            'age'       => $this->age,
            'priority'  => $this->priority,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            array_map('intval', (array) ($a['service'] ?? [])) + self::empty(),
            array_map('intval', (array) ($a['incident'] ?? [])) + self::empty(),
            (bool) ($a['truncated'] ?? false),
            array_map(
                static fn ($c): array => [
                    'incident' => isset($c['incident']) ? (int) $c['incident'] : null,
                    'service'  => isset($c['service']) ? (int) $c['service'] : null,
                    'total'    => isset($c['total']) ? (int) $c['total'] : null,
                ],
                array_filter((array) ($a['counted'] ?? []), 'is_array')
            ),
            array_map('intval', (array) ($a['age'] ?? [])) + array_fill_keys(self::AGE_BUCKETS, 0),
            array_values(array_map(
                static fn (array $p): array => ['name' => (string) ($p['name'] ?? ''), 'count' => (int) ($p['count'] ?? 0), 'other' => (bool) ($p['other'] ?? false)],
                array_filter((array) ($a['priority'] ?? []), 'is_array')
            ))
        );
    }
}
