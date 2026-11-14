<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;

final class RequestformModel extends BaseModel
{
    use SiteServicesTrait;

    public const CREATED_KEY = 'com_servicedeskplus.request.created';
    public const INPUT_KEY   = 'com_servicedeskplus.request.input';

    /**
     * Id of the request created by the last POST (shown once, then cleared).
     */
    public function takeCreatedId(): ?string
    {
        $app = Factory::getApplication();
        $id  = $app->getUserState(self::CREATED_KEY);
        $app->setUserState(self::CREATED_KEY, null);

        return \is_string($id) && preg_match('/^[0-9]{1,20}$/', $id) ? $id : null;
    }

    /**
     * Values the user typed before a failed submit (shown once, then cleared).
     *
     * @return array{subject: string, description: string}
     */
    public function takeInput(): array
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState(self::INPUT_KEY);
        $app->setUserState(self::INPUT_KEY, null);

        return [
            'subject'     => \is_array($data) ? (string) ($data['subject'] ?? '') : '',
            'description' => \is_array($data) ? (string) ($data['description'] ?? '') : '',
        ];
    }

    public function issueSubmissionToken(): string
    {
        $this->identity();

        return $this->sdp()->getSubmissionGuard()->issue();
    }
}
