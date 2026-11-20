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
use SdpPortal\Component\ServiceDeskPlus\Site\View\ListPageTrait;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;
    use ListPageTrait;

    public const SORTS = ['date_desc', 'date_asc', 'end_asc', 'title_asc'];

    /** @var list<Announcement> Announcements of the current page */
    public array $items = [];

    /** @var list<string> Types of all announcements the user may see, for the filter */
    public array $types = [];

    public string $type = '';

    /** @var array<string, int> Cards above the list: type => announcements (search applied, '' = all types) */
    public array $totals = [];

    public string $error = '';

    public function display($tpl = null): void
    {
        /** @var AnnouncementsModel $model */
        $model = $this->getModel();
        $input = Factory::getApplication()->getInput();

        $this->readListInput([''], self::SORTS);
        $this->type = mb_substr(trim($input->getString('type', '')), 0, 100);

        try {
            $all = $model->getItems();

            $this->types = array_values(array_unique(array_filter(array_map(static fn (Announcement $a) => $a->type, $all))));
            sort($this->types, SORT_NATURAL | SORT_FLAG_CASE);

            // The cards count the search's matches per type; the list also applies the type.
            $searched     = $this->searched($all);
            $this->totals = ['' => \count($searched)] + array_fill_keys($this->types, 0);

            foreach ($searched as $a) {
                if ($a->type !== '' && isset($this->totals[$a->type])) {
                    $this->totals[$a->type]++;
                }
            }

            $this->items = $this->pageOf($this->sorted($this->filtered($all)));
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('announcements', 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal')->useScript('com_servicedeskplus.filters');

        parent::display($tpl);
    }

    /**
     * Link to this list with the current filters and a different page.
     */
    public function pageUrl(int $page): string
    {
        return $this->listUrl('announcements', $page, '', 'date_desc', ['type' => $this->type]);
    }

    /**
     * @param  list<Announcement>  $items
     *
     * @return list<Announcement>
     */
    private function filtered(array $items): array
    {
        return array_values(array_filter($this->searched($items), fn (Announcement $a): bool => $this->type === '' || strcasecmp($a->type, $this->type) === 0));
    }

    /**
     * @param  list<Announcement>  $items
     *
     * @return list<Announcement>
     */
    private function searched(array $items): array
    {
        return array_values(array_filter($items, fn (Announcement $a): bool => $this->search === '' || $a->id === $this->search || self::matches($this->search, $a->title, $a->content)));
    }

    /** Link to the list with the current search and sort, and the given type. */
    public function typeUrl(string $type): string
    {
        $params = array_filter([
            'search' => $this->search,
            'sort'   => $this->sort !== 'date_desc' ? $this->sort : '',
            'type'   => $type,
        ], static fn (string $v): bool => $v !== '');

        return Route::_('index.php?option=com_servicedeskplus&view=announcements' . ($params !== [] ? '&' . http_build_query($params) : ''));
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
