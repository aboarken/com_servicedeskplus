<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Announcement\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '';
$item = $this->item;
?>
<div class="com-servicedeskplus sdp-announcement">
    <p><a href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=announcements'); ?>">&larr; <?php echo Text::_('COM_SERVICEDESKPLUS_BACK_TO_ANNOUNCEMENTS'); ?></a></p>

    <?php if ($item === null) : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else : ?>
        <h1 class="h2"><?php echo $this->escape($item->title !== '' ? $item->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTITLED')); ?></h1>

        <dl class="row">
            <?php if ($date($item->date()) !== '') : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_DATE'); ?></dt>
                <dd class="col-sm-9"><?php echo $date($item->date()); ?></dd>
            <?php endif; ?>
            <?php if ($item->toTime !== null) : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTIL'); ?></dt>
                <dd class="col-sm-9"><?php echo $date($item->toTime); ?></dd>
            <?php endif; ?>
            <?php if ($item->type !== '') : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_TYPE'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($item->type); ?></dd>
            <?php endif; ?>
            <?php if ($item->priority !== '') : ?>
                <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_PRIORITY'); ?></dt>
                <dd class="col-sm-9"><?php echo $this->escape($item->priority); ?></dd>
            <?php endif; ?>
        </dl>

        <div class="sdp-text mb-4"><?php echo nl2br($this->escape($item->content !== '' ? $item->content : '—')); ?></div>
    <?php endif; ?>
</div>
