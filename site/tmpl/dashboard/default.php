<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */

$date    = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$buckets = [
    'open'     => 'COM_SERVICEDESKPLUS_DASHBOARD_OPEN',
    'on_hold'  => 'COM_SERVICEDESKPLUS_DASHBOARD_ON_HOLD',
    'resolved' => 'COM_SERVICEDESKPLUS_DASHBOARD_RESOLVED',
    'closed'   => 'COM_SERVICEDESKPLUS_DASHBOARD_CLOSED',
    'other'    => 'COM_SERVICEDESKPLUS_DASHBOARD_OTHER',
];
$card = static function (string $label, int $value, string $url = ''): string {
    $number = $url !== '' ? '<a class="stretched-link text-reset text-decoration-none" href="' . $url . '">' . $value . '</a>' : (string) $value;

    return '<div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">'
        . '<div class="small text-muted">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="fs-2 fw-semibold">' . $number . '</div>'
        . '</div></div></div>';
};
$requestsUrl = Route::_('index.php?option=com_servicedeskplus&view=requests&status=all');
?>
<div class="com-servicedeskplus sdp-dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php // Summary cards ?>
    <div class="row g-3 mb-4">
        <?php if ($this->requests !== null) :
            $open = $this->requests->service['open'] + $this->requests->incident['open'];
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OPEN_REQUESTS'), $open, Route::_('index.php?option=com_servicedeskplus&view=requests'));
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OVERDUE_REQUESTS'), $this->requests->overdue);
        endif; ?>
        <?php if ($this->showApprovals && $this->approvalsError === '' && $this->approvals !== []) :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_WAITING_APPROVALS'), $this->approvals['total'], Route::_('index.php?option=com_servicedeskplus&view=approvals'));
        endif; ?>
        <?php if ($this->showLoans && $this->loansError === '') :
            echo $card(Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OVERDUE_LOANS'), $this->loansOverdue, Route::_('index.php?option=com_servicedeskplus&view=assets'));
        endif; ?>
    </div>

    <?php // Requests by type and status ?>
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
                    if ($bucket === 'other' && $s + $i === 0) {
                        continue;
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php echo Text::_($label); ?></th>
                        <td class="text-end"><?php echo $s; ?></td>
                        <td class="text-end"><?php echo $i; ?></td>
                        <td class="text-end"><?php echo $s + $i; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                        <td class="text-end"><?php echo $this->requests->service['total']; ?></td>
                        <td class="text-end"><?php echo $this->requests->incident['total']; ?></td>
                        <td class="text-end"><?php echo $this->requests->service['total'] + $this->requests->incident['total']; ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php if ($this->requests->truncated) : ?>
            <p class="small text-muted"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TRUNCATED'); ?></p>
        <?php endif; ?>
        <p class="mb-4">
            <a href="<?php echo $requestsUrl; ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_ALL_REQUESTS'); ?></a>
            <?php if ($this->canCreate) : ?>
                · <a href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=requestform'); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_A_REQUEST'); ?></a>
            <?php endif; ?>
        </p>

        <?php if ($this->requests->recent !== []) : ?>
            <h2 class="h5"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_RECENT'); ?></h2>
            <div class="table-responsive mb-4">
                <table class="table align-middle">
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
                        $url = Route::_('index.php?option=com_servicedeskplus&view=request&id=' . rawurlencode($r->id));
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

    <?php if ($this->showApprovals) : ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_APPROVALS'); ?></h2>
        <?php if ($this->approvalsError !== '') : ?>
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->approvalsError); ?></div>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table table-striped align-middle">
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
                            <td class="text-end"><?php echo (int) ($this->approvals[$module] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_TOTAL'); ?></th>
                            <td class="text-end"><?php echo (int) ($this->approvals['total'] ?? 0); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($this->showLoans) : ?>
        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS'); ?></h2>
        <?php if ($this->loansError !== '') : ?>
            <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->loansError); ?></div>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table table-striped align-middle">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_LOANS_TOTAL'); ?></th>
                            <td class="text-end"><?php echo $this->loansTotal; ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo Text::_('COM_SERVICEDESKPLUS_DASHBOARD_OVERDUE_LOANS'); ?></th>
                            <td class="text-end"><?php echo $this->loansOverdue; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
