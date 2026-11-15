<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\AlreadyDecided;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\AutoDecided;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalNotFound;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalService;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\InvalidDecision;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\NotRecordable;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\RateLimitExceeded;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\SubmissionGuard;

/**
 * Feature 4: approve or reject. POST, CSRF token, ACL, one-time token, rate limit.
 * The approval ids come from the form but are re-checked against SDP (approver must be the
 * logged-in user's SDP user) before the decision is sent with the service key.
 */
final class ApprovalController extends BaseController
{
    use IdentityGuardTrait;

    public function decide(): void
    {
        if (!$this->requireLogin()) {
            return;
        }

        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('sdp.approval.view', 'com_servicedeskplus') || !$user->authorise('sdp.approval.decide', 'com_servicedeskplus')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $post       = $this->input->post;
        $requestId  = $post->getString('request_id', '');
        $levelId    = $post->getString('level_id', '');
        $approvalId = $post->getString('approval_id', '');
        $module     = ApprovalModule::normalize($post->getCmd('module', '')) ?? ApprovalModule::REQUEST;
        $decision   = $post->getCmd('decision', '');
        $comment    = (string) $post->getRaw('comment', '');
        $token      = $post->getAlnum('submission', '');

        $detailUrl = 'index.php?option=com_servicedeskplus&view=approval&id=' . rawurlencode($approvalId)
            . '&request_id=' . rawurlencode($requestId) . '&level_id=' . rawurlencode($levelId)
            . ($module !== ApprovalModule::REQUEST ? '&module=' . $module : '');
        $listUrl   = 'index.php?option=com_servicedeskplus&view=approvals';

        $this->setRedirect(Route::_(preg_match('/^[0-9]{1,20}$/', $approvalId . $requestId . $levelId) ? $detailUrl : $listUrl, false));

        try {
            $identity = $this->currentIdentity();
        } catch (SdpException) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_UNAVAILABLE'), 'error');

            return;
        }

        if (!$identity->isMatched()) {
            $this->setRedirect(Route::_($listUrl, false));

            return;
        }

        $guard = $this->sdpComponent()->getSubmissionGuard();

        if ($guard->consume($token) !== SubmissionGuard::OK) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_APPROVAL_ALREADY_SUBMITTED'), 'warning');

            return;
        }

        try {
            $outcome = $this->sdpComponent()->getApprovalService()
                ->decide($identity, $requestId, $levelId, $approvalId, $decision, $comment, self::approverLabel((string) $user->username, (string) $user->name), $module);
        } catch (AutoDecided $e) {
            $this->app->enqueueMessage(Text::_($e->entryRemoved ? 'COM_SERVICEDESKPLUS_APPROVAL_ERR_AUTO_DECIDED' : 'COM_SERVICEDESKPLUS_APPROVAL_ERR_AUTO_DECIDED_LEFT'), 'error');

            return;
        } catch (NotRecordable) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_APPROVAL_ERR_NOT_RECORDABLE'), 'error');

            return;
        } catch (AlreadyDecided) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_APPROVAL_ERR_ALREADY_DECIDED'), 'warning');

            return;
        } catch (ApprovalNotFound) {
            $this->setRedirect(Route::_($listUrl, false));
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_APPROVAL_NOT_FOUND'), 'error');

            return;
        } catch (InvalidDecision $e) {
            $this->app->enqueueMessage(Text::_($e->getMessage()), 'error');

            return;
        } catch (RateLimitExceeded) {
            $this->app->enqueueMessage(Text::_('COM_SERVICEDESKPLUS_APPROVAL_RATE_LIMITED'), 'error');

            return;
        } catch (SdpException $e) {
            $key = match ($e->getKind()) {
                SdpException::PERMISSION => 'COM_SERVICEDESKPLUS_APPROVAL_ERR_PERMISSION',
                SdpException::NETWORK    => 'COM_SERVICEDESKPLUS_APPROVAL_ERR_UNCERTAIN',
                default                  => $e->getLanguageKey(),
            };
            $this->app->enqueueMessage(Text::_($key), 'error');

            return;
        }

        $message = $decision === 'approve' ? 'COM_SERVICEDESKPLUS_APPROVAL_APPROVED' : 'COM_SERVICEDESKPLUS_APPROVAL_REJECTED';
        $this->app->enqueueMessage(Text::_($message), 'success');

        $notice = match ($outcome) {
            ApprovalService::OUTCOME_UNCONFIRMED   => 'COM_SERVICEDESKPLUS_APPROVAL_UNCONFIRMED',
            ApprovalService::OUTCOME_LEVEL_PENDING => 'COM_SERVICEDESKPLUS_APPROVAL_LEVEL_PENDING',
            ApprovalService::OUTCOME_ORIGINAL_LEFT => 'COM_SERVICEDESKPLUS_APPROVAL_ORIGINAL_LEFT',
            default                                => null,
        };

        if ($notice !== null) {
            $this->app->enqueueMessage(Text::_($notice), 'info');
        }

        $this->setRedirect(Route::_($listUrl, false));
    }

    /**
     * How the user is named in the SDP comment and note: "(username) Full Name".
     */
    private static function approverLabel(string $username, string $name): string
    {
        $username = trim($username);
        $name     = trim($name);

        if ($username === '' || strcasecmp($username, $name) === 0) {
            return $name !== '' ? $name : $username;
        }

        return '(' . $username . ') ' . $name;
    }
}
