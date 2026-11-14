<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Immutable builder for the SDP v3 "list_info" object.
 */
final class ListInfo
{
    public const MAX_ROW_COUNT = 100;

    /**
     * @param  array<mixed>|null          $searchCriteria  SDP search_criteria (object or array form)
     * @param  array<string, string>|null $searchFields    SDP search_fields (field => value)
     */
    public function __construct(
        public readonly int $rowCount = 25,
        public readonly int $startIndex = 1,
        public readonly ?string $sortField = null,
        public readonly string $sortOrder = 'desc',
        public readonly ?array $searchCriteria = null,
        public readonly ?array $searchFields = null,
        public readonly bool $getTotalCount = false
    ) {
        if ($rowCount < 1 || $rowCount > self::MAX_ROW_COUNT) {
            throw new \InvalidArgumentException('row_count must be between 1 and 100');
        }

        if ($startIndex < 1) {
            throw new \InvalidArgumentException('start_index must be 1 or greater');
        }

        if (!\in_array($sortOrder, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('sort_order must be asc or desc');
        }

        if ($sortField !== null && !preg_match('/^[a-z0-9_.]{1,64}$/', $sortField)) {
            throw new \InvalidArgumentException('Invalid sort_field');
        }
    }

    public function withStartIndex(int $startIndex): self
    {
        return new self($this->rowCount, $startIndex, $this->sortField, $this->sortOrder, $this->searchCriteria, $this->searchFields, $this->getTotalCount);
    }

    public function withRowCount(int $rowCount): self
    {
        return new self($rowCount, $this->startIndex, $this->sortField, $this->sortOrder, $this->searchCriteria, $this->searchFields, $this->getTotalCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'row_count'   => $this->rowCount,
            'start_index' => $this->startIndex,
        ];

        if ($this->sortField !== null) {
            $out['sort_field'] = $this->sortField;
            $out['sort_order'] = $this->sortOrder;
        }

        if ($this->getTotalCount) {
            $out['get_total_count'] = true;
        }

        if ($this->searchCriteria !== null) {
            $out['search_criteria'] = $this->searchCriteria;
        }

        if ($this->searchFields !== null) {
            $out['search_fields'] = $this->searchFields;
        }

        return $out;
    }
}
