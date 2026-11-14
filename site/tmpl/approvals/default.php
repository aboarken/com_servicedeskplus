<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Approvals\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC5')) : '—';
$decisionLabel = [
    'approval.approve' => 'COM_SERVICEDESKPLUS_APPROVAL_HISTORY_APPROVE',
    'approval.reject'  => 'COM_SERVICEDESKPLUS_APPROVAL_HISTORY_REJECT',
];
$resultLabel = [
    'ok'     => 'COM_SERVICEDESKPLUS_APPROVAL_RESULT_OK',
    'denied' => 'COM_SERVICEDESKPLUS_APPROVAL_RESULT_DENIED',
    'error'  => 'COM_SERVICEDESKPLUS_APPROVAL_RESULT_ERROR',
];
?>
<div class="com-servicedeskplus sdp-approvals">
    <h1 class="h2"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_TITLE'); ?></h1>

    <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_PENDING'); ?></h2>

    <?php if ($this->error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php elseif ($this->pending === []) : ?>
        <div class="alert alert-secondary"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive mb-4">
            <table class="table table-striped align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_PENDING'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_SENT_ON'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_LEVEL'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->pending as $p) :
                    $url = Route::_('index.php?option=com_servicedeskplus&view=approval&id=' . rawurlencode($p->approvalId)
                        . '&request_id=' . rawurlencode($p->requestId) . '&level_id=' . rawurlencode($p->levelId));
                    ?>
                    <tr>
                        <td><a href="<?php echo $url; ?>"><?php echo $this->escape($p->requestId); ?></a></td>
                        <td><a href="<?php echo $url; ?>"><?php echo $this->escape($p->subject); ?></a></td>
                        <td><?php echo $this->escape($p->requester); ?></td>
                        <td><?php echo $date($p->sentOn); ?></td>
                        <td><?php echo $this->escape($p->level); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY'); ?></h2>
    <p class="text-muted small"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY_NOTE'); ?></p>

    <?php if ($this->history === []) : ?>
        <div class="alert alert-secondary"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVALS_HISTORY'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_DATE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ID'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_DECISION'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_RESULT'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->history as $h) : ?>
                    <tr>
                        <td><?php echo HTMLHelper::_('date', $h['created'], Text::_('DATE_FORMAT_LC5')); ?></td>
                        <td><?php echo $this->escape((string) $h['sdp_request_id']); ?></td>
                        <td><?php echo Text::_($decisionLabel[$h['action']] ?? 'JNONE'); ?></td>
                        <td><?php echo Text::_($resultLabel[$h['result']] ?? 'JNONE'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
