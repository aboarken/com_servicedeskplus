<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Extension\ServiceDeskPlusComponent;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\UserMapping;

final class UsermapModel extends FormModel
{
    public const STATE_KEY = 'com_servicedeskplus.edit.usermap.data';

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_servicedeskplus.usermap', 'usermap', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        if ($this->requestedUserId() > 0) {
            $form->setFieldAttribute('joomla_user_id', 'readonly', 'true');
        }

        return $form;
    }

    /**
     * The posted mapping, filtered and validated by the form (forms/usermap.xml) on the server,
     * as the browser already does.
     *
     * @param  array<string, mixed>  $data  Raw posted jform data
     *
     * @return array{joomla_user_id: int, sdp_user_id: string}
     *
     * @throws ValidationFailed
     */
    public function validated(array $data): array
    {
        $form = $this->getForm($data, false);

        if (!$form instanceof Form) {
            throw new ValidationFailed([Text::_('JLIB_FORM_ERROR_FORM_NOT_LOADED')]);
        }

        $filtered = $form->filter($data);

        if ($form->validate($filtered) !== true) {
            $messages = [];

            foreach ($form->getErrors() as $error) {
                $messages[] = $error instanceof \Throwable ? $error->getMessage() : (string) $error;
            }

            throw new ValidationFailed($messages ?: [Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')]);
        }

        return ['joomla_user_id' => (int) ($filtered['joomla_user_id'] ?? 0), 'sdp_user_id' => trim((string) ($filtered['sdp_user_id'] ?? ''))];
    }

    public function getMapping(): ?UserMapping
    {
        $id = $this->requestedUserId();

        return $id > 0 ? $this->component()->getUserMapRepository()->find($id) : null;
    }

    protected function loadFormData()
    {
        // Values of a rejected save are shown once, and only on the form of the same user.
        $app   = Factory::getApplication();
        $state = $app->getUserState(self::STATE_KEY, []);
        $app->setUserState(self::STATE_KEY, null);

        if (\is_array($state) && $state !== [] && (int) ($state['joomla_user_id'] ?? 0) === $this->requestedUserId()) {
            return $state;
        }

        $mapping = $this->getMapping();

        return [
            'joomla_user_id' => $this->requestedUserId() ?: '',
            'sdp_user_id'    => $mapping?->sdpUserId ?? '',
        ];
    }

    private function requestedUserId(): int
    {
        return max(0, Factory::getApplication()->getInput()->getInt('joomla_user_id', 0));
    }

    private function component(): ServiceDeskPlusComponent
    {
        $component = Factory::getApplication()->bootComponent('com_servicedeskplus');

        if (!$component instanceof ServiceDeskPlusComponent) {
            throw new \LogicException('Unexpected component class');
        }

        return $component;
    }
}
