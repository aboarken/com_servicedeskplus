<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Request\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '—';
$item = $this->item;
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
            <dd class="col-sm-9"><?php echo $this->escape($item->priority !== '' ? $item->priority : '—'); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_TECHNICIAN'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->technician !== '' ? $item->technician : Text::_('COM_SERVICEDESKPLUS_UNASSIGNED')); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_CATEGORY'); ?></dt>
            <dd class="col-sm-9"><?php echo $this->escape($item->category !== '' ? $item->category : '—'); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_CREATED'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->createdAt); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_UPDATED'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->updatedAt); ?></dd>
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
    <?php endif; ?>
</div>
