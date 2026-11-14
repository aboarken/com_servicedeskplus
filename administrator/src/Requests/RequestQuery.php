<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validated list parameters for "My requests". Anything unexpected falls back to a default.
 */
final class RequestQuery
{
    public const PAGE_SIZE = 20;
    public const MAX_PAGE  = 500;
    public const SORTS     = ['created' => 'created_time', 'updated' => 'last_updated_time'];

    public readonly int $page;
    public readonly string $search;
    public readonly string $status;
    public readonly string $sort;
    public readonly string $direction;

    /**
     * @param  list<string>  $allowedStatuses
     */
    public function __construct(
        int $page = 1,
        string $search = '',
        string $status = '',
        string $sort = 'created',
        string $direction = 'desc',
        array $allowedStatuses = [],
        public readonly int $limit = self::PAGE_SIZE
    ) {
        $this->page      = max(1, min(self::MAX_PAGE, $page));
        $search          = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $search) ?? '');
        $this->search    = mb_substr($search, 0, 100);
        $this->status    = \in_array($status, $allowedStatuses, true) ? $status : '';
        $this->sort      = isset(self::SORTS[$sort]) ? $sort : 'created';
        $this->direction = $direction === 'asc' ? 'asc' : 'desc';
    }

    public function sortField(): string
    {
        return self::SORTS[$this->sort];
    }

    public function startIndex(): int
    {
        return ($this->page - 1) * $this->limit + 1;
    }

    public function isIdSearch(): bool
    {
        return preg_match('/^#?[0-9]{1,20}$/', $this->search) === 1;
    }

    public function searchId(): string
    {
        return ltrim($this->search, '#');
    }

    public function cacheKey(): string
    {
        return 'requests:' . hash('sha256', json_encode([$this->page, $this->search, $this->status, $this->sort, $this->direction, $this->limit]));
    }
}
