<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\LoanAsset;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\AssetsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\ListPageTrait;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

/**
 * My Loans with search, "Show" (all, to return within 30 days, overdue, previous loans), sort and pages,
 * like My Requests.
 */
final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;
    use ListPageTrait;

    public const SHOWS = ['all', 'due_soon', 'overdue', 'previous'];

    public const SORTS = ['due_asc', 'due_desc', 'name_asc'];

    /** @var list<LoanAsset> Current page */
    public array $items = [];

    /** @var list<\SdpPortal\Component\ServiceDeskPlus\Administrator\Assets\PastLoan> Current page of "Returned" */
    public array $pastLoans = [];

    /** @var array<string, ?int> Totals for the cards: show => count (null when unknown) */
    public array $totals = [];

    public string $error = '';

    public int $now = 0;

    public function display($tpl = null): void
    {
        /** @var AssetsModel $model */
        $model = $this->getModel();

        $this->now = time();
        $this->readListInput(self::SHOWS, self::SORTS);

        try {
            $this->countTotals($model);

            if ($this->show === 'previous') {
                $this->showPrevious($model);
            } else {
                $items = array_values(array_filter($model->getItems(), fn (LoanAsset $a): bool => match ($this->show) {
                    'due_soon' => $a->isDueSoon($this->now),
                    'overdue'  => $a->isOverdue($this->now),
                    default    => true,
                } && ($this->search === '' || $a->id === $this->search || self::matches($this->search, $a->name, $a->product, $a->serialNumber, $a->type))));

                usort($items, fn (LoanAsset $a, LoanAsset $b): int => match ($this->sort) {
                    // Loans without a due date last.
                    'due_desc' => [$b->loanEnd ?? PHP_INT_MIN, $b->name] <=> [$a->loanEnd ?? PHP_INT_MIN, $a->name],
                    'name_asc' => strnatcasecmp($a->name, $b->name),
                    default    => [$a->loanEnd ?? PHP_INT_MAX, $a->name] <=> [$b->loanEnd ?? PHP_INT_MAX, $b->name],
                });

                $this->items = $this->pageOf($items);
            }
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $document = $this->getDocument();
        $this->prepareMenuPage('assets', 'COM_SERVICEDESKPLUS_ASSETS_TITLE');
        $document->setMetaData('robots', 'noindex, nofollow');
        $document->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $document->getWebAssetManager()->useStyle('com_servicedeskplus.portal')->useScript('com_servicedeskplus.filters');

        parent::display($tpl);
    }

    /**
     * Returned loans: newest return first ("Due date, latest first" and the default), oldest
     * first ("Due date, earliest first" chosen explicitly), or by asset name.
     */
    private function showPrevious(AssetsModel $model): void
    {
        $loans = array_values(array_filter(
            $model->getPastLoans(),
            fn ($l): bool => $this->search === '' || $l->number === $this->search || self::matches($this->search, $l->assetNames(), $l->comments)
        ));

        $when = static fn ($l): int => $l->returned ?? $l->due ?? $l->start ?? 0;
        // The list's default sort is "earliest first"; past loans open with the latest return first.
        $sortChosen = Factory::getApplication()->getInput()->getCmd('sort', '') !== '';

        usort($loans, fn ($a, $b): int => match ($this->sort) {
            'name_asc' => strnatcasecmp($a->assetNames(), $b->assetNames()),
            'due_asc'  => $sortChosen ? $when($a) <=> $when($b) : $when($b) <=> $when($a),
            default    => $when($b) <=> $when($a),
        });

        $this->pastLoans = $this->pageOf($loans);
    }

    /**
     * Totals of the cards above the list (the search is not applied to them). The returned loans
     * are counted without reading their assets; a failure there leaves that card without a number.
     */
    private function countTotals(AssetsModel $model): void
    {
        $current = $model->getItems();

        $this->totals = [
            'all'      => \count($current),
            'due_soon' => \count(array_filter($current, fn (LoanAsset $a): bool => $a->isDueSoon($this->now))),
            'overdue'  => \count(array_filter($current, fn (LoanAsset $a): bool => $a->isOverdue($this->now))),
            'previous' => null,
        ];

        try {
            $this->totals['previous'] = \count($model->getPastLoans(false));
        } catch (SdpException) {
            // Shown as "—"; the list itself reports the error when it is opened.
        }
    }

    public function pageUrl(int $page): string
    {
        return $this->listUrl('assets', $page, 'all', 'due_asc');
    }
}
