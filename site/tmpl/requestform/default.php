<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\AttachmentValidator;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestService;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Requestform\HtmlView $this */
?>
<div class="com-servicedeskplus sdp-requestform">
    <h1 class="h2"><?php echo Text::_('COM_SERVICEDESKPLUS_REQUESTFORM_TITLE'); ?></h1>

    <?php if ($this->createdId !== null) :
        $url = Route::_('index.php?option=com_servicedeskplus&view=request&id=' . rawurlencode($this->createdId));
        ?>
        <div class="alert alert-success" role="status">
            <?php echo Text::sprintf('COM_SERVICEDESKPLUS_REQUEST_CREATED', $this->escape($this->createdId)); ?>
            <a class="alert-link" href="<?php echo $url; ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_VIEW_REQUEST'); ?></a>
        </div>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_servicedeskplus'); ?>" method="post" enctype="multipart/form-data" data-sdp-submit-once>
        <div class="mb-3">
            <label class="form-label" for="sdp-subject"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBJECT'); ?> <span aria-hidden="true">*</span></label>
            <input class="form-control" type="text" id="sdp-subject" name="subject" required
                   maxlength="<?php echo RequestService::SUBJECT_MAX; ?>" autocomplete="off"
                   value="<?php echo $this->escape($this->input['subject']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label" for="sdp-description"><?php echo Text::_('COM_SERVICEDESKPLUS_DESCRIPTION'); ?> <span aria-hidden="true">*</span></label>
            <textarea class="form-control" id="sdp-description" name="description" rows="8" required
                      maxlength="<?php echo RequestService::DESCRIPTION_MAX; ?>"><?php echo $this->escape($this->input['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="sdp-attachments"><?php echo Text::_('COM_SERVICEDESKPLUS_ATTACHMENTS'); ?></label>
            <input class="form-control" type="file" id="sdp-attachments" name="attachments[]" multiple
                   accept=".jpg,.jpeg,.png,.pdf,.xlsx,.docx,image/jpeg,image/png,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                   data-sdp-max-files="<?php echo AttachmentValidator::MAX_FILES; ?>"
                   data-sdp-max-bytes="<?php echo AttachmentValidator::MAX_FILE_BYTES; ?>"
                   data-sdp-max-total="<?php echo AttachmentValidator::MAX_TOTAL; ?>"
                   data-sdp-extensions="jpg,jpeg,png,pdf,xlsx,docx"
                   data-sdp-error-text="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_ATTACH_CLIENT_ERROR')); ?>"
                   aria-describedby="sdp-attachments-help">
            <div class="form-text" id="sdp-attachments-help">
                <?php echo Text::sprintf('COM_SERVICEDESKPLUS_ATTACHMENTS_HELP', AttachmentValidator::MAX_FILES, (int) (AttachmentValidator::MAX_FILE_BYTES / 1048576), (int) (AttachmentValidator::MAX_TOTAL / 1048576)); ?>
            </div>
        </div>

        <button class="btn btn-primary" type="submit">
            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" data-sdp-spinner></span>
            <span data-sdp-label data-sdp-busy-text="<?php echo $this->escape(Text::_('COM_SERVICEDESKPLUS_SUBMITTING')); ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_SUBMIT_REQUEST'); ?></span>
        </button>
        <a class="btn btn-link" href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=requests'); ?>"><?php echo Text::_('JCANCEL'); ?></a>

        <input type="hidden" name="task" value="request.create">
        <input type="hidden" name="submission" value="<?php echo $this->escape($this->submissionToken); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
