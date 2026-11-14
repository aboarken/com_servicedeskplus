<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Utilities\ArrayHelper;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Extension\ServiceDeskPlusComponent;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\EmailNormalizer;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\MappingException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\MappingManager;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\UsermapModel;

/**
 * Admin actions on identity mappings. All tasks need POST + CSRF token + core.admin.
 */
final class UsermapController extends BaseController
{
    private const LIST_URL = 'index.php?option=com_servicedeskplus&view=usermaps';
    private const EDIT_URL = 'index.php?option=com_servicedeskplus&view=usermap&layout=edit';

    public function add(): void
    {
        $this->guard();
        $this->app->setUserState(UsermapModel::STATE_KEY, null);
        $this->setRedirect(Route::_(self::EDIT_URL, false));
    }

    public function cancel(): void
    {
        $this->guard();
        $this->app->setUserState(UsermapModel::STATE_KEY, null);
        $this->setRedirect(Route::_(self::LIST_URL, false));
    }

    public function save(): void
    {
        $this->guard();

        $data      = $this->input->post->get('jform', [], 'array');
        $joomlaId  = (int) ($data['joomla_user_id'] ?? 0);
        $sdpUserId = trim((string) ($data['sdp_user_id'] ?? ''));
        $user      = $this->loadUser($joomlaId);
        $backUrl   = self::EDIT_URL . ($joomlaId > 0 ? '&joomla_user_id=' . $joomlaId : '');

        if ($user === null) {
            $this->fail(Text::_('COM_SERVICEDESKPLUS_MAPPING_ERR_USER'), $data, $backUrl);

            return;
        }

        try {
            $sdpUser = $this->manager()->assign($joomlaId, (string) $user->email, $sdpUserId, $this->actorId());
        } catch (MappingException $e) {
            $this->fail(Text::_($e->getMessage()), $data, $backUrl);

            return;
        } catch (SdpException $e) {
            $this->fail(Text::_($e->getLanguageKey()), $data, $backUrl);

            return;
        }

        $this->app->setUserState(UsermapModel::STATE_KEY, null);
        $this->app->enqueueMessage(Text::sprintf(
            'COM_SERVICEDESKPLUS_MAPPING_SAVED',
            $this->escape((string) $user->name),
            $this->escape((string) ($sdpUser['name'] ?? '')),
            $this->escape((string) ($sdpUser['email_id'] ?? ''))
        ), 'success');

        if (EmailNormalizer::normalize($sdpUser['email_id'] ?? '') !== EmailNormalizer::normalize((string) $user->email)) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_MAPPING_WARN_EMAIL_DIFFERS'), 'warning');
        }

        $this->setRedirect(Route::_(self::LIST_URL, false));
    }

    public function confirm(): void
    {
        $this->forEachSelected(function (int $id, MappingManager $manager): void {
            $user = $this->loadUser($id);

            if ($user === null) {
                throw new MappingException('COM_SERVICEDESKPLUS_MAPPING_ERR_USER');
            }

            $manager->confirm($id, (string) $user->email, $this->actorId());
        }, 'COM_SERVICEDESKPLUS_MAPPING_N_CONFIRMED');
    }

    public function suspend(): void
    {
        $this->forEachSelected(
            fn (int $id, MappingManager $manager) => $manager->suspend($id, $this->actorId()),
            'COM_SERVICEDESKPLUS_MAPPING_N_SUSPENDED'
        );
    }

    public function rematch(): void
    {
        $this->forEachSelected(
            fn (int $id, MappingManager $manager) => $manager->rematch($id, $this->actorId()),
            'COM_SERVICEDESKPLUS_MAPPING_N_REMATCHED'
        );
    }

    /**
     * @param  callable(int, MappingManager): void  $action
     */
    private function forEachSelected(callable $action, string $doneKey): void
    {
        $this->guard();

        $ids  = array_filter(ArrayHelper::toInteger($this->input->post->get('cid', [], 'array')), static fn (int $id) => $id > 0);
        $done = 0;

        foreach ($ids as $id) {
            try {
                $action($id, $this->manager());
                $done++;
            } catch (MappingException $e) {
                $this->app->enqueueMessage(Text::sprintf('COM_SERVICEDESKPLUS_MAPPING_ERR_FOR_USER', $id, Text::_($e->getMessage())), 'error');
            } catch (SdpException $e) {
                $this->app->enqueueMessage(Text::sprintf('COM_SERVICEDESKPLUS_MAPPING_ERR_FOR_USER', $id, Text::_($e->getLanguageKey())), 'error');
            }
        }

        if ($ids === []) {
            $this->app->enqueueMessage(Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');
        } elseif ($done > 0) {
            $this->app->enqueueMessage(Text::plural($doneKey, $done), 'success');
        }

        $this->setRedirect(Route::_(self::LIST_URL, false));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fail(string $message, array $data, string $url): void
    {
        $this->app->setUserState(UsermapModel::STATE_KEY, $data);
        $this->app->enqueueMessage($message, 'error');
        $this->setRedirect(Route::_($url, false));
    }

    private function guard(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.admin', 'com_servicedeskplus')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function loadUser(int $id): ?User
    {
        if ($id <= 0) {
            return null;
        }

        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

        return $user->id > 0 ? $user : null;
    }

    private function actorId(): int
    {
        return (int) $this->app->getIdentity()->id;
    }

    private function manager(): MappingManager
    {
        return $this->component()->getMappingManager();
    }

    private function component(): ServiceDeskPlusComponent
    {
        $component = $this->app->bootComponent('com_servicedeskplus');

        if (!$component instanceof ServiceDeskPlusComponent) {
            throw new \LogicException('Unexpected component class');
        }

        return $component;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
