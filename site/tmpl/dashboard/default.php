<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;
use SdpPortal\Component\ServiceDeskPlus\Site\View\Ui;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */

/*
 * Layout: summary cards, then panels (Announcements, Recent Requests, then My Requests, My Approvals
 * and My Loans side by side). Styles are in media/com_servicedeskplus/css/portal.css (.sdp-*), so the
 * Dashboard looks the same on any site template. Colours ("tones"): blue (#17426f) open, amber on
 * hold or due soon, green resolved / approved / nothing overdue, slate closed, purple waiting
 * approval, red rejected / overdue, teal announcements, muted for zero.
 */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$e    = fn (string $text): string => $this->escape($text);
$r    = $this->requests;

// Summary card: the whole card opens its page.
$stat = static fn (string $label, int $value, string $url, string $tone): string => '<a class="sdp-stat sdp-tone-' . $tone . '" href="' . $url . '">'
    . '<span class="sdp-stat-label">' . $label . '</span>'
    . '<span class="sdp-stat-value">' . $value . '</span></a>';

// Row of a panel list: label and a coloured count; the whole row opens its page.
$row = static function (string $label, ?int $value, string $url, string $tone, bool $strong = false): string {
    $cls = 'sdp-list-row' . ($strong ? ' sdp-list-row--strong' : '');

    if ($value === null) {
        return '<li><span class="' . $cls . '"><span>' . $label . '</span><span class="sdp-pill sdp-tone-muted">—</span></span></li>';
    }

    return '<li><a class="' . $cls . '" href="' . $url . '"><span>' . $label . '</span>'
        . '<span class="sdp-pill sdp-tone-' . ($value === 0 && $tone !== 'green' ? 'muted' : $tone) . '">' . $value . '</span></a></li>';
};

$panelHead = static fn (string $title, string $url = '', string $linkText = ''): string => '<div class="sdp-panel-head">'
    . '<h2 class="sdp-panel-title">' . $title . '</h2>'
    . ($url !== '' ? '<a class="sdp-panel-link" href="' . $url . '">' . $linkText . ' &rarr;</a>' : '')
    . '</div>';

$viewAll = Text::_('COM_SERVICEDESKPLUS_DASHBOARD_VIEW_ALL');
$error   = fn (string $message): string => '<div class="sdp-panel-body"><div class="sdp-alert sdp-tone-red" role="alert">' . $e($message) . '</div></div>';
$badge   = static fn (string $status): string => Ui::tone($status);
// My Requests tiles: bucket => label, tone.
$tiles = [
    'open'     => ['COM_SERVICEDESKPLUS_DASHBOARD_OPEN', 'blue'],
    'on_hold'  => ['COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD', 'amber'],
    'resolved' => ['COM_SERVICEDESKPLUS_DASHBOARD_RESOLVED', 'green'],
    'closed'   => ['COM_SERVICEDESKPLUS_DASHBOARD_CLOSED', 'slate'],
];
?>
<div class="com-servicedeskplus sdp-dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2 mb-3"><?php echo $e($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php // Summary cards ?>
    <div class="sdp-stats">
        <?php if ($r !== null) :
            echo $stat(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OPEN_REQUESTS'), $r->service['open'] + $r->incident['open'], $this->statusUrl('open'), 'blue');
            echo $stat(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD_REQUESTS'), $r->service['on_hold'] + $r->incident['on_hold'], $this->statusUrl('on_hold'), 'amber');
        endif; ?>
        <?php if ($this->showApprovals && $this->approvalsError === '') :
            $waiting = (int) ($this->approvals['total'] ?? 0);
            echo $stat(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING_APPROVAL'), $waiting, $this->url('approvals'), $waiting > 0 ? 'purple' : 'green');
        endif; ?>
        <?php if ($this->showLoans && $this->loansError === '') :
            echo $stat(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS_TO_RETURN'), $this->loansDueSoon, $this->url('assets&show=due_soon'), $this->loansOverdue > 0 ? 'red' : ($this->loansDueSoon > 0 ? 'amber' : 'green'));
        endif; ?>
        <?php if ($this->showAnnouncements && $this->announcementsError === '') :
            echo $stat(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ALL_ANNOUNCEMENTS'), $this->announcementsTotal, $this->url('announcements'), 'teal');
        endif; ?>
    </div>

    <?php if ($this->showAnnouncements) : ?>
        <section class="sdp-panel">
            <?php echo $panelHead(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ANNOUNCEMENTS'), $this->announcementsError === '' && $this->announcements !== [] ? $this->url('announcements') : '', $viewAll); ?>
            <?php if ($this->announcementsError !== '') : ?>
                <?php echo $error($this->announcementsError); ?>
            <?php elseif ($this->announcements === []) : ?>
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_NONE'); ?></p>
            <?php else : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
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
                                <td class="sdp-id"><a href="<?php echo $url; ?>"><?php echo $e($a->id); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $e($a->title !== '' ? $a->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTITLED')); ?></a></td>
                                <td><?php echo $a->type !== '' ? '<span class="sdp-badge sdp-tone-teal">' . $e($a->type) . '</span>' : '—'; ?></td>
                                <td class="sdp-nowrap"><?php echo $date($a->date()); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($this->showRequests) : ?>
        <?php // Newest open and on-hold requests ?>
        <section class="sdp-panel">
            <?php echo $panelHead(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT'), $this->url('requests&status=all'), $viewAll); ?>
            <?php if ($this->requestsError !== '') : ?>
                <?php echo $error($this->requestsError); ?>
            <?php elseif ($r === null || $r->recent === []) : ?>
                <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT_NONE'); ?></p>
            <?php else : ?>
                <div class="sdp-table-wrap">
                    <table class="sdp-table">
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
                                <td class="sdp-id"><a href="<?php echo $url; ?>"><?php echo $e($item->id); ?></a></td>
                                <td><a href="<?php echo $url; ?>"><?php echo $e($item->subject); ?></a></td>
                                <td><span class="sdp-badge sdp-tone-<?php echo $badge($item->status); ?>"><?php echo $e($item->status); ?></span></td>
                                <td class="sdp-nowrap"><?php echo $date($item->updatedAt); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($this->showRequests || $this->showApprovals || $this->showLoans) : ?>
    <?php // My Requests, My Approvals and My Loans side by side (stacked on small screens) ?>
    <div class="sdp-panels">
        <?php if ($this->showRequests) : ?>
            <section class="sdp-panel">
                <?php echo $panelHead(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_REQUESTS'), $this->url('requests&status=all'), $viewAll); ?>
                <?php if ($this->requestsError !== '') : ?>
                    <?php echo $error($this->requestsError); ?>
                <?php elseif ($r !== null) : ?>
                    <div class="sdp-panel-body">
                        <div class="sdp-tiles">
                            <?php foreach ($tiles as $bucket => [$labelKey, $tone]) :
                                // Open and on hold: counted from the rows read; resolved and closed: counted by SDP.
                                $counted = $r->counted[$bucket] ?? null;
                                $i       = $counted !== null ? $counted['incident'] : (int) ($r->incident[$bucket] ?? 0);
                                $s       = $counted !== null ? $counted['service'] : (int) ($r->service[$bucket] ?? 0);
                                $total   = $counted !== null ? $counted['total'] : $i + $s;
                                ?>
                                <div class="sdp-tile sdp-tone-<?php echo $total ? $tone : 'muted'; ?>">
                                    <a class="sdp-tile-main" href="<?php echo $this->statusUrl($bucket); ?>">
                                        <span class="sdp-tile-value"><?php echo $total ?? '—'; ?></span>
                                        <span class="sdp-tile-label"><?php echo Text::_($labelKey); ?></span>
                                    </a>
                                    <?php if ($i !== null && $s !== null) : ?>
                                        <div class="sdp-tile-meta">
                                            <a href="<?php echo $this->statusUrl($bucket, RequestQuery::TYPE_INCIDENT); ?>"><?php echo Text::sprintf('COM_SERVICEDESKPLUS_DASHBOARD_N_INCIDENT', $i); ?></a>
                                            <a href="<?php echo $this->statusUrl($bucket, RequestQuery::TYPE_SERVICE); ?>"><?php echo Text::sprintf('COM_SERVICEDESKPLUS_DASHBOARD_N_SERVICE', $s); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($r->truncated) : ?>
                            <p class="sdp-note"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TRUNCATED'); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($this->canCreate) : ?>
                        <div class="sdp-panel-foot">
                            <a class="sdp-action" href="<?php echo $this->url('requestform'); ?>">+ <?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_A_REQUEST'); ?></a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($this->showApprovals) : ?>
            <section class="sdp-panel">
                <?php echo $panelHead(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'), $this->url('approvals'), $viewAll); ?>
                <?php if ($this->approvalsError !== '') : ?>
                    <?php echo $error($this->approvalsError); ?>
                <?php endif; ?>
                <ul class="sdp-list">
                    <?php if ($this->approvalsError === '') : ?>
                        <?php foreach ($this->approvalModules as $module) :
                            echo $row(Text::_(ApprovalModule::labelKey($module)), (int) ($this->approvals[$module] ?? 0), $this->url('approvals&type=' . $module), 'purple');
                        endforeach; ?>
                        <?php echo $row(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING_APPROVAL'), (int) ($this->approvals['total'] ?? 0), $this->url('approvals'), 'purple', true); ?>
                    <?php endif; ?>
                    <li class="sdp-list-sep"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_MY_DECISIONS'); ?></li>
                    <?php echo $row(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_APPROVED'), $this->decisions['approved'], $this->url('approvals&show=approved'), 'green'); ?>
                    <?php echo $row(Text::_('COM_SERVICEDESKPLUS_APPROVALS_SHOW_REJECTED'), $this->decisions['rejected'], $this->url('approvals&show=rejected'), 'red'); ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($this->showLoans) : ?>
            <section class="sdp-panel">
                <?php echo $panelHead(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS'), $this->url('assets'), $viewAll); ?>
                <?php if ($this->loansError !== '') : ?>
                    <?php echo $error($this->loansError); ?>
                <?php else : ?>
                    <ul class="sdp-list">
                        <?php echo $row(Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_ALL'), $this->loansTotal, $this->url('assets'), 'blue'); ?>
                        <?php echo $row(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS_TO_RETURN'), $this->loansDueSoon, $this->url('assets&show=due_soon'), 'amber'); ?>
                        <?php echo $row(Text::_('COM_SERVICEDESKPLUS_LOANS_SHOW_OVERDUE'), $this->loansOverdue, $this->url('assets&show=overdue'), $this->loansOverdue > 0 ? 'red' : 'green'); ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
