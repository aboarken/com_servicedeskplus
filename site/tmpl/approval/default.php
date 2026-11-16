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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalService;
use SdpPortal\Component\ServiceDeskPlus\Site\View\Ui;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Approval\HtmlView $this */

// Layouts from the component, unless the site template overrides them (html/layouts/com_servicedeskplus).
$layoutOptions = ['component' => 'com_servicedeskplus', 'client' => 0];

$date  = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '—';
$item  = $this->item;
$field = static fn (string $labelKey, string $html): string => '<div><dt>' . Text::_($labelKey) . '</dt><dd>' . $html . '</dd></div>';
?>
<div class="com-servicedeskplus sdp-approval">
    <a class="sdp-back" href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=approvals'); ?>">&larr; <?php echo Text::_('COM_SERVICEDESKPLUS_BACK_TO_APPROVALS'); ?></a>

    <?php if ($item === null) : ?>
        <div class="sdp-alert sdp-tone-red" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else : ?>
        <div class="sdp-head">
            <div>
                <h1 class="h2"><?php echo $this->escape($item->subject); ?></h1>
                <div class="sdp-head-meta">
                    <span><?php echo Text::sprintf('COM_SERVICEDESKPLUS_APPROVAL_ITEM', Text::_(ApprovalModule::labelKey($item->module)), $this->escape($item->requestId)); ?></span>
                    <?php echo Ui::badge($item->status); ?>
                </div>
            </div>
        </div>

        <section class="sdp-panel">
            <div class="sdp-panel-head"><h2 class="sdp-panel-title"><?php echo Text::_('COM_SERVICEDESKPLUS_DETAILS'); ?></h2></div>
            <dl class="sdp-fields">
                <?php
                echo $field('COM_SERVICEDESKPLUS_APPROVAL_TYPE', Ui::badge(Text::_(ApprovalModule::labelKey($item->module)), 'purple'));
                echo $field('COM_SERVICEDESKPLUS_REQUESTER', $this->escape($item->requester !== '' ? $item->requester : '—'));
                echo $field('COM_SERVICEDESKPLUS_APPROVAL_SENT_ON', $date($item->sentOn));
                echo $field('COM_SERVICEDESKPLUS_STATUS', Ui::badge($item->status));
                ?>
            </dl>
        </section>

        <section class="sdp-panel">
            <div class="sdp-panel-head"><h2 class="sdp-panel-title"><?php echo Text::_('COM_SERVICEDESKPLUS_DESCRIPTION'); ?></h2></div>
            <div class="sdp-panel-body sdp-text sdp-prose"><?php echo nl2br($this->escape($item->description !== '' ? $item->description : '—')); ?></div>
        </section>

        <?php if ($item->module === ApprovalModule::REQUEST) : ?>
            <?php echo (new FileLayout('conversation', null, $layoutOptions))->render(['entries' => $this->conversation]); ?>
        <?php endif; ?>

        <section class="sdp-panel">
            <div class="sdp-panel-head"><h2 class="sdp-panel-title"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_COMMENTS'); ?></h2></div>
            <div class="sdp-panel-body sdp-text sdp-prose"><?php echo $item->comments !== '' ? nl2br($this->escape($item->comments)) : '—'; ?></div>
        </section>

        <?php if (!$item->pending) : ?>
            <div class="sdp-alert sdp-tone-slate"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_NOT_PENDING'); ?></div>
        <?php elseif (!$this->canDecide) : ?>
            <?php // No decide permission: read-only. ?>
        <?php else : ?>
            <section class="sdp-panel">
                <div class="sdp-panel-head"><h2 class="sdp-panel-title"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_DECISION'); ?></h2></div>
                <form class="sdp-panel-body" action="<?php echo Route::_('index.php?option=com_servicedeskplus'); ?>" method="post" data-sdp-submit-once>
                    <div class="sdp-form-field">
                        <label class="sdp-label" for="sdp-comment"><?php echo Text::_('COM_SERVICEDESKPLUS_APPROVAL_COMMENT'); ?></label>
                        <textarea class="sdp-input" id="sdp-comment" name="comment" rows="3" maxlength="<?php echo ApprovalService::COMMENT_MAX; ?>"></textarea>
                    </div>

                    <input type="hidden" name="decision" value="" data-sdp-decision>
                    <div class="sdp-actions">
                        <button class="sdp-btn sdp-btn--green" type="submit" name="decision" value="approve"
                                data-sdp-confirm="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_APPROVAL_CONFIRM_APPROVE')); ?>">
                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-sdp-spinner></span>
                            <span data-sdp-label>&#10003; <?php echo Text::_('COM_SERVICEDESKPLUS_APPROVE'); ?></span>
                        </button>
                        <button class="sdp-btn sdp-btn--red" type="submit" name="decision" value="reject"
                                data-sdp-confirm="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_APPROVAL_CONFIRM_REJECT')); ?>">
                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-sdp-spinner></span>
                            <span data-sdp-label>&#10005; <?php echo Text::_('COM_SERVICEDESKPLUS_REJECT'); ?></span>
                        </button>
                    </div>

                    <input type="hidden" name="task" value="approval.decide">
                    <input type="hidden" name="request_id" value="<?php echo $this->escape($item->requestId); ?>">
                    <input type="hidden" name="level_id" value="<?php echo $this->escape($item->levelId); ?>">
                    <input type="hidden" name="approval_id" value="<?php echo $this->escape($item->approvalId); ?>">
                    <input type="hidden" name="module" value="<?php echo $this->escape($item->module); ?>">
                    <input type="hidden" name="submission" value="<?php echo $this->escape($this->submissionToken); ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
