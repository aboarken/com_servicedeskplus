<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \SdpPortal\Component\ServiceDeskPlus\Administrator\View\Usermap\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');

$mapping = $this->mapping;
$action  = 'index.php?option=com_servicedeskplus&view=usermap&layout=edit'
    . ($mapping ? '&joomla_user_id=' . $mapping->joomlaUserId : '');
?>
<form action="<?php echo Route::_($action); ?>" method="post" name="adminForm" id="adminForm" class="form-validate" autocomplete="off">

    <?php if ($mapping) : ?>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_CURRENT'); ?></h2>
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_STATUS'); ?></dt>
                    <dd class="col-sm-9"><?php echo Text::_('COM_SERVICEDESKPLUS_STATUS_' . strtoupper($mapping->status)); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_MAPPED_EMAIL'); ?></dt>
                    <dd class="col-sm-9"><?php echo $this->escape($mapping->email); ?></dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_SOURCE'); ?></dt>
                    <dd class="col-sm-9"><?php echo Text::_('COM_SERVICEDESKPLUS_SOURCE_' . strtoupper($mapping->source)); ?></dd>
                </dl>
            </div>
        </div>
    <?php endif; ?>

    <p><?php echo Text::_('COM_SERVICEDESKPLUS_MAPPING_EDIT_HELP'); ?></p>

    <?php echo $this->form->renderFieldset('mapping'); ?>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
