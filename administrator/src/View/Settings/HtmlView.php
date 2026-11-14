<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\View\Settings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\SettingsModel;

final class HtmlView extends BaseHtmlView
{
    public Form $form;

    public bool $hasServiceKey = false;

    public bool $isHttps = true;

    public bool $isPinned = false;

    public bool $insecureTls = false;

    public string $secretSource = '';

    public function display($tpl = null): void
    {
        /** @var SettingsModel $model */
        $model = $this->getModel();

        $this->form          = $model->getForm();
        $settings            = $model->settings();
        $this->hasServiceKey = $settings->hasServiceKey();
        $this->isHttps       = $settings->baseUrl() === '' || str_starts_with($settings->baseUrl(), 'https://');
        $this->isPinned      = $settings->sslPin() !== '';
        $this->insecureTls   = !$settings->verifySsl() && !$this->isPinned;
        $this->secretSource  = $model->secretSource();

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_SERVICEDESKPLUS_SETTINGS_TITLE'), 'cog');

        $toolbar = $this->getDocument()->getToolbar();

        $toolbar->apply('settings.save', 'JSAVE');

        $toolbar->standardButton('test', 'COM_SERVICEDESKPLUS_TEST_CONNECTION')
            ->task('settings.test')
            ->icon('icon-plug')
            ->listCheck(false);

        $toolbar->standardButton('readpin', 'COM_SERVICEDESKPLUS_READ_PIN')
            ->task('settings.readPin')
            ->icon('icon-lock')
            ->listCheck(false);

        $toolbar->preferences('com_servicedeskplus');
    }
}
