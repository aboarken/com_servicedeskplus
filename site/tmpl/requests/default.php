<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Requests\HtmlView $this */

$q      = $this->query;
$date   = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$sorts  = [
    'created_desc' => 'COM_SERVICEDESKPLUS_SORT_CREATED_DESC',
    'created_asc'  => 'COM_SERVICEDESKPLUS_SORT_CREATED_ASC',
    'updated_desc' => 'COM_SERVICEDESKPLUS_SORT_UPDATED_DESC',
    'updated_asc'  => 'COM_SERVICEDESKPLUS_SORT_UPDATED_ASC',
];
$currentSort = $q->sort . '_' . $q->direction;
?>
<div class="com-servicedeskplus sdp-requests">
    <?php /* Same grid as the filter bar below, so "Submit a Request" lines up with and has the size of "Search". */ ?>
    <div class="sdp-page-header row g-2 align-items-center mb-3">
        <div class="col">
            <?php if ($this->showPageHeading) : ?>
                <h1 class="h2 mb-0"><?php echo $this->escape($this->pageHeading); ?></h1>
            <?php endif; ?>
        </div>
        <?php if ($this->canCreate) : ?>
            <div class="col-12 col-sm-auto col-lg-2">
                <a class="btn btn-primary w-100 sdp-btn-nowrap" href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=requestform'); ?>">
                    <?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_A_REQUEST'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <form class="sdp-filters row g-2 align-items-end mb-3" method="get" action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=requests'); ?>">
        <div class="col-12 col-lg-5">
            <label class="form-label" for="sdp-search"><?php echo Text::_('COM_SERVICEDESKPLUS_SEARCH'); ?></label>
            <input class="form-control" type="search" id="sdp-search" name="search" maxlength="100"
                   value="<?php echo $this->escape($q->search); ?>"
                   placeholder="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_SEARCH_HINT')); ?>">
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" for="sdp-status"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></label>
            <select class="form-select" id="sdp-status" name="status">
                <option value="<?php echo RequestQuery::ALL_STATUSES; ?>" <?php echo $q->status === '' ? 'selected' : ''; ?>><?php echo Text::_('COM_SERVICEDESKPLUS_ALL_STATUSES'); ?></option>
                <?php foreach ($this->statuses as $status) : ?>
                    <option value="<?php echo $this->escape($status); ?>" <?php echo $status === $q->status ? 'selected' : ''; ?>><?php echo $this->escape($status); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <label class="form-label" for="sdp-sort"><?php echo Text::_('COM_SERVICEDESKPLUS_SORT'); ?></label>
            <select class="form-select" id="sdp-sort" name="sort">
                <?php foreach ($sorts as $value => $label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo $value === $currentSort ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-2 sdp-filter-submit">
            <button class="btn btn-secondary sdp-btn-neutral w-100" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
        </div>
    </form>

    <?php if ($this->error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php elseif ($this->result === null || $this->result->items === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTS_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_TECHNICIAN'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_CREATED'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_UPDATED'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->result->items as $item) :
                    $url = Route::_('index.php?option=com_servicedeskplus&view=request&id=' . rawurlencode($item->id));
                    ?>
                    <tr>
                        <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->id); ?></a></td>
                        <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->subject); ?></a></td>
                        <td><?php echo $this->escape($item->status); ?></td>
                        <td><?php echo $this->escape($item->technician !== '' ? $item->technician : Text::_('COM_SERVICEDESKPLUS_UNASSIGNED')); ?></td>
                        <td><?php echo $date($item->createdAt); ?></td>
                        <td><?php echo $date($item->updatedAt); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($this->result !== null && ($this->result->page > 1 || $this->result->hasMore)) :
        $pages = $this->result->total !== null ? (int) ceil($this->result->total / $q->limit) : null;
        ?>
        <nav aria-label="<?php echo $this->escape(Text::_('JLIB_HTML_PAGINATION')); ?>">
            <ul class="pagination">
                <li class="page-item <?php echo $this->result->page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $this->pageUrl($this->result->page - 1); ?>"><?php echo Text::_('JPREV'); ?></a>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">
                        <?php echo $pages ? Text::sprintf('COM_SERVICEDESKPLUS_PAGE_N_OF_M', $this->result->page, $pages) : Text::sprintf('COM_SERVICEDESKPLUS_PAGE_N', $this->result->page); ?>
                    </span>
                </li>
                <li class="page-item <?php echo $this->result->hasMore ? '' : 'disabled'; ?>">
                    <a class="page-link" href="<?php echo $this->pageUrl($this->result->page + 1); ?>"><?php echo Text::_('JNEXT'); ?></a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
