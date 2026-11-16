<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Request\HtmlView $this */

// Layouts from the component, unless the site template overrides them (html/layouts/com_servicedeskplus).
$layoutOptions = ['component' => 'com_servicedeskplus', 'client' => 0];

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '—';
$item = $this->item;
// Lookup fields that SDP leaves empty are shown the same way: "Not assigned".
$text = static fn (string $v): string => $v !== '' ? $v : Text::_('COM_SERVICEDESKPLUS_UNASSIGNED');
?>
<div class="com-servicedeskplus sdp-request">
    <p><a href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=requests'); ?>">&larr; <?php echo Text::_('COM_SERVICEDESKPLUS_BACK_TO_REQUESTS'); ?></a></p>

    <?php if ($item === null) : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else : ?>
        <h1 class="h2"><?php echo $this->escape($item->subject); ?></h1>
        <p class="text-muted"><?php echo Text::sprintf('COM_SERVICEDESKPLUS_REQUEST_TITLE', $this->escape($item->id)); ?></p>

        <dl class="row">
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->status); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_PRIORITY'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->priority)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_URGENCY'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->urgency)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_TECHNICIAN_GROUP'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->technicianGroup)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_TECHNICIAN'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->technician)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_CATEGORY'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->category)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBCATEGORY'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->subcategory)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ITEM'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($text($item->item)); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_CREATED'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->createdAt); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_UPDATED'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->updatedAt); ?></dd>
            <?php if ($item->approvalStatus !== '') : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_STATUS'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($item->approvalStatus); ?></dd>
            <?php endif; ?>
            <?php if ($item->dueAt) : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_DUE'); ?></dt>
                <dd class="col-sm-9"><?php echo $date($item->dueAt); ?></dd>
            <?php endif; ?>
            <?php if ($item->resolvedAt) : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_RESOLVED'); ?></dt>
                <dd class="col-sm-9"><?php echo $date($item->resolvedAt); ?></dd>
            <?php endif; ?>
            <?php if ($item->completedAt) : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_COMPLETED'); ?></dt>
                <dd class="col-sm-9"><?php echo $date($item->completedAt); ?></dd>
            <?php endif; ?>
        </dl>

        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DESCRIPTION'); ?></h2>
        <div class="sdp-text mb-4"><?php echo nl2br($this->escape($item->description !== '' ? $item->description : '—')); ?></div>

        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_RESOLUTION'); ?></h2>
        <div class="sdp-text"><?php echo $item->resolution !== '' ? nl2br($this->escape($item->resolution)) : Text::_('COM_SERVICEDESKPLUS_NO_RESOLUTION'); ?></div>

        <?php if ($this->approvals !== []) : ?>
            <h2 class="h4 mt-4"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUEST_APPROVALS'); ?></h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle sdp-request-approvals">
                    <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUEST_APPROVALS'); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_LEVEL'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVER'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_SENT_ON'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_ACTED_ON'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_COMMENTS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->approvals as $approval) : ?>
                        <tr>
                            <td><?php echo $this->escape(Text::sprintf('COM_SERVICEDESKPLUS_APPROVAL_LEVEL_N', $approval->levelNumber)
                                . ($approval->levelName !== '' ? ' · ' . $approval->levelName : '')); ?></td>
                            <td><?php echo $this->escape($approval->approver !== '' ? $approval->approver : '—'); ?></td>
                            <td><?php echo $this->escape($approval->status !== '' ? $approval->status : '—'); ?></td>
                            <td><?php echo $date($approval->sentOn); ?></td>
                            <td><?php echo $date($approval->actedOn); ?></td>
                            <td class="sdp-text"><?php echo $approval->comments !== '' ? nl2br($this->escape($approval->comments)) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php echo (new FileLayout('conversation', null, $layoutOptions))->render(['entries' => $this->conversation]); ?>
    <?php endif; ?>
</div>
