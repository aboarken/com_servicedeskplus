<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Announcements\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC4')) : '';
?>
<div class="com-servicedeskplus sdp-announcements">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php if ($this->error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php elseif ($this->items === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENTS_NONE'); ?></div>
    <?php else : ?>
        <div class="list-group mb-4">
            <?php foreach ($this->items as $item) :
                $url  = Route::_('index.php?option=com_servicedeskplus&view=announcement&id=' . rawurlencode($item->id));
                $meta = array_filter([$date($item->date()), $item->type, $item->priority !== '' ? Text::sprintf('COM_SERVICEDESKPLUS_ANNOUNCEMENT_PRIORITY_LABEL', $item->priority) : '']);
                ?>
                <article class="list-group-item">
                    <h2 class="h5 mb-1"><a href="<?php echo $url; ?>"><?php echo $this->escape($item->title !== '' ? $item->title : Text::_('COM_SERVICEDESKPLUS_ANNOUNCEMENT_UNTITLED')); ?></a></h2>
                    <?php if ($meta !== []) : ?>
                        <p class="small text-muted mb-1"><?php echo $this->escape(implode(' · ', $meta)); ?></p>
                    <?php endif; ?>
                    <?php if ($item->excerpt() !== '') : ?>
                        <p class="mb-0"><?php echo $this->escape($item->excerpt()); ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
