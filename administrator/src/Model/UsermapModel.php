<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
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
