<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\SecretProvider;

/** @var \SdpPortal\Component\ServiceDeskPlus\Administrator\View\Settings\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');

$secretLabel = match ($this->secretSource) {
    SecretProvider::SOURCE_ENV    => 'COM_SERVICEDESKPLUS_SETTINGS_SECRET_ENV',
    SecretProvider::SOURCE_JOOMLA => 'COM_SERVICEDESKPLUS_SETTINGS_SECRET_JOOMLA',
    default                       => 'COM_SERVICEDESKPLUS_SETTINGS_SECRET_NONE',
};
?>
<form action="<?php echo Route::_('index.php?option=com_servicedeskplus&view=settings'); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate" autocomplete="off">

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_STATUS'); ?></h2>
            <ul class="list-unstyled mb-0">
                <li>
                    <?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_KEY_STATUS'); ?>:
                    <strong><?php echo Text::_($this->hasServiceKey ? 'COM_SERVICEDESKPLUS_SETTINGS_KEY_STORED' : 'COM_SERVICEDESKPLUS_SETTINGS_KEY_MISSING'); ?></strong>
                </li>
                <li>
                    <?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_SECRET_SOURCE'); ?>:
                    <strong><?php echo Text::_($secretLabel); ?></strong>
                </li>
            </ul>
        </div>
    </div>

    <?php if (!$this->isHttps) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_WARN_HTTP'); ?></div>
    <?php endif; ?>

    <?php if ($this->insecureTls) : ?>
        <div class="alert alert-danger"><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_WARN_NO_VERIFY'); ?></div>
    <?php elseif ($this->isPinned) : ?>
        <div class="alert alert-success"><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_PINNED'); ?></div>
    <?php endif; ?>

    <?php if ($this->secretSource !== SecretProvider::SOURCE_ENV) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_HINT_ENV_SECRET'); ?></div>
    <?php endif; ?>

    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_FIELDSET_CONNECTION'); ?></legend>
        <?php echo $this->form->renderFieldset('connection'); ?>
    </fieldset>

    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_FIELDSET_REQUESTS'); ?></legend>
        <?php echo $this->form->renderFieldset('requests'); ?>
    </fieldset>

    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_FIELDSET_APPROVALS'); ?></legend>
        <?php echo $this->form->renderFieldset('approvals'); ?>
    </fieldset>

    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_SERVICEDESKPLUS_SETTINGS_FIELDSET_LIMITS'); ?></legend>
        <?php echo $this->form->renderFieldset('limits'); ?>
    </fieldset>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
