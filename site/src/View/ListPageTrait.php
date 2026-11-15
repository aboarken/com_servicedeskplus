<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * Search, filter, sort and page for portal lists that are read whole and shown in pages of 25
 * (My Approvals, My Loans, Announcements), so they behave like My Requests.
 */
trait ListPageTrait
{
    public string $search = '';

    public string $show = '';

    public string $sort = '';

    public int $page = 1;

    public int $pages = 1;

    public int $total = 0;

    /**
     * Read search, show and sort from the URL; unknown values fall back to the first allowed one.
     *
     * @param  list<string>  $shows
     * @param  list<string>  $sorts
     */
    protected function readListInput(array $shows, array $sorts): void
    {
        $input        = Factory::getApplication()->getInput();
        $this->search = mb_substr(trim($input->getString('search', '')), 0, 100);
        $show         = $input->getCmd('show', '');
        $sort         = $input->getCmd('sort', '');
        $this->show   = \in_array($show, $shows, true) ? $show : ($shows[0] ?? '');
        $this->sort   = \in_array($sort, $sorts, true) ? $sort : ($sorts[0] ?? '');
    }

    /**
     * The current page of $items (25 per page); sets page, pages and total.
     *
     * @template T
     *
     * @param  list<T>  $items
     *
     * @return list<T>
     */
    protected function pageOf(array $items, int $size = 25): array
    {
        $this->total = \count($items);
        $this->pages = max(1, (int) ceil($this->total / $size));
        $this->page  = min(max(1, Factory::getApplication()->getInput()->getInt('page', 1)), $this->pages);

        return \array_slice($items, ($this->page - 1) * $size, $size);
    }

    /**
     * Link to this list with the current search, filter and sort, and a different page.
     *
     * @param  array<string, string>  $extra  further filters to keep (empty values are left out)
     */
    protected function listUrl(string $view, int $page, string $defaultShow, string $defaultSort, array $extra = []): string
    {
        $params = array_filter([
            'search' => $this->search,
            'show'   => $this->show !== $defaultShow ? $this->show : '',
            'sort'   => $this->sort !== $defaultSort ? $this->sort : '',
        ] + $extra + ['page' => $page > 1 ? (string) $page : ''], static fn (string $v) => $v !== '');

        return Route::_('index.php?option=com_servicedeskplus&view=' . $view . ($params !== [] ? '&' . http_build_query($params) : ''));
    }

    protected static function matches(string $needle, string ...$haystacks): bool
    {
        if ($needle === '') {
            return true;
        }

        $needle = mb_strtolower($needle);

        foreach ($haystacks as $haystack) {
            if (str_contains(mb_strtolower($haystack), $needle)) {
                return true;
            }
        }

        return false;
    }
}
