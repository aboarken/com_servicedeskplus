<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Assets\HtmlView $this */

$date = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC4')) : '—';
$text = fn (string $v): string => $v !== '' ? $this->escape($v) : '—';
?>
<div class="com-servicedeskplus sdp-assets">
    <h1 class="h2"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_TITLE'); ?></h1>

    <?php if ($this->error !== '') : ?>
        <div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
    <?php elseif ($this->items === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_NONE'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSETS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_NAME'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_TYPE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_PRODUCT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_SERIAL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_ASSET_STATE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_START'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_DUE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $item) :
                    $overdue = $item->isOverdue($this->now);
                    $url     = Route::_('index.php?option=com_servicedeskplus&view=asset&id=' . rawurlencode($item->id));
                    ?>
                    <tr class="<?php echo $overdue ? 'table-danger sdp-overdue' : ''; ?>">
                        <th scope="row"><a href="<?php echo $url; ?>"><?php echo $text($item->name); ?></a></th>
                        <td><?php echo $text($item->type); ?></td>
                        <td><?php echo $text($item->product); ?></td>
                        <td><?php echo $text($item->serialNumber); ?></td>
                        <td><?php echo $text($item->state); ?></td>
                        <td><?php echo $date($item->loanStart); ?></td>
                        <td>
                            <?php echo $date($item->loanEnd); ?>
                            <?php if ($overdue) : ?>
                                <span class="badge bg-danger"><?php echo Text::_('COM_SERVICEDESKPLUS_LOAN_OVERDUE'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
