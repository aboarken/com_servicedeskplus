<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */

$date    = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$buckets = [
    'open'     => 'COM_SERVICEDESKPLUS_DASHBOARD_OPEN',
    'on_hold'  => 'COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD',
    'resolved' => 'COM_SERVICEDESKPLUS_DASHBOARD_RESOLVED',
    'closed'   => 'COM_SERVICEDESKPLUS_DASHBOARD_CLOSED',
    'canceled' => 'COM_SERVICEDESKPLUS_DASHBOARD_CANCELED',
    'other'    => 'COM_SERVICEDESKPLUS_DASHBOARD_OTHER',
];
// A number that opens its page; "—" when it is not known.
$link = fn (?int $value, string $url): string => $value === null ? '—' : '<a href="' . $url . '">' . $value . '</a>';
$card = function (string $label, int $value, string $url): string {
    return '<div class="col-6 col-md-4 col-xl"><div class="card h-100 sdp-stat"><div class="card-body">'
        . '<div class="small text-muted">' . $this->escape($label) . '</div>'
        . '<div class="fs-2 fw-semibold"><a class="stretched-link text-reset text-decoration-none" href="' . $url . '">' . $value . '</a></div>'
        . '</div></div></div>';
};
?>
<div class="com-servicedeskplus sdp-dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2 mb-3"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php // Summary cards ?>
    <div class="row g-3 mb-4">
        <?php if ($this->requests !== null) :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OPEN_REQUESTS'), $this->requests->service['open'] + $this->requests->incident['open'], $this->statusUrl('open'));
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OVERDUE_REQUESTS'), $this->requests->overdue, $this->url('requests&status=all'));
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
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->announcementsError); ?></div>
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
        <?php // Requests by status and type ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_REQUESTS'); ?></h2>
        <?php if ($this->requestsError !== '') : ?>
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->requestsError); ?></div>
        <?php elseif ($this->requests !== null) : ?>
            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle">
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
                        $s = $this->requests->service[$bucket] ?? 0;
                        $i = $this->requests->incident[$bucket] ?? 0;
                        if (\in_array($bucket, ['canceled', 'other'], true) && $s + $i === 0) {
                            continue;
                        }
                        $url = $this->statusUrl($bucket);
                        ?>
                        <tr>
                            <th scope="row"><a href="<?php echo $url; ?>"><?php echo Text::_($label); ?></a></th>
                            <td class="text-end"><?php echo $s; ?></td>
                            <td class="text-end"><?php echo $i; ?></td>
                            <td class="text-end fw-semibold"><?php echo $link($s + $i, $url); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                            <td class="text-end"><?php echo $this->requests->service['total']; ?></td>
                            <td class="text-end"><?php echo $this->requests->incident['total']; ?></td>
                            <td class="text-end"><?php echo $link($this->requests->service['total'] + $this->requests->incident['total'], $this->url('requests&status=all')); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php if ($this->requests->truncated) : ?>
                <p class="small text-muted"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TRUNCATED'); ?></p>
            <?php endif; ?>
            <p class="mb-4">
                <a href="<?php echo $this->url('requests&status=all'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_VIEW_ALL_REQUESTS'); ?></a>
                <?php if ($this->canCreate) : ?>
                    · <a href="<?php echo $this->url('requestform'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_A_REQUEST'); ?></a>
                <?php endif; ?>
            </p>

            <?php if ($this->requests->recent !== []) : ?>
                <h2 class="h5"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT'); ?></h2>
                <div class="table-responsive mb-4">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_UPDATED'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->requests->recent as $r) :
                            $url = $this->url('request&id=' . rawurlencode($r->id));
                            ?>
                            <tr>
                                <td><a href="<?php echo $url; ?>"><?php echo $this->escape($r->id); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $this->escape($r->subject); ?></a></td>
                                <td><?php echo $this->escape($r->status); ?></td>
                                <td><?php echo $date($r->updatedAt); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($this->showApprovals) : ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'); ?></h2>
        <?php if ($this->approvalsError !== '') : ?>
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->approvalsError); ?></div>
        <?php else : ?>
            <div class="table-responsive mb-2">
                <table class="table table-striped align-middle">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_TYPE'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->approvalModules as $module) : ?>
                        <tr>
                            <th scope="row"><?php echo Text::_(ApprovalModule::labelKey($module)); ?></th>
                            <td class="text-end"><?php echo $link((int) ($this->approvals[$module] ?? 0), $this->url('approvals&type=' . $module)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                            <td class="text-end"><?php echo $link((int) ($this->approvals['total'] ?? 0), $this->url('approvals')); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
        <div class="table-responsive mb-4">
            <table class="table table-striped align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY'); ?></caption>
                <tbody>
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
        </div>
    <?php endif; ?>

    <?php if ($this->showLoans) : ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS'); ?></h2>
        <?php if ($this->loansError !== '') : ?>
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->loansError); ?></div>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table table-striped align-middle">
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
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
