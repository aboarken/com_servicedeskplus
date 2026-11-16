<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\IntegrationsettingsModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\ValidationFailed;

final class SettingsController extends BaseController
{

    /**
     * Pages are shown only through DisplayController, which requires core.admin. Joomla also
     * offers every controller's inherited display() as a task (task={name}.display), which would
     * skip that check for users who may only open the component, so it is refused here.
     */
    public function display($cachable = false, $urlparams = [])
    {
        throw new RouteNotFoundException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
    }
    private const STATE_KEY = 'com_servicedeskplus.edit.settings.data';

    public function save(): void
    {
        $this->guard();

        /** @var IntegrationsettingsModel $model */
        $model = $this->getModel('Integrationsettings', 'Administrator', ['ignore_request' => true]);
        $data  = $this->input->post->get('jform', [], 'array');

        try {
            $model->save($data);
            $this->app->setUserState(self::STATE_KEY, null);
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_SETTINGS_SAVED'), 'success');
        } catch (ValidationFailed $e) {
            // Keep entered values for the form, but never the key.
            unset($data['service_key']);
            $this->app->setUserState(self::STATE_KEY, $data);

            foreach ($e->getMessages() as $message) {
                $this->app->enqueueMessage($message, 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_servicedeskplus&view=integrationsettings', false));
    }

    /**
     * Tests the saved settings with a harmless read call.
     */
    public function test(): void
    {
        $this->guard();

        /** @var IntegrationsettingsModel $model */
        $model = $this->getModel('Integrationsettings', 'Administrator', ['ignore_request' => true]);

        try {
            $model->testConnection();
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_TEST_OK'), 'success');
        } catch (SdpException $e) {
            $this->app->enqueueMessage(Text::sprintf('COM_SERVICEDESKPLUS_TEST_FAILED', Text::_($e->getLanguageKey())), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_servicedeskplus&view=integrationsettings', false));
    }

    /**
     * Shows the pin of the certificate the SDP address presents right now. The admin must compare
     * it with the value computed on the SDP server before pasting it into the pin field.
     */
    public function readPin(): void
    {
        $this->guard();

        /** @var IntegrationsettingsModel $model */
        $model = $this->getModel('Integrationsettings', 'Administrator', ['ignore_request' => true]);

        try {
            $cert = $model->readServerPin();
            $this->app->enqueueMessage(Text::sprintf(
                'COM_SERVICEDESKPLUS_PIN_READ',
                htmlspecialchars($cert['pin'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($cert['subject'] !== '' ? $cert['subject'] : '-', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($cert['issuer'] !== '' ? $cert['issuer'] : '-', ENT_QUOTES, 'UTF-8'),
                $cert['expires'] ? gmdate('Y-m-d', $cert['expires']) : '-'
            ), 'info');
        } catch (\RuntimeException | \InvalidArgumentException) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_PIN_READ_FAILED'), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_servicedeskplus&view=integrationsettings', false));
    }

    private function guard(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_servicedeskplus')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
