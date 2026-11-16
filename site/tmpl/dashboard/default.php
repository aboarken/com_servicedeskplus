<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */

$date    = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$buckets = [
    'open'    => 'COM_SERVICEDESKPLUS_DASHBOARD_OPEN',
    'on_hold' => 'COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD',
];
// A number that opens its page; "—" when it is not known.
$link = fn (?int $value, string $url): string => $value === null ? '—' : '<a href="' . $url . '">' . $value . '</a>';
$card = function (string $label, int $value, string $url): string {
    return '<div class="col-6 col-md-4 col-xl"><div class="card h-100 sdp-stat"><div class="card-body">'
        . '<div class="small text-muted">' . $this->escape($label) . '</div>'
        . '<div class="fs-2 fw-semibold"><a class="stretched-link text-reset text-decoration-none" href="' . $url . '">' . $value . '</a></div>'
        . '</div></div></div>';
};
// Opening of a block in the row of three (My Requests, My Approvals, My Loans).
$block = fn (string $titleKey): string => '<div class="col-12 col-lg"><section class="card h-100 sdp-block">'
    . '<div class="card-header"><h2 class="h4 mb-0">' . Text::_($titleKey) . '</h2></div><div class="card-body">';
$blockEnd = '</div></section></div>';
$error    = fn (string $message): string => '<div class="alert alert-danger mb-0" role="alert">' . $this->escape($message) . '</div>';
$r        = $this->requests;
?>
<div class="com-servicedeskplus sdp-dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2 mb-3"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php // Summary cards ?>
    <div class="row g-3 mb-4">
        <?php if ($r !== null) :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OPEN_REQUESTS'), $r->service['open'] + $r->incident['open'], $this->statusUrl('open'));
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD_REQUESTS'), $r->service['on_hold'] + $r->incident['on_hold'], $this->statusUrl('on_hold'));
        endif; ?>
        <?php if ($this->showApprovals && $this->approvalsError === '') :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING_APPROVAL'), $this->approvals['total'] ?? 0, $this->url('approvals'));
        endif; ?>
        <?php if ($this->showLoans && $this->loansError === '') :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS_TO_RETURN'), $this->loansDueSoon, $this->url('assets&show=due_soon'));
        endif; ?>
        <?php if ($this->showAnnouncements && $this->announcementsError === '') :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ALL_ANNOUNCEMENTS'), $this->announcementsTotal, $this->url('announcements'));
        endif; ?>
    </div>

    <?php if ($this->showAnnouncements) : ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ANNOUNCEMENTS'); ?></h2>
        <?php if ($this->announcementsError !== '') : ?>
            <div class="alert alert-danger mb-4" role="alert"><?php echo $this->escape($this->announcementsError); ?></div>
        <?php elseif ($this->announcements === []) : ?>
            <p class="text-muted mb-4"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_NONE'); ?></p>
        <?php else : ?>
            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ANNOUNCEMENTS'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_TYPE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_START'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->announcements as $a) :
                        $url = $this->url('announcement&id=' . rawurlencode($a->id));
                        ?>
                        <tr>
                            <td><a href="<?php echo $url; ?>"><?php echo $this->escape($a->id); ?></a></td>
                            <td><a href="<?php echo $url; ?>"><?php echo $this->escape($a->title !== '' ? $a->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTITLED')); ?></a></td>
                            <td><?php echo $this->escape($a->type !== '' ? $a->type : '—'); ?></td>
                            <td><?php echo $date($a->date()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mb-4"><a href="<?php echo $this->url('announcements'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_VIEW_ALL_ANNOUNCEMENTS'); ?></a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($this->showRequests) : ?>
        <?php // Newest open and on-hold requests ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT'); ?></h2>
        <?php if ($this->requestsError !== '') : ?>
            <div class="alert alert-danger mb-4" role="alert"><?php echo $this->escape($this->requestsError); ?></div>
        <?php elseif ($r !== null && $r->recent === []) : ?>
            <p class="text-muted mb-2"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT_NONE'); ?></p>
        <?php elseif ($r !== null) : ?>
            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_UPDATED'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($r->recent as $item) :
                        $url = $this->url('request&id=' . rawurlencode($item->id));
                        ?>
                        <tr>
                            <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->id); ?></a></td>
                            <td><a href="<?php echo $url; ?>"><?php echo $this->escape($item->subject); ?></a></td>
                            <td><?php echo $this->escape($item->status); ?></td>
                            <td><?php echo $date($item->updatedAt); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($this->requestsError === '') : ?>
            <p class="mb-4">
                <a href="<?php echo $this->url('requests&status=all'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_VIEW_ALL_REQUESTS'); ?></a>
                <?php if ($this->canCreate) : ?>
                    · <a href="<?php echo $this->url('requestform'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_A_REQUEST'); ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($this->showRequests || $this->showApprovals || $this->showLoans) : ?>
    <?php // Three blocks side by side (stacked on small screens) ?>
    <div class="row g-3 mb-4 sdp-blocks">
        <?php if ($this->showRequests) : ?>
            <?php echo $block('COM_SERVICEDESKPLUS_DASHBOARD_REQUESTS'); ?>
            <?php if ($this->requestsError !== '') : ?>
                <?php echo $error($this->requestsError); ?>
            <?php elseif ($r !== null) : ?>
                <table class="table table-sm table-striped align-middle mb-0">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_REQUESTS'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_SERVICE_REQUESTS'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_INCIDENTS'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($buckets as $bucket => $label) :
                        $s   = $r->service[$bucket] ?? 0;
                        $i   = $r->incident[$bucket] ?? 0;
                        $url = $this->statusUrl($bucket);
                        ?>
                        <tr>
                            <th scope="row"><a href="<?php echo $url; ?>"><?php echo Text::_($label); ?></a></th>
                            <td class="text-end"><?php echo $link($s, $this->statusUrl($bucket, RequestQuery::TYPE_SERVICE)); ?></td>
                            <td class="text-end"><?php echo $link($i, $this->statusUrl($bucket, RequestQuery::TYPE_INCIDENT)); ?></td>
                            <td class="text-end fw-semibold"><?php echo $link($s + $i, $url); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                            <td class="text-end"><?php echo $r->service['total']; ?></td>
                            <td class="text-end"><?php echo $r->incident['total']; ?></td>
                            <td class="text-end"><?php echo $r->service['total'] + $r->incident['total']; ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php if ($r->truncated) : ?>
                    <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TRUNCATED'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php echo $blockEnd; ?>
        <?php endif; ?>

        <?php if ($this->showApprovals) : ?>
            <?php echo $block('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'); ?>
            <?php if ($this->approvalsError !== '') : ?>
                <?php echo $error($this->approvalsError); ?>
            <?php endif; ?>
            <table class="table table-sm table-striped align-middle mb-0<?php echo $this->approvalsError !== '' ? ' mt-3' : ''; ?>">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'); ?></caption>
                <?php if ($this->approvalsError === '') : ?>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_TYPE'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING'); ?></th>
                        </tr>
                    </thead>
                <?php endif; ?>
                <tbody>
                <?php if ($this->approvalsError === '') : ?>
                    <?php foreach ($this->approvalModules as $module) : ?>
                        <tr>
                            <th scope="row"><?php echo Text::_(ApprovalModule::labelKey($module)); ?></th>
                            <td class="text-end"><?php echo $link((int) ($this->approvals[$module] ?? 0), $this->url('approvals&type=' . $module)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-semibold">
                        <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING_APPROVAL'); ?></th>
                        <td class="text-end"><?php echo $link((int) ($this->approvals['total'] ?? 0), $this->url('approvals')); ?></td>
                    </tr>
                <?php endif; ?>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_APPROVED'); ?></th>
                        <td class="text-end"><?php echo $link($this->decisions['approved'], $this->url('approvals&show=approved')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_REJECTED'); ?></th>
                        <td class="text-end"><?php echo $link($this->decisions['rejected'], $this->url('approvals&show=rejected')); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php echo $blockEnd; ?>
        <?php endif; ?>

        <?php if ($this->showLoans) : ?>
            <?php echo $block('COM_SERVICEDESKPLUS_DASHBOARD_LOANS'); ?>
            <?php if ($this->loansError !== '') : ?>
                <?php echo $error($this->loansError); ?>
            <?php else : ?>
                <table class="table table-sm table-striped align-middle mb-0">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS'); ?></caption>
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_ALL'); ?></th>
                            <td class="text-end"><?php echo $link($this->loansTotal, $this->url('assets')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS_TO_RETURN'); ?></th>
                            <td class="text-end"><?php echo $link($this->loansDueSoon, $this->url('assets&show=due_soon')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_OVERDUE'); ?></th>
                            <td class="text-end"><?php echo $link($this->loansOverdue, $this->url('assets&show=overdue')); ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php echo $blockEnd; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
