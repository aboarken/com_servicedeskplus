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

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Announcements\HtmlView $this */

// Layouts from the component, unless the site template overrides them (html/layouts/com_servicedeskplus).
$layoutOptions = ['component' => 'com_servicedeskplus', 'client' => 0];

$date  = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$text  = fn (string $v): string => $v !== '' ? $this->escape($v) : '—';
$sorts = [
    'date_desc' => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_DATE_DESC',
    'date_asc'  => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_DATE_ASC',
    'end_asc'   => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_END_ASC',
    'title_asc' => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_TITLE_ASC',
];
?>
<div class="com-servicedeskplus sdp-announcements" data-sdp-root="announcements">
    <?php if ($this->showPageHeading) : ?>
        <div class="sdp-head">
            <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
        </div>
    <?php endif; ?>

    <?php // Filters are applied on change, the search on Enter (media/js/filters.js). ?>
    <form class="sdp-toolbar" method="get" data-sdp-filters autocomplete="off" role="search" action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=announcements'); ?>">
        <div class="sdp-field--search">
            <label class="sdp-label" for="sdp-search"><?php echo Text::_('COM_SERVICEDESKPLUS_SEARCH'); ?></label>
            <input class="sdp-input" type="search" id="sdp-search" name="search" maxlength="100" enterkeyhint="search"
                   value="<?php echo $this->escape($this->search); ?>"
                   placeholder="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SEARCH_HINT')); ?>">
        </div>
        <div>
            <label class="sdp-label" for="sdp-type"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_TYPE'); ?></label>
            <select class="sdp-input" id="sdp-type" name="type">
                <option value=""><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_ALL_TYPES'); ?></option>
                <?php foreach ($this->types as $type) : ?>
                    <option value="<?php echo $this->escape($type); ?>" <?php echo strcasecmp($type, $this->type) === 0 ? 'selected' : ''; ?>><?php echo $this->escape($type); ?></option>
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
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_NONE'); ?></p>
            <?php else : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
                        <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_TITLE'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_PRIORITY'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_START'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_END'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $item) :
                            $url = Route::_('index.php?option=com_servicedeskplus&view=announcement&id=' . rawurlencode($item->id));
                            ?>
                            <tr>
                                <td class="sdp-id"><a href="<?php echo $url; ?>"><?php echo $this->escape($item->id); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->title !== '' ? $item->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTITLED')); ?></a></td>
                                <td><?php echo Ui::badge($item->type, 'teal'); ?></td>
                                <td><?php echo $text($item->priority); ?></td>
                                <td class="sdp-nowrap"><?php echo $date($item->date()); ?></td>
                                <td class="sdp-nowrap"><?php echo $item->toTime !== null ? $date($item->toTime) : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_NO_END'); ?></td>
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
