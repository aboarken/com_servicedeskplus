<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Asset\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC4')) : '—';
$text = fn (string $v): string => $v !== '' ? $this->escape($v) : '—';
$item = $this->item;
?>
<div class="com-servicedeskplus sdp-asset">
    <p><a href="<?php echo Route::_('index.php?option=com_servicedeskplus&view=assets'); ?>">&larr; <?php echo Text::_('COM_SERVICEDESKPLUS_BACK_TO_ASSETS'); ?></a></p>

    <?php if ($item === null) : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php else :
        $overdue = $item->isOverdue($this->now);
        ?>
        <h1 class="h2"><?php echo $text($item->name); ?></h1>

        <?php if ($overdue) : ?>
            <div class="alert alert-danger" role="alert"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_OVERDUE_NOTICE'); ?></div>
        <?php endif; ?>

        <dl class="row <?php echo $overdue ? 'sdp-overdue ps-3' : ''; ?>">
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_TYPE'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->type); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_PRODUCT'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->product); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_SERIAL'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->serialNumber); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_STATE'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->state); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_PURCHASE_DATE'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->purchaseDate); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_WARRANTY_EXPIRY'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->warrantyExpiry); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_DEPARTMENT'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->department); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_LOANED_TO'); ?></dt>
            <dd class="col-sm-9"><?php echo $text($item->loanedTo); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_START'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->loanStart); ?></dd>
            <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_DUE'); ?></dt>
            <dd class="col-sm-9"><?php echo $date($item->loanEnd); ?></dd>
        </dl>
    <?php endif; ?>
</div>
