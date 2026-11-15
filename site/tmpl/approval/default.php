<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalService;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Approval\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '—';
$item = $this->item;
?>
<div class="com-servicedeskplus sdp-approval">
    <p><a href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=approvals'); ?>">&larr; <?php echo Text::_('COM_SERVICEDESKPLUS_BACK_TO_APPROVALS'); ?></a></p>

    <?php if ($item === null) : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else : ?>
        <h1 class="h2"><?php echo $this->escape($item->subject); ?></h1>
        <p class="text-muted"><?php echo Text::sprintf('COM_SERVICEDESKPLUS_APPROVAL_ITEM', Text::_(ApprovalModule::labelKey($item->module)), $this->escape($item->requestId)); ?></p>

        <dl class="row">
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTER'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->requester !== '' ? $item->requester : '—'); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_SENT_ON'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->sentOn); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->status); ?></dd>
        </dl>

        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_DESCRIPTION'); ?></h2>
        <div class="sdp-text mb-4"><?php echo nl2br($this->escape($item->description !== '' ? $item->description : '—')); ?></div>

        <?php if ($item->module === ApprovalModule::REQUEST) : ?>
            <?php echo LayoutHelper::render('conversation', ['entries' => $this->conversation], JPATH_SITE . '/components/com_servicedeskplus/layouts'); ?>
        <?php endif; ?>

        <h2 class="h4"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_COMMENTS'); ?></h2>
        <div class="sdp-text mb-4"><?php echo $item->comments !== '' ? nl2br($this->escape($item->comments)) : '—'; ?></div>

        <?php if (!$item->pending) : ?>
            <div class="alert alert-secondary"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_NOT_PENDING'); ?></div>
        <?php elseif (!$this->canDecide) : ?>
            <?php // No decide permission: read-only. ?>
        <?php else : ?>
            <form action="<?php echo Route::_('index.php?option=com_servicedeskplus'); ?>" method="post" data-sdp-submit-once>
                <div class="mb-3">
                    <label class="form-label" for="sdp-comment"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_COMMENT'); ?></label>
                    <textarea class="form-control" id="sdp-comment" name="comment" rows="3" maxlength="<?php echo ApprovalService::COMMENT_MAX; ?>"></textarea>
                </div>

                <input type="hidden" name="decision" value="" data-sdp-decision>
                <button class="btn btn-success" type="submit" name="decision" value="approve"
                        data-sdp-confirm="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_APPROVAL_CONFIRM_APPROVE')); ?>">
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-sdp-spinner></span>
                    <span data-sdp-label><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVE'); ?></span>
                </button>
                <button class="btn btn-danger" type="submit" name="decision" value="reject"
                        data-sdp-confirm="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_APPROVAL_CONFIRM_REJECT')); ?>">
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-sdp-spinner></span>
                    <span data-sdp-label><?php echo Text::_('COM_SERVICEDESKPLUS_REJECT'); ?></span>
                </button>

                <input type="hidden" name="task" value="approval.decide">
                <input type="hidden" name="request_id" value="<?php echo $this->escape($item->requestId); ?>">
                <input type="hidden" name="level_id" value="<?php echo $this->escape($item->levelId); ?>">
                <input type="hidden" name="approval_id" value="<?php echo $this->escape($item->approvalId); ?>">
                <input type="hidden" name="module" value="<?php echo $this->escape($item->module); ?>">
                <input type="hidden" name="submission" value="<?php echo $this->escape($this->submissionToken); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
