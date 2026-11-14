<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\EmailNormalizer;

/** @var \SdpPortal\Component\ServiceDeskPlus\Administrator\View\Usermaps\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('multiselect');

$badge = [
    'active'    => 'bg-success',
    'suspended' => 'bg-danger',
    'unmatched' => 'bg-secondary',
    'ambiguous' => 'bg-warning text-dark',
];
?>
<form action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=usermaps'); ?>" method="post" name="adminForm" id="adminForm">
    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

    <?php if ($this->items === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
    <?php else : ?>
        <table class="table" id="usermapList">
            <caption class="visually-hidden"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPINGS_TITLE'); ?></caption>
            <thead>
                <tr>
                    <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_JOOMLA_USER'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_JOOMLA_EMAIL'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_SDP_USER_ID'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_MAPPED_EMAIL'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_STATUS'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_SOURCE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_MATCHED_AT'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_CHECKED_AT'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $i => $item) :
                $editUrl      = Route::_('index.php?option=com_servicedeskplus&view=usermap&layout=edit&joomla_user_id=' . (int) $item->joomla_user_id);
                $emailChanged = $item->joomla_email !== null && EmailNormalizer::normalize($item->joomla_email) !== $item->email;
                ?>
                <tr>
                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->joomla_user_id, false, 'cid', 'cb', $this->escape((string) $item->name)); ?></td>
                    <th scope="row">
                        <?php if ($item->name === null) : ?>
                            <em><?php echo Text::sprintf('COM_SERVICEDESKPLUS_MAPPING_DELETED_USER', (int) $item->joomla_user_id); ?></em>
                        <?php else : ?>
                            <a href="<?php echo $editUrl; ?>"><?php echo $this->escape($item->name); ?></a>
                            <div class="small text-muted"><?php echo $this->escape($item->username); ?></div>
                        <?php endif; ?>
                    </th>
                    <td>
                        <?php echo $this->escape((string) $item->joomla_email); ?>
                        <?php if ($emailChanged) : ?>
                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_EMAIL_CHANGED'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $this->escape((string) $item->sdp_user_id); ?></td>
                    <td><?php echo $this->escape($item->email); ?></td>
                    <td><span class="badge <?php echo $badge[$item->status] ?? 'bg-secondary'; ?>"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS_' . strtoupper($item->status)); ?></span></td>
                    <td><?php echo Text::_('COM_SERVICEDESKPLUS_SOURCE_' . strtoupper($item->source)); ?></td>
                    <td><?php echo $item->matched_at ? HTMLHelper::_('date', $item->matched_at, Text::_('DATE_FORMAT_LC5')) : '—'; ?></td>
                    <td><?php echo HTMLHelper::_('date', $item->checked_at, Text::_('DATE_FORMAT_LC5')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>
    <?php endif; ?>

    <input type="hidden" name="task" value="">
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
