<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

final class RequestListResult
{
    /**
     * @param  list<RequestSummary>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly bool $hasMore,
        public readonly ?int $total
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items'   => array_map(static fn (RequestSummary $s) => $s->toArray(), $this->items),
            'page'    => $this->page,
            'hasMore' => $this->hasMore,
            'total'   => $this->total,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            array_map(static fn (array $s) => RequestSummary::fromArray($s), $a['items'] ?? []),
            (int) ($a['page'] ?? 1),
            (bool) ($a['hasMore'] ?? false),
            isset($a['total']) ? (int) $a['total'] : null
        );
    }
}
