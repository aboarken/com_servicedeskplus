<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Site\View\Ui;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Approvals\HtmlView $this */

// Layouts from the component, unless the site template overrides them (html/layouts/com_servicedeskplus).
$layoutOptions = ['component' => 'com_servicedeskplus', 'client' => 0];

$date  = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$shows = [
    'pending'  => 'COM_SERVICEDESKPLUS_APPROVALS_SHOW_PENDING',
    'approved' => 'COM_SERVICEDESKPLUS_APPROVALS_SHOW_APPROVED',
    'rejected' => 'COM_SERVICEDESKPLUS_APPROVALS_SHOW_REJECTED',
    'decided'  => 'COM_SERVICEDESKPLUS_APPROVALS_SHOW_DECIDED',
];
$sorts = [
    'date_desc' => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_DATE_DESC',
    'date_asc'  => 'COM_SERVICEDESKPLUS_ANNOUNCEMENTS_SORT_DATE_ASC',
];
// Decision and result: label and colour.
$decisionLabel = [
    'approval.approve' => ['COM_SERVICEDESKPLUS_APPROVAL_HISTORY_APPROVE', 'green'],
    'approval.reject'  => ['COM_SERVICEDESKPLUS_APPROVAL_HISTORY_REJECT', 'red'],
];
$resultLabel = [
    'ok'     => ['COM_SERVICEDESKPLUS_APPROVAL_RESULT_OK', 'green'],
    'denied' => ['COM_SERVICEDESKPLUS_APPROVAL_RESULT_DENIED', 'amber'],
    'error'  => ['COM_SERVICEDESKPLUS_APPROVAL_RESULT_ERROR', 'red'],
];
$isPending = $this->show === 'pending';
?>
<div class="com-servicedeskplus sdp-approvals" data-sdp-root="approvals">
    <?php echo (new \Joomla\CMS\Layout\FileLayout('messages', null, ['component' => 'com_servicedeskplus', 'client' => 0]))->render(); ?>
    <?php if ($this->showPageHeading) : ?>
        <div class="sdp-head">
            <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
        </div>
    <?php endif; ?>

    <?php // Filters are applied on change, the search on Enter (media/js/filters.js). ?>
    <form class="sdp-toolbar" method="get" data-sdp-filters autocomplete="off" role="search" action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=approvals'); ?>">
        <div class="sdp-field--search">
            <label class="sdp-label" for="sdp-search"><?php echo Text::_('COM_SERVICEDESKPLUS_SEARCH'); ?></label>
            <input class="sdp-input" type="search" id="sdp-search" name="search" maxlength="100" enterkeyhint="search"
                   value="<?php echo $this->escape($this->search); ?>"
                   placeholder="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SEARCH_HINT')); ?>">
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
            <label class="sdp-label" for="sdp-type"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_TYPE'); ?></label>
            <select class="sdp-input" id="sdp-type" name="type">
                <option value="" <?php echo $this->type === '' ? 'selected' : ''; ?>><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_ALL_TYPES'); ?></option>
                <?php foreach ($this->modules as $module) : ?>
                    <option value="<?php echo $module; ?>" <?php echo $module === $this->type ? 'selected' : ''; ?>><?php echo Text::_(ApprovalModule::labelKey($module)); ?></option>
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
            <?php if ($isPending && $this->pending === []) : ?>
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_NONE'); ?></p>
            <?php elseif (!$isPending && $this->history === []) : ?>
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY_NONE'); ?></p>
            <?php elseif ($isPending) : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
                        <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_PENDING'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTER'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_SENT_ON'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_LEVEL'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->pending as $p) :
                            $url = Route::_('index.php?option=com_servicedeskplus&view=approval&id=' . rawurlencode($p->approvalId)
                                . '&request_id=' . rawurlencode($p->requestId) . '&level_id=' . rawurlencode($p->levelId)
                                . ($p->module !== ApprovalModule::REQUEST ? '&module=' . rawurlencode($p->module) : ''));
                            ?>
                            <tr>
                                <td class="sdp-id"><a href="<?php echo $url; ?>"><?php echo $this->escape($p->requestId); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $this->escape($p->subject !== '' ? $p->subject : '—'); ?></a></td>
                                <td><?php echo Ui::badge(Text::_(ApprovalModule::labelKey($p->module)), 'purple'); ?></td>
                                <td><?php echo $this->escape($p->requester !== '' ? $p->requester : '—'); ?></td>
                                <td class="sdp-nowrap"><?php echo $date($p->sentOn); ?></td>
                                <td><?php echo $this->escape($p->level !== '' ? $p->level : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
                        <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_DECISION'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_RESULT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_DATE'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->history as $h) :
                            // Decisions recorded before 1.10.0 have no module (they were all requests) and no subject.
                            $hModule   = ApprovalModule::normalize((string) ($h['module'] ?? '')) ?? ApprovalModule::REQUEST;
                            $hSubject  = trim((string) ($h['subject'] ?? ''));
                            $decision  = $decisionLabel[$h['action']] ?? ['JNONE', 'muted'];
                            $result    = $resultLabel[$h['result']] ?? ['JNONE', 'muted'];
                            ?>
                            <tr>
                                <td class="sdp-id"><?php echo $this->escape((string) $h['sdp_request_id']); ?></td>
                                <td><?php echo $hSubject !== '' ? $this->escape($hSubject) : '—'; ?></td>
                                <td><?php echo Ui::badge(Text::_(ApprovalModule::labelKey($hModule)), 'purple'); ?></td>
                                <td><?php echo Ui::badge(Text::_($decision[0]), $decision[1]); ?></td>
                                <td><?php echo Ui::badge(Text::_($result[0]), $result[1]); ?></td>
                                <td class="sdp-nowrap"><?php echo HTMLHelper::_('date', $h['created'], Text::_('DATE_FORMAT_LC5')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <?php if (!$isPending) : ?>
                <div class="sdp-panel-foot sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY_NOTE'); ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php echo (new FileLayout('pagination', null, $layoutOptions))->render(['page' => $this->page, 'pages' => $this->pages, 'url' => fn (int $p): string => $this->pageUrl($p)]); ?>
</div>
