<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Site\View\Ui;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Assets\HtmlView $this */

// Layouts from the component, unless the site template overrides them (html/layouts/com_servicedeskplus).
$layoutOptions = ['component' => 'com_servicedeskplus', 'client' => 0];

$date  = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC4')) : '—';
$text  = fn (string $v): string => $v !== '' ? $this->escape($v) : '—';
$shows = [
    'all'      => 'COM_SERVICEDESKPLUS_LOANS_SHOW_ALL',
    'due_soon' => 'COM_SERVICEDESKPLUS_LOANS_SHOW_DUE_SOON',
    'overdue'  => 'COM_SERVICEDESKPLUS_LOANS_SHOW_OVERDUE',
];
$sorts = [
    'due_asc'  => 'COM_SERVICEDESKPLUS_LOANS_SORT_DUE_ASC',
    'due_desc' => 'COM_SERVICEDESKPLUS_LOANS_SORT_DUE_DESC',
    'name_asc' => 'COM_SERVICEDESKPLUS_LOANS_SORT_NAME_ASC',
];
?>
<div class="com-servicedeskplus sdp-assets" data-sdp-root="assets">
    <?php if ($this->showPageHeading) : ?>
        <div class="sdp-head">
            <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
        </div>
    <?php endif; ?>

    <?php // Filters are applied on change, the search on Enter (media/js/filters.js). ?>
    <form class="sdp-toolbar" method="get" data-sdp-filters autocomplete="off" role="search" action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=assets'); ?>">
        <div class="sdp-field--search">
            <label class="sdp-label" for="sdp-search"><?php echo Text::_('COM_SERVICEDESKPLUS_SEARCH'); ?></label>
            <input class="sdp-input" type="search" id="sdp-search" name="search" maxlength="100" enterkeyhint="search"
                   value="<?php echo $this->escape($this->search); ?>"
                   placeholder="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_LOANS_SEARCH_HINT')); ?>">
        </div>
        <div>
            <label class="sdp-label" for="sdp-show"><?php echo Text::_('COM_SERVICEDESKPLUS_SHOW'); ?></label>
            <select class="sdp-input" id="sdp-show" name="show">
                <?php foreach ($shows as $value => $label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo $value === $this->show ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="sdp-label" for="sdp-sort"><?php echo Text::_('COM_SERVICEDESKPLUS_SORT'); ?></label>
            <select class="sdp-input" id="sdp-sort" name="sort">
                <?php foreach ($sorts as $value => $label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo $value === $this->sort ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if ($this->error !== '') : ?>
        <div class="sdp-alert sdp-tone-red" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else : ?>
        <section class="sdp-panel">
            <?php if ($this->items === []) : ?>
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_NONE'); ?></p>
            <?php else : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
                        <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_TITLE'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_NAME'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_PRODUCT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_SERIAL'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_STATE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_START'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_DUE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $item) :
                            $overdue = $item->isOverdue($this->now);
                            $dueSoon = !$overdue && $item->isDueSoon($this->now);
                            $url     = Route::_('index.php?option=com_servicedeskplus&view=asset&id=' . rawurlencode($item->id));
                            ?>
                            <tr class="<?php echo $overdue ? 'sdp-overdue' : ''; ?>">
                                <td class="sdp-id"><a href="<?php echo $url; ?>"><?php echo $this->escape($item->id); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $text($item->name); ?></a></td>
                                <td><?php echo $text($item->type); ?></td>
                                <td><?php echo $text($item->product); ?></td>
                                <td><?php echo $text($item->serialNumber); ?></td>
                                <td><?php echo Ui::badge($item->state, 'slate'); ?></td>
                                <td class="sdp-nowrap"><?php echo $date($item->loanStart); ?></td>
                                <td class="sdp-nowrap">
                                    <?php echo $date($item->loanEnd); ?>
                                    <?php if ($overdue) : ?>
                                        <?php echo Ui::badge(Text::_('COM_SERVICEDESKPLUS_LOAN_OVERDUE'), 'red'); ?>
                                    <?php elseif ($dueSoon) : ?>
                                        <?php echo Ui::badge(Text::_('COM_SERVICEDESKPLUS_LOAN_DUE_SOON'), 'amber'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php echo (new FileLayout('pagination', null, $layoutOptions))->render(['page' => $this->page, 'pages' => $this->pages, 'url' => fn (int $p): string => $this->pageUrl($p)]); ?>
</div>
