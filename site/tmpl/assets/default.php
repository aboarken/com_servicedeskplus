<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Assets\HtmlView $this */

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
<div class="com-servicedeskplus sdp-assets">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <form class="sdp-filters row g-2 align-items-end mb-3" method="get" action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=assets'); ?>">
        <div class="col-12 col-lg-5">
            <label class="form-label" for="sdp-search"><?php echo Text::_('COM_SERVICEDESKPLUS_SEARCH'); ?></label>
            <input class="form-control" type="search" id="sdp-search" name="search" maxlength="100"
                   value="<?php echo $this->escape($this->search); ?>"
                   placeholder="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_LOANS_SEARCH_HINT')); ?>">
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="sdp-show"><?php echo Text::_('COM_SERVICEDESKPLUS_SHOW'); ?></label>
            <select class="form-select" id="sdp-show" name="show">
                <?php foreach ($shows as $value => $label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo $value === $this->show ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label" for="sdp-sort"><?php echo Text::_('COM_SERVICEDESKPLUS_SORT'); ?></label>
            <select class="form-select" id="sdp-sort" name="sort">
                <?php foreach ($sorts as $value => $label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo $value === $this->sort ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-2 sdp-filter-submit">
            <button class="btn btn-secondary sdp-btn-neutral w-100" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
        </div>
    </form>

    <?php if ($this->error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php elseif ($this->items === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
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
                    $url     = Route::_('index.php?option=com_servicedeskplus&view=asset&id=' . rawurlencode($item->id));
                    ?>
                    <tr class="<?php echo $overdue ? 'sdp-overdue' : ''; ?>">
                        <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->id); ?></a></td>
                        <td><a href="<?php echo $url; ?>"><?php echo $text($item->name); ?></a></td>
                        <td><?php echo $text($item->type); ?></td>
                        <td><?php echo $text($item->product); ?></td>
                        <td><?php echo $text($item->serialNumber); ?></td>
                        <td><?php echo $text($item->state); ?></td>
                        <td><?php echo $date($item->loanStart); ?></td>
                        <td>
                            <?php echo $date($item->loanEnd); ?>
                            <?php if ($overdue) : ?>
                                <span class="text-danger fw-semibold">· <?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_OVERDUE'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php echo LayoutHelper::render('pagination', ['page' => $this->page, 'pages' => $this->pages, 'url' => fn (int $p): string => $this->pageUrl($p)], JPATH_SITE . '/components/com_servicedeskplus/layouts'); ?>
</div>
