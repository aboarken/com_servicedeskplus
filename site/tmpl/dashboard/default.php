<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */

/*
 * Layout: the user's details (department, job title, employee ID, name), summary cards, charts
 * (requests by status, open requests by age and by priority), then My Requests, My Approvals and
 * My Loans side by side. Styles are in media/com_servicedeskplus/css/portal.css (.sdp-*), so the
 * Dashboard looks the same on any site template. Colours ("tones"): blue (#17426f) open, amber on
 * hold or due soon, green resolved / approved / nothing overdue, slate closed, purple waiting
 * approval, red rejected / overdue, teal announcements, muted for zero.
 */

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
// My Requests tiles: bucket => label, tone.
$tiles = [
    'open'     => ['COM_SERVICEDESKPLUS_DASHBOARD_OPEN', 'blue'],
    'on_hold'  => ['COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD', 'amber'],
    'resolved' => ['COM_SERVICEDESKPLUS_DASHBOARD_RESOLVED', 'green'],
    'closed'   => ['COM_SERVICEDESKPLUS_DASHBOARD_CLOSED', 'slate'],
];
?>
<div class="com-servicedeskplus sdp-dashboard" data-sdp-root="dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2 mb-3"><?php echo $e($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php // The user's own ServiceDesk Plus record: department, job title, employee ID, name ?>
    <?php if ($this->profile !== null) :
        $p      = $this->profile;
        $fields = [
            'COM_SERVICEDESKPLUS_PROFILE_DEPARTMENT'  => $p->department,
            'COM_SERVICEDESKPLUS_PROFILE_JOB_TITLE'   => $p->jobTitle,
            'COM_SERVICEDESKPLUS_PROFILE_EMPLOYEE_ID' => $p->employeeId,
            'COM_SERVICEDESKPLUS_PROFILE_NAME'        => $p->name !== '' ? $p->name : $this->userName,
        ];
        ?>
        <section class="sdp-profile" aria-label="<?php echo Text::_('COM_SERVICEDESKPLUS_PROFILE_TITLE'); ?>">
            <dl class="sdp-profile-list">
                <?php foreach ($fields as $labelKey => $value) : ?>
                    <div class="sdp-profile-item">
                        <dt><?php echo Text::_($labelKey); ?></dt>
                        <dd><?php echo $value !== '' ? $e($value) : '—'; ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
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
    </div>

    <?php // Charts from the requests read for the figures above (no extra ServiceDesk Plus calls) ?>
    <?php if ($this->showRequests && $r !== null && $this->requestsError === '') :
        // Requests by status: per bucket incidents + service requests; resolved and closed may
        // come without the type split (then one neutral segment).
        $columns = [];
        $unsplit = false;

        foreach ($tiles as $bucket => [$labelKey]) {
            $counted = $r->counted[$bucket] ?? null;
            $i       = $counted !== null ? $counted['incident'] : (int) ($r->incident[$bucket] ?? 0);
            $s       = $counted !== null ? $counted['service'] : (int) ($r->service[$bucket] ?? 0);
            $total   = $counted !== null ? $counted['total'] : $i + $s;
            $split   = $i !== null && $s !== null;
            $unsplit = $unsplit || ($total !== null && !$split);
            $columns[$bucket] = ['label' => Text::_($labelKey), 'i' => $i, 's' => $s, 'total' => $total, 'split' => $split];
        }

        $maxStatus = max(1, ...array_map(static fn (array $c): int => (int) $c['total'], array_values($columns)));
        $active    = array_sum($r->age);
        $ageLabels = [
            'week'    => 'COM_SERVICEDESKPLUS_CHART_AGE_WEEK',
            'month'   => 'COM_SERVICEDESKPLUS_CHART_AGE_MONTH',
            'quarter' => 'COM_SERVICEDESKPLUS_CHART_AGE_QUARTER',
            'older'   => 'COM_SERVICEDESKPLUS_CHART_AGE_OLDER',
        ];
        $maxAge      = max(1, ...array_values($r->age ?: [0]));
        $priorities  = array_map(static fn (array $pr): array => $pr + ['label' => $pr['other'] ? Text::_('COM_SERVICEDESKPLUS_CHART_PRIORITY_OTHER') : ($pr['name'] !== '' ? $pr['name'] : Text::_('COM_SERVICEDESKPLUS_CHART_PRIORITY_NONE'))], $r->priority);
        $maxPriority = max(1, ...array_map(static fn (array $pr): int => $pr['count'], $priorities ?: [['count' => 0]]));
        $pct         = static fn (int $value, int $max): string => rtrim(rtrim(number_format(100 * $value / $max, 2, '.', ''), '0'), '.');
        ?>
        <div class="sdp-panels sdp-charts">
            <section class="sdp-panel sdp-chart" aria-labelledby="sdp-chart-status">
                <?php echo str_replace('<h2 class="sdp-panel-title">', '<h2 class="sdp-panel-title" id="sdp-chart-status">', $panelHead(Text::_('COM_SERVICEDESKPLUS_CHART_STATUS_TITLE'), $this->url('requests&status=all'), $viewAll)); ?>
                <div class="sdp-panel-body">
                    <ul class="sdp-legend">
                        <li><span class="sdp-swatch sdp-series-1" aria-hidden="true"></span><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_INCIDENTS'); ?></li>
                        <li><span class="sdp-swatch sdp-series-2" aria-hidden="true"></span><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_SERVICE'); ?></li>
                        <?php if ($unsplit) : ?>
                            <li><span class="sdp-swatch sdp-series-none" aria-hidden="true"></span><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_NOT_SPLIT'); ?></li>
                        <?php endif; ?>
                    </ul>
                    <div class="sdp-columns" aria-hidden="true">
                        <?php foreach ($columns as $bucket => $c) :
                            $total = (int) $c['total'];
                            $tip   = $c['split']
                                ? Text::sprintf('COM_SERVICEDESKPLUS_CHART_TIP_STATUS', $c['label'], (int) $c['i'], (int) $c['s'])
                                : Text::sprintf('COM_SERVICEDESKPLUS_CHART_TIP_TOTAL', $c['label'], $total);
                            ?>
                            <a class="sdp-column" href="<?php echo $this->statusUrl($bucket); ?>" data-sdp-tip="<?php echo $e($tip); ?>" tabindex="-1">
                                <span class="sdp-column-value"><?php echo $c['total'] === null ? '—' : $total; ?></span>
                                <span class="sdp-column-track">
                                    <span class="sdp-column-stack" style="height: <?php echo $pct($total, $maxStatus); ?>%">
                                        <?php if ($c['split']) : ?>
                                            <?php if ((int) $c['s'] > 0) : ?><span class="sdp-seg sdp-series-2" style="flex-grow: <?php echo (int) $c['s']; ?>"></span><?php endif; ?>
                                            <?php if ((int) $c['i'] > 0) : ?><span class="sdp-seg sdp-series-1" style="flex-grow: <?php echo (int) $c['i']; ?>"></span><?php endif; ?>
                                        <?php elseif ($total > 0) : ?>
                                            <span class="sdp-seg sdp-series-none" style="flex-grow: 1"></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <span class="sdp-column-label"><?php echo $e($c['label']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <details class="sdp-chart-figures">
                        <summary><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_SHOW_FIGURES'); ?></summary>
                        <table class="sdp-table">
                            <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_STATUS_TITLE'); ?></caption>
                            <thead><tr>
                                <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                                <th scope="col" class="sdp-num"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_INCIDENTS'); ?></th>
                                <th scope="col" class="sdp-num"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_SERVICE'); ?></th>
                                <th scope="col" class="sdp-num"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_TOTAL'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($columns as $bucket => $c) : ?>
                                <tr>
                                    <th scope="row"><a href="<?php echo $this->statusUrl($bucket); ?>"><?php echo $e($c['label']); ?></a></th>
                                    <td class="sdp-num"><?php echo $c['i'] ?? '—'; ?></td>
                                    <td class="sdp-num"><?php echo $c['s'] ?? '—'; ?></td>
                                    <td class="sdp-num"><?php echo $c['total'] ?? '—'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                </div>
            </section>

            <section class="sdp-panel sdp-chart" aria-labelledby="sdp-chart-age">
                <?php echo str_replace('<h2 class="sdp-panel-title">', '<h2 class="sdp-panel-title" id="sdp-chart-age">', $panelHead(Text::_('COM_SERVICEDESKPLUS_CHART_AGE_TITLE'))); ?>
                <div class="sdp-panel-body">
                    <p class="sdp-chart-note"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_ACTIVE_NOTE'); ?></p>
                    <?php if ($active === 0) : ?>
                        <p class="sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_EMPTY'); ?></p>
                    <?php else : ?>
                        <ul class="sdp-hbars" aria-hidden="true">
                            <?php $step = 0; foreach ($ageLabels as $key => $labelKey) : $n = (int) ($r->age[$key] ?? 0); $step++; ?>
                                <li class="sdp-hbar" data-sdp-tip="<?php echo $e(Text::sprintf('COM_SERVICEDESKPLUS_CHART_TIP_TOTAL', Text::_($labelKey), $n)); ?>">
                                    <span class="sdp-hbar-label"><?php echo Text::_($labelKey); ?></span>
                                    <span class="sdp-hbar-track"><?php if ($n > 0) : ?><span class="sdp-hbar-fill sdp-seq-<?php echo $step; ?>" style="width: <?php echo $pct($n, $maxAge); ?>%"></span><?php endif; ?></span>
                                    <span class="sdp-hbar-value"><?php echo $n; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <details class="sdp-chart-figures">
                            <summary><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_SHOW_FIGURES'); ?></summary>
                            <table class="sdp-table">
                                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_AGE_TITLE'); ?></caption>
                                <tbody>
                                <?php foreach ($ageLabels as $key => $labelKey) : ?>
                                    <tr><th scope="row"><?php echo Text::_($labelKey); ?></th><td class="sdp-num"><?php echo (int) ($r->age[$key] ?? 0); ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </div>
            </section>

            <section class="sdp-panel sdp-chart" aria-labelledby="sdp-chart-priority">
                <?php echo str_replace('<h2 class="sdp-panel-title">', '<h2 class="sdp-panel-title" id="sdp-chart-priority">', $panelHead(Text::_('COM_SERVICEDESKPLUS_CHART_PRIORITY_TITLE'))); ?>
                <div class="sdp-panel-body">
                    <p class="sdp-chart-note"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_ACTIVE_NOTE'); ?></p>
                    <?php if ($priorities === []) : ?>
                        <p class="sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_EMPTY'); ?></p>
                    <?php else : ?>
                        <ul class="sdp-hbars" aria-hidden="true">
                            <?php foreach ($priorities as $pr) : ?>
                                <li class="sdp-hbar" data-sdp-tip="<?php echo $e(Text::sprintf('COM_SERVICEDESKPLUS_CHART_TIP_TOTAL', $pr['label'], $pr['count'])); ?>">
                                    <span class="sdp-hbar-label"><?php echo $e($pr['label']); ?></span>
                                    <span class="sdp-hbar-track"><span class="sdp-hbar-fill sdp-series-1" style="width: <?php echo $pct($pr['count'], $maxPriority); ?>%"></span></span>
                                    <span class="sdp-hbar-value"><?php echo (int) $pr['count']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <details class="sdp-chart-figures">
                            <summary><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_SHOW_FIGURES'); ?></summary>
                            <table class="sdp-table">
                                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_CHART_PRIORITY_TITLE'); ?></caption>
                                <tbody>
                                <?php foreach ($priorities as $pr) : ?>
                                    <tr><th scope="row"><?php echo $e($pr['label']); ?></th><td class="sdp-num"><?php echo (int) $pr['count']; ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    <?php endif; ?>
                </div>
            </section>
        </div>
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
