<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\ListInfo;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditHistoryInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditLogInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Cache\UserCacheInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\Identity;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\RateLimiter;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\RateLimitExceeded;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * Feature 4: approvals assigned to the user.
 *
 * Everything uses the service (technician) key. Because SDP cannot tell which person acted,
 * this class enforces it: a decision is sent only after re-fetching the approval and confirming
 * that its approver is the logged-in user's mapped SDP user and that it is still pending at the
 * current level. The user's name is written into the SDP comment and every decision is audited.
 */
final class ApprovalService
{
    public const ACTION_APPROVE = 'approval.approve';
    public const ACTION_REJECT  = 'approval.reject';
    public const COMMENT_MAX    = 2000;

    public const OUTCOME_CONFIRMED   = 'confirmed';
    public const OUTCOME_UNCONFIRMED = 'unconfirmed';

    /**
     * @param  \Closure(): SdpClient  $clientFactory
     */
    public function __construct(
        private readonly \Closure $clientFactory,
        private readonly UserCacheInterface $cache,
        private readonly AuditLogInterface $audit,
        private readonly AuditHistoryInterface $history,
        private readonly RateLimiter $rateLimiter,
        private readonly LoggerInterface $logger,
        private readonly int $decidePerHour,
        private readonly string $pendingStatus = 'Pending Approval',
        private readonly int $scanLimit = 50
    ) {
    }

    /**
     * Pending approvals where the user is the approver at the current level.
     *
     * SDP has no documented "my pending approvals" endpoint, so this lists the most recent requests
     * whose approval status is pending and checks their current approval level.
     *
     * @return list<PendingApproval>
     *
     * @throws SdpException
     */
    public function listPending(Identity $identity): array
    {
        $sdpUserId = $identity->sdpUserId();
        $cached    = $this->cache->get($identity, 'approvals:pending');

        if ($cached !== null) {
            return array_map(static fn (array $a) => PendingApproval::fromArray($a), $cached);
        }

        $client   = $this->client();
        $requests = $client->listRequests(new ListInfo(
            rowCount: max(1, min(ListInfo::MAX_ROW_COUNT, $this->scanLimit)),
            sortField: 'created_time',
            sortOrder: 'desc',
            searchCriteria: ['field' => 'approval_status.name', 'condition' => 'is', 'value' => $this->pendingStatus]
        ))->items;

        $pending = [];

        foreach ($requests as $request) {
            $requestId = SdpFields::string($request, 'id');

            if (!preg_match('/^[0-9]{1,20}$/', $requestId)) {
                continue;
            }

            try {
                $level = $this->currentLevel($client->getApprovalLevels($requestId));

                if ($level === null) {
                    continue;
                }

                $levelId = SdpFields::string($level, 'id');

                foreach ($client->getApprovals($requestId, $levelId) as $approval) {
                    if ($this->isMine($approval, $sdpUserId) && $this->isPendingStatus($approval)) {
                        $pending[] = new PendingApproval(
                            $requestId,
                            $levelId,
                            SdpFields::string($approval, 'id'),
                            SdpFields::string($request, 'subject'),
                            SdpFields::name($request, 'requester'),
                            SdpFields::timestamp($approval, 'sent_on'),
                            SdpFields::string($level, 'level') ?: SdpFields::string($level, 'name')
                        );
                    }
                }
            } catch (SdpException $e) {
                if (\in_array($e->getKind(), [SdpException::NETWORK, SdpException::AUTH, SdpException::NOT_CONFIGURED], true)) {
                    throw $e;
                }

                $this->logger->warning(\sprintf('Approvals of request %s could not be read (%s)', $requestId, $e->getKind()));
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        $this->cache->set($identity, 'approvals:pending', array_map(static fn (PendingApproval $p) => $p->toArray(), $pending));

        return $pending;
    }

    /**
     * Decisions this user made through the portal (from the audit table).
     *
     * @return list<array{created: string, action: string, result: string, sdp_request_id: ?string, approval_id: ?string, detail: string}>
     */
    public function history(Identity $identity, int $limit = 50): array
    {
        $identity->sdpUserId();

        return $this->history->recent($identity->joomlaUserId, [self::ACTION_APPROVE, self::ACTION_REJECT], $limit);
    }

    /**
     * @throws ApprovalNotFound|SdpException
     */
    public function getForUser(Identity $identity, string $requestId, string $levelId, string $approvalId): ApprovalDetail
    {
        [$approval, $level, $requestId, $levelId, $approvalId] = $this->locateOwn($identity, $requestId, $levelId, $approvalId);

        try {
            $request = $this->client()->getRequest($requestId);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::NOT_FOUND) {
                throw new ApprovalNotFound();
            }

            throw $e;
        }

        return new ApprovalDetail(
            $requestId,
            $levelId,
            $approvalId,
            SdpFields::string($request, 'subject'),
            HtmlText::toPlain(SdpFields::string($request, 'description')),
            SdpFields::name($request, 'requester'),
            SdpFields::timestamp($approval, 'sent_on'),
            HtmlText::toPlain(SdpFields::string($approval, 'comments')),
            SdpFields::name($approval, 'status'),
            $this->isPending($approval, $level)
        );
    }

    /**
     * Approve or reject.
     *
     * @param  string  $decision     approve | reject
     * @param  string  $approverName Joomla display name of the user, written into the SDP comment
     *                               because SDP records the service technician as the actor
     *
     * @return string  OUTCOME_CONFIRMED when a re-read shows the approval is no longer pending
     *
     * @throws ApprovalNotFound|AlreadyDecided|InvalidDecision|RateLimitExceeded|SdpException
     */
    public function decide(Identity $identity, string $requestId, string $levelId, string $approvalId, string $decision, string $comment, string $approverName = ''): string
    {
        $identity->sdpUserId();
        $userId = $identity->joomlaUserId;

        if (!\in_array($decision, ['approve', 'reject'], true)) {
            throw new InvalidDecision('COM_SERVICEDESKPLUS_APPROVAL_ERR_DECISION');
        }

        $action  = $decision === 'approve' ? self::ACTION_APPROVE : self::ACTION_REJECT;
        $comment = str_replace(["\r\n", "\r"], "\n", $comment);
        $comment = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $comment) ?? '');

        if (mb_strlen($comment) > self::COMMENT_MAX) {
            throw new InvalidDecision('COM_SERVICEDESKPLUS_APPROVAL_ERR_COMMENT_TOO_LONG');
        }

        if (!$this->allowDecision($userId)) {
            $this->audit->record($userId, $action, AuditLogInterface::RESULT_DENIED, $this->safeId($requestId), $this->safeId($approvalId), 'rate_limited');

            throw new RateLimitExceeded();
        }

        // Fresh read with the service key: approver must be the user, approval still pending.
        try {
            [$approval, $level, $requestId, $levelId, $approvalId] = $this->locateOwn($identity, $requestId, $levelId, $approvalId);
        } catch (ApprovalNotFound $e) {
            $this->audit->record($userId, $action, AuditLogInterface::RESULT_DENIED, $this->safeId($requestId), $this->safeId($approvalId), 'not_approver_or_missing');

            throw $e;
        }

        if (!$this->isPending($approval, $level)) {
            $this->audit->record($userId, $action, AuditLogInterface::RESULT_DENIED, $requestId, $approvalId, 'not_pending');

            throw new AlreadyDecided();
        }

        try {
            // Comments are plain text (JSON-encoded by SdpClient), not HTML.
            $this->client()->decideApproval($requestId, $levelId, $approvalId, $decision, self::sdpComment($decision, $approverName, $comment));
        } catch (SdpException $e) {
            if ($this->stillPending($identity, $requestId, $levelId, $approvalId) === false) {
                $this->audit->record($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'already_decided');

                throw new AlreadyDecided();
            }

            $this->audit->record($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, $e->getKind() . ':' . $e->getSdpMessage());

            throw $e;
        }

        $this->cache->clear($identity, 'approvals:');

        // Verify with a fresh read that SDP applied the decision.
        $outcome = self::OUTCOME_UNCONFIRMED;

        try {
            [$after] = $this->locateOwn($identity, $requestId, $levelId, $approvalId);
            $outcome = $this->isPendingStatus($after) ? self::OUTCOME_UNCONFIRMED : self::OUTCOME_CONFIRMED;
        } catch (ApprovalNotFound | SdpException) {
            // The decision was accepted by SDP; the verification read is best effort.
        }

        $this->audit->record($userId, $action, AuditLogInterface::RESULT_OK, $requestId, $approvalId, $outcome);

        return $outcome;
    }

    /**
     * Comment sent to SDP: who decided in Joomla, then the user's own comment.
     */
    public static function sdpComment(string $decision, string $approverName, string $comment): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $approverName) ?? '');
        $verb = $decision === 'approve' ? 'Approved' : 'Rejected';
        $line = $name !== '' ? \sprintf('%s by %s via the Joomla portal.', $verb, mb_substr($name, 0, 200)) : $verb . ' via the Joomla portal.';

        return $comment !== '' ? $line . "\n\n" . $comment : $line;
    }

    /**
     * Find the approval through the spec's endpoints and check its approver is the user.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string, 4: string}
     *
     * @throws ApprovalNotFound|SdpException
     */
    private function locateOwn(Identity $identity, string $requestId, string $levelId, string $approvalId): array
    {
        try {
            $requestId  = SdpClient::id($requestId);
            $levelId    = SdpClient::id($levelId);
            $approvalId = SdpClient::id($approvalId);
        } catch (\InvalidArgumentException) {
            throw new ApprovalNotFound();
        }

        $client = $this->client();

        try {
            $level = null;

            foreach ($client->getApprovalLevels($requestId) as $candidate) {
                if (SdpFields::string($candidate, 'id') === $levelId) {
                    $level = $candidate;
                    break;
                }
            }

            if ($level === null) {
                throw new ApprovalNotFound();
            }

            $approval = null;

            foreach ($client->getApprovals($requestId, $levelId) as $candidate) {
                if (SdpFields::string($candidate, 'id') === $approvalId) {
                    $approval = $candidate;
                    break;
                }
            }
        } catch (SdpException $e) {
            if (\in_array($e->getKind(), [SdpException::NOT_FOUND, SdpException::VALIDATION], true)) {
                throw new ApprovalNotFound();
            }

            throw $e;
        }

        if ($approval === null) {
            throw new ApprovalNotFound();
        }

        if (!$this->isMine($approval, $identity->sdpUserId())) {
            $this->audit->record($identity->joomlaUserId, 'approval.view', AuditLogInterface::RESULT_DENIED, $requestId, $approvalId, 'not_approver');

            throw new ApprovalNotFound();
        }

        return [$approval, $level, $requestId, $levelId, $approvalId];
    }

    private function stillPending(Identity $identity, string $requestId, string $levelId, string $approvalId): ?bool
    {
        try {
            [$approval, $level] = $this->locateOwn($identity, $requestId, $levelId, $approvalId);

            return $this->isPending($approval, $level);
        } catch (ApprovalNotFound | SdpException) {
            return null;
        }
    }

    /** @param list<array<string, mixed>> $levels */
    private function currentLevel(array $levels): ?array
    {
        foreach ($levels as $level) {
            if ($this->isCurrentLevel($level)) {
                return $level;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $level */
    private function isCurrentLevel(array $level): bool
    {
        if (\array_key_exists('is_current', $level)) {
            return filter_var($level['is_current'], FILTER_VALIDATE_BOOLEAN);
        }

        // Builds without is_current: the pending level is the current one.
        return mb_strtolower(SdpFields::name($level, 'status')) === mb_strtolower($this->pendingStatus);
    }

    /** @param array<string, mixed> $approval */
    private function isMine(array $approval, string $sdpUserId): bool
    {
        return $sdpUserId !== '' && SdpFields::id($approval, 'approver') === $sdpUserId;
    }

    /** @param array<string, mixed> $approval */
    private function isPendingStatus(array $approval): bool
    {
        return mb_strtolower(SdpFields::name($approval, 'status')) === mb_strtolower($this->pendingStatus);
    }

    /**
     * @param array<string, mixed> $approval
     * @param array<string, mixed> $level
     */
    private function isPending(array $approval, array $level): bool
    {
        return $this->isPendingStatus($approval) && $this->isCurrentLevel($level);
    }

    private function allowDecision(int $userId): bool
    {
        // Approve and reject share one budget.
        return $this->rateLimiter->allowAny($userId, [self::ACTION_APPROVE, self::ACTION_REJECT], $this->decidePerHour);
    }

    private function safeId(string $id): ?string
    {
        return preg_match('/^[0-9]{1,20}$/', $id) ? $id : null;
    }

    private function client(): SdpClient
    {
        return ($this->clientFactory)();
    }
}
