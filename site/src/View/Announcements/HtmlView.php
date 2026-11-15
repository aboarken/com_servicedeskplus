<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Announcements;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements\Announcement;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AnnouncementsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    public const PAGE_SIZE = 25;

    public const SORTS = ['date_desc', 'date_asc', 'end_asc', 'title_asc'];

    /** @var list<Announcement> Announcements of the current page */
    public array $items = [];

    /** @var list<string> Types of all announcements the user may see, for the filter */
    public array $types = [];

    public string $search = '';

    public string $type = '';

    public string $sort = 'date_desc';

    public int $page = 1;

    public int $pages = 1;

    public int $total = 0;

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var AnnouncementsModel $model */
        $model = $this->getModel();
        $input = Factory::getApplication()->getInput();

        $this->search = mb_substr(trim($input->getString('search', '')), 0, 100);
        $this->type   = mb_substr(trim($input->getString('type', '')), 0, 100);
        $sort         = $input->getCmd('sort', 'date_desc');
        $this->sort   = \in_array($sort, self::SORTS, true) ? $sort : 'date_desc';

        try {
            $all = $model->getItems();

            $this->types = array_values(array_unique(array_filter(array_map(static fn (Announcement $a) => $a->type, $all))));
            sort($this->types, SORT_NATURAL | SORT_FLAG_CASE);

            $list        = $this->sorted($this->filtered($all));
            $this->total = \count($list);
            $this->pages = max(1, (int) ceil($this->total / self::PAGE_SIZE));
            $this->page  = min(max(1, $input->getInt('page', 1)), $this->pages);
            $this->items = \array_slice($list, ($this->page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('announcements', 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal');

        parent::display($tpl);
    }

    /**
     * Link to this list with the current filters and a different page.
     */
    public function pageUrl(int $page): string
    {
        $params = array_filter([
            'search' => $this->search,
            'type'   => $this->type,
            'sort'   => $this->sort !== 'date_desc' ? $this->sort : '',
            'page'   => $page > 1 ? (string) $page : '',
        ], static fn (string $v) => $v !== '');

        return Route::_('index.php?option=com_servicedeskplus&view=announcements' . ($params !== [] ? '&' . http_build_query($params) : ''));
    }

    /**
     * @param  list<Announcement>  $items
     *
     * @return list<Announcement>
     */
    private function filtered(array $items): array
    {
        $needle = mb_strtolower($this->search);

        return array_values(array_filter($items, function (Announcement $a) use ($needle): bool {
            if ($this->type !== '' && strcasecmp($a->type, $this->type) !== 0) {
                return false;
            }

            return $needle === ''
                || $a->id === $this->search
                || str_contains(mb_strtolower($a->title), $needle)
                || str_contains(mb_strtolower($a->content), $needle);
        }));
    }

    /**
     * @param  list<Announcement>  $items
     *
     * @return list<Announcement>
     */
    private function sorted(array $items): array
    {
        usort($items, fn (Announcement $a, Announcement $b): int => match ($this->sort) {
            'date_asc'  => [$a->date() ?? 0, (int) $a->id] <=> [$b->date() ?? 0, (int) $b->id],
            // Announcements without an end date last.
            'end_asc'   => [$a->toTime ?? PHP_INT_MAX, (int) $a->id] <=> [$b->toTime ?? PHP_INT_MAX, (int) $b->id],
            'title_asc' => strnatcasecmp($a->title, $b->title) ?: (int) $a->id <=> (int) $b->id,
            default     => [$b->date() ?? 0, (int) $b->id] <=> [$a->date() ?? 0, (int) $a->id],
        });

        return $items;
    }
}
