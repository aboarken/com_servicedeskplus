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
use SdpPortal\Component\ServiceDeskPlus\Administrator\Audit\AuditCounterInterface;
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
 *
 * SDP only accepts a decision from the assigned approver. When it refuses the service key
 * (code 4201, "not authorized to take action on this approval") and the API technician's e-mail
 * is configured, the decision is recorded through that technician instead ("approval account"):
 * it is added as approver on the same level, takes the decision with the real approver's name
 * in the comment, the real approver's pending entry is removed and a private request note
 * records who decided. The ownership check above always runs first.
 */
final class ApprovalService
{
    public const ACTION_APPROVE = 'approval.approve';
    public const ACTION_REJECT  = 'approval.reject';
    /** Longest comment the user can type; SDP stores at most SDP_COMMENT_MAX on one line. */
    public const COMMENT_MAX     = 200;
    public const SDP_COMMENT_MAX = 250;

    public const OUTCOME_CONFIRMED   = 'confirmed';
    public const OUTCOME_UNCONFIRMED = 'unconfirmed';

    /** Recorded through the approval account; SDP still waits for other approvers on the level. */
    public const OUTCOME_LEVEL_PENDING = 'level_pending';

    /** Recorded through the approval account, but the approver's own pending entry could not be removed. */
    public const OUTCOME_ORIGINAL_LEFT = 'original_left';

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
        private readonly int $scanLimit = 50,
        private readonly string $approvalAccountEmail = '',
        private readonly array $modules = ApprovalModule::ALL
    ) {
    }

    /** SDP user id of the approval account, once looked up. */
    private ?string $approvalAccountId = null;

    /** Module and title of the record of the decision in progress, for the audit rows. */
    private string $decisionModule  = '';
    private string $decisionSubject = '';

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

        $client  = $this->client();
        $pending = $this->listFromApprovalsEndpoint($sdpUserId);

        if ($pending !== null && $pending !== []) {
            $pending = self::onePerLevel($pending);
            $this->cache->set($identity, 'approvals:pending', array_map(static fn (PendingApproval $p) => $p->toArray(), $pending));

            return $pending;
        }

        // GET /api/v3/approvals only lists the key owner's approvals on some builds (16030): scan
        // each enabled module's records that may be waiting for approval instead.
        $pending = [];

        foreach ($this->modules as $module) {
            array_push($pending, ...$this->scanModule($client, $module, $sdpUserId));
        }

        $pending = self::onePerLevel($pending);
        $this->cache->set($identity, 'approvals:pending', array_map(static fn (PendingApproval $p) => $p->toArray(), $pending));

        return $pending;
    }

    /**
     * Pending approvals of one approver in one module, found by reading the module's newest
     * records that may be waiting for approval and their current approval level. A module the
     * build or the API technician cannot read is skipped (logged); other modules still show.
     *
     * @return list<PendingApproval>
     *
     * @throws SdpException  network, authentication and configuration problems
     */
    private function scanModule(SdpClient $client, string $module, string $sdpUserId): array
    {
        $query = ApprovalModule::scanQuery($module, $this->pendingStatus, max(1, min(ListInfo::MAX_ROW_COUNT, $this->scanLimit)));
        $fatal = [SdpException::NETWORK, SdpException::AUTH, SdpException::NOT_CONFIGURED];

        try {
            $records = $client->listModule($module, new ListInfo(
                rowCount: $query['limit'],
                sortField: $query['sort'],
                sortOrder: 'desc',
                searchCriteria: $query['criteria']
            ))->items;
        } catch (SdpException $e) {
            if (\in_array($e->getKind(), $fatal, true)) {
                throw $e;
            }

            $this->logger->warning(\sprintf('%s approvals could not be listed (%s); skipped', ucfirst(str_replace('_', ' ', $module)), $e->getKind()));

            return [];
        }

        $fields  = ApprovalModule::fields($module);
        $pending = [];

        foreach ($records as $record) {
            $entityId = SdpFields::string($record, 'id');

            if (!preg_match('/^[0-9]{1,20}$/', $entityId) || self::isClosed($record)) {
                continue;
            }

            try {
                $level = $this->currentLevel($client->getApprovalLevels($entityId, $module));

                if ($level === null) {
                    continue;
                }

                $levelId = SdpFields::string($level, 'id');

                foreach ($client->getApprovals($entityId, $levelId, $module) as $approval) {
                    if ($this->isMine($approval, $sdpUserId) && $this->isPendingStatus($approval)) {
                        $pending[] = new PendingApproval(
                            $entityId,
                            $levelId,
                            SdpFields::string($approval, 'id'),
                            self::firstText($record, $fields['title'], false),
                            self::firstText($record, $fields['requester'], true),
                            SdpFields::timestamp($approval, 'sent_on'),
                            SdpFields::string($level, 'level') ?: SdpFields::string($level, 'name'),
                            $module
                        );
                    }
                }
            } catch (SdpException $e) {
                if (\in_array($e->getKind(), $fatal, true)) {
                    throw $e;
                }

                $this->logger->warning(\sprintf('Approvals of %s %s could not be read (%s)', str_replace('_', ' ', $module), $entityId, $e->getKind()));
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $pending;
    }

    /**
     * Pending approvals of one approver from GET /api/v3/approvals, across requests, changes,
     * releases and purchase orders. Returns null when the endpoint cannot be used: it fails, or
     * it ignores the approver criteria (any item of another approver; SDP's documented filter
     * "my_pending_approvals" only covers the key's owner). Every item is checked here again.
     *
     * @return list<PendingApproval>|null
     *
     * @throws SdpException  network, authentication and configuration problems
     */
    private function listFromApprovalsEndpoint(string $sdpUserId): ?array
    {
        try {
            $items = $this->client()->listApprovals([
                'row_count'       => ListInfo::MAX_ROW_COUNT,
                'start_index'     => 1,
                'search_criteria' => [
                    ['field' => 'approver.id', 'condition' => 'is', 'value' => $sdpUserId],
                    ['field' => 'status.name', 'condition' => 'is', 'value' => $this->pendingStatus, 'logical_operator' => 'AND'],
                ],
            ]);
        } catch (SdpException $e) {
            if (\in_array($e->getKind(), [SdpException::NETWORK, SdpException::AUTH, SdpException::NOT_CONFIGURED], true)) {
                throw $e;
            }

            $this->logger->info(\sprintf('GET /api/v3/approvals is not usable on this build (%s); scanning requests instead', $e->getKind()));

            return null;
        }

        $pending = [];

        foreach ($items as $item) {
            if (!$this->isMine($item, $sdpUserId)) {
                $this->logger->warning('GET /api/v3/approvals ignored the approver criteria; scanning requests instead');

                return null;
            }

            $module = ApprovalModule::normalize(SdpFields::string($item, 'associated_entity'));
            $level  = \is_array($item['approval_level'] ?? null) ? $item['approval_level'] : [];
            $parent = $module !== null && \is_array($level[ApprovalModule::entityKey($module)] ?? null) ? $level[ApprovalModule::entityKey($module)] : null;

            if ($module === null || $parent === null || !$this->isPendingStatus($item) || self::isClosed($parent)) {
                continue;
            }

            $entityId   = SdpFields::string($parent, 'id');
            $levelId    = SdpFields::string($level, 'id');
            $approvalId = SdpFields::string($item, 'id');

            if (!preg_match('/^[0-9]{1,20}$/', $entityId . '') || !preg_match('/^[0-9]{1,20}$/', $levelId) || !preg_match('/^[0-9]{1,20}$/', $approvalId)) {
                continue;
            }

            $fields    = ApprovalModule::fields($module);
            $pending[] = new PendingApproval(
                $entityId,
                $levelId,
                $approvalId,
                self::firstText($parent, $fields['title'], false),
                self::firstText($parent, $fields['requester'], true),
                SdpFields::timestamp($item, 'sent_on'),
                SdpFields::string($level, 'name'),
                $module
            );
        }

        return $pending;
    }

    /**
     * Decisions this user made through the portal (from the audit table).
     *
     * @return list<array{created: string, action: string, result: string, sdp_request_id: ?string, approval_id: ?string, detail: string}>
     */
    public function history(Identity $identity, int $limit = 200): array
    {
        $identity->sdpUserId();

        return $this->history->recent($identity->joomlaUserId, [self::ACTION_APPROVE, self::ACTION_REJECT], $limit);
    }

    /**
     * How many approvals the user approved and rejected through the portal (recorded decisions;
     * decisions made directly in ServiceDesk Plus are not known to the portal). Null when the
     * audit store cannot count.
     *
     * @return array{approved: int|null, rejected: int|null}
     */
    public function decisionCounts(Identity $identity): array
    {
        $identity->sdpUserId();

        if (!$this->history instanceof AuditCounterInterface) {
            return ['approved' => null, 'rejected' => null];
        }

        $since = new \DateTimeImmutable('@0');

        return [
            'approved' => $this->history->countSince($identity->joomlaUserId, self::ACTION_APPROVE, $since, [AuditLogInterface::RESULT_OK]),
            'rejected' => $this->history->countSince($identity->joomlaUserId, self::ACTION_REJECT, $since, [AuditLogInterface::RESULT_OK]),
        ];
    }

    /**
     * @throws ApprovalNotFound|SdpException
     */
    public function getForUser(Identity $identity, string $requestId, string $levelId, string $approvalId, string $module = ApprovalModule::REQUEST): ApprovalDetail
    {
        $module = ApprovalModule::normalize($module) ?? throw new ApprovalNotFound();

        [$approval, $level, $requestId, $levelId, $approvalId] = $this->locateOwn($identity, $requestId, $levelId, $approvalId, $module);

        try {
            $request = $this->client()->getModuleEntity($module, $requestId);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::NOT_FOUND) {
                throw new ApprovalNotFound();
            }

            throw $e;
        }

        $fields = ApprovalModule::fields($module);

        return new ApprovalDetail(
            $requestId,
            $levelId,
            $approvalId,
            self::firstText($request, $fields['title'], false),
            HtmlText::toPlain(self::firstText($request, $fields['description'], false)),
            self::firstText($request, $fields['requester'], true),
            SdpFields::timestamp($approval, 'sent_on'),
            HtmlText::toPlain(SdpFields::string($approval, 'comments')),
            SdpFields::name($approval, 'status'),
            $this->isPending($approval, $level),
            $module
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
    public function decide(Identity $identity, string $requestId, string $levelId, string $approvalId, string $decision, string $comment, string $approverName = '', string $module = ApprovalModule::REQUEST): string
    {
        $identity->sdpUserId();
        $userId = $identity->joomlaUserId;
        $module = ApprovalModule::normalize($module) ?? throw new ApprovalNotFound();

        $this->decisionModule  = $module;
        $this->decisionSubject = '';

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
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_DENIED, $this->safeId($requestId), $this->safeId($approvalId), 'rate_limited');

            throw new RateLimitExceeded();
        }

        // Fresh read with the service key: approver must be the user, approval still pending.
        try {
            [$approval, $level, $requestId, $levelId, $approvalId] = $this->locateOwn($identity, $requestId, $levelId, $approvalId, $module);
        } catch (ApprovalNotFound $e) {
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_DENIED, $this->safeId($requestId), $this->safeId($approvalId), 'not_approver_or_missing');

            throw $e;
        }

        if (!$this->isPending($approval, $level)) {
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_DENIED, $requestId, $approvalId, 'not_pending');

            throw new AlreadyDecided();
        }

        $this->decisionSubject = $this->recordTitle($module, $requestId);
        $sdpComment            = self::sdpComment($decision, $approverName, $comment);

        try {
            // Comments are plain text (JSON-encoded by SdpClient), not HTML.
            $this->client()->decideApproval($requestId, $levelId, $approvalId, $decision, $sdpComment, $module);
        } catch (SdpException $e) {
            if ($e->getKind() === SdpException::PERMISSION && $this->approvalAccountEmail !== '') {
                return $this->decideThroughAccount($identity, $action, $requestId, $levelId, $approvalId, $decision, $sdpComment, $approverName, $e, $module);
            }

            if ($this->stillPending($identity, $requestId, $levelId, $approvalId, $module) === false) {
                $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'already_decided');

                throw new AlreadyDecided();
            }

            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, $e->getKind() . ':' . $e->getSdpMessage());

            throw $e;
        }

        $this->cache->clear($identity, 'approvals:');

        // Verify with a fresh read that SDP applied the decision.
        $outcome = self::OUTCOME_UNCONFIRMED;

        try {
            [$after] = $this->locateOwn($identity, $requestId, $levelId, $approvalId, $module);
            $outcome = $this->isPendingStatus($after) ? self::OUTCOME_UNCONFIRMED : self::OUTCOME_CONFIRMED;
        } catch (ApprovalNotFound | SdpException) {
            // The decision was accepted by SDP; the verification read is best effort.
        }

        $this->recordDecision($userId, $action, AuditLogInterface::RESULT_OK, $requestId, $approvalId, $outcome);

        return $outcome;
    }

    /**
     * Audit row of a decision, with the module and title of the record decided on, so
     * "My past decisions" can show what each decision was about.
     */
    private function recordDecision(int $userId, string $action, string $result, ?string $entityId, ?string $approvalId, string $detail): void
    {
        $this->audit->record($userId, $action, $result, $entityId, $approvalId, $detail, 0, $this->decisionModule, $this->decisionSubject);
    }

    /** Title of a request, change, release or purchase order; '' when it cannot be read. */
    private function recordTitle(string $module, string $entityId): string
    {
        try {
            return self::firstText($this->client()->getModuleEntity($module, $entityId), ApprovalModule::fields($module)['title'], false);
        } catch (SdpException | \InvalidArgumentException) {
            return '';
        }
    }

    /**
     * Comment sent to SDP: who decided in Joomla, then the user's own comment.
     * SDP accepts plain text on one line, at most SDP_COMMENT_MAX characters.
     */
    public static function sdpComment(string $decision, string $approverName, string $comment): string
    {
        $oneLine = static fn (string $t): string => trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $t) ?? '') ?? '');

        $name    = mb_substr($oneLine($approverName), 0, 100);
        $comment = $oneLine($comment);
        $verb    = $decision === 'approve' ? 'Approved' : 'Rejected';
        $line    = $name !== '' ? \sprintf('%s by %s via the Joomla portal.', $verb, $name) : $verb . ' via the Joomla portal.';

        return mb_substr($comment !== '' ? $line . ' Custom comment: ' . $comment : $line, 0, self::SDP_COMMENT_MAX);
    }

    /**
     * Record the decision through the approval account after SDP refused the direct decision.
     * The caller has already checked that the user is the approver and the approval is pending.
     *
     * @throws SdpException  the original refusal when the account cannot be used, or the failure
     */
    private function decideThroughAccount(
        Identity $identity,
        string $action,
        string $requestId,
        string $levelId,
        string $approvalId,
        string $decision,
        string $sdpComment,
        string $approverName,
        SdpException $refusal,
        string $module
    ): string {
        $userId    = $identity->joomlaUserId;
        $client    = $this->client();
        $accountId = $this->approvalAccountId();

        if ($accountId === null || $accountId === $identity->sdpUserId()) {
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'refused:no_approval_account');

            throw $refusal;
        }

        // Reuse a pending entry of the account left by an earlier attempt, otherwise add one.
        $accountApprovalId = null;
        $added             = false;

        try {
            foreach ($client->getApprovals($requestId, $levelId, $module) as $candidate) {
                if (SdpFields::id($candidate, 'approver') === $accountId && $this->isPendingStatus($candidate)) {
                    $accountApprovalId = SdpFields::string($candidate, 'id');
                    break;
                }
            }

            if ($accountApprovalId === null) {
                $accountApprovalId = SdpClient::id(SdpFields::string($client->addApproval($requestId, $levelId, $accountId, $module), 'id'));
                $added             = true;
            }
        } catch (SdpException $e) {
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'account_add:' . $e->getKind() . ':' . $e->getSdpMessage());

            // Typically the record is closed: SDP lets nobody change its approvers.
            if ($e->getKind() === SdpException::PERMISSION) {
                throw new NotRecordable('The API technician could not be added to the approval level', 0, $e);
            }

            throw $e;
        } catch (\InvalidArgumentException) {
            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'account_add:no_id');

            throw new SdpException(SdpException::BAD_RESPONSE, 'Added approval has no id');
        }

        try {
            // SDP only accepts a decision once the approval e-mail was sent. It goes to the API
            // technician's mailbox; a failure here (e.g. already sent) is left to the decision.
            try {
                $what = str_replace('_', ' ', ApprovalModule::entityKey($module)) . ' ' . $requestId;
                $client->sendApprovalNotification(
                    $requestId,
                    $levelId,
                    $accountApprovalId,
                    \sprintf('%s: approval recorded via the Joomla portal', ucfirst($what)),
                    HtmlText::fromPlain(\sprintf(
                        'The assigned approver (%s) decided on %s via the Joomla portal. The decision is recorded under this account. No action is needed.',
                        trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $approverName) ?? '') ?: 'unknown',
                        $what
                    )),
                    $module
                );
            } catch (SdpException $e) {
                $this->logger->warning(\sprintf('Approval e-mail for approval %s of request %s could not be sent (%s)', $accountApprovalId, $requestId, $e->getKind()));
            }

            // SDP may already have decided the added entry by itself ("Auto-approve requests if
            // already approved by the same approver in the previous stages", "... when the requester
            // is the approver"). Never let that stand for the opposite of the user's decision.
            $state  = $this->approvalStatus($requestId, $levelId, $accountApprovalId, $module);
            $wanted = $decision === 'approve' ? 'approved' : 'rejected';

            if ($state !== null && !\in_array($state, [mb_strtolower($this->pendingStatus), 'to be sent'], true)) {
                if ($state !== $wanted) {
                    $removed = true;

                    try {
                        $client->deleteApproval($requestId, $levelId, $accountApprovalId, $module);
                    } catch (SdpException $e) {
                        $removed = false;
                        $this->logger->error(\sprintf(
                            'ServiceDesk Plus set the API technician\'s approval %s on %s %s to "%s" by itself although the user chose to %s; it could not be removed (%s). Fix it in ServiceDesk Plus and turn off the "Auto-approve" options under Admin > Advanced Portal Settings > Approval.',
                            $accountApprovalId,
                            $module,
                            $requestId,
                            $state,
                            $decision,
                            $e->getKind()
                        ));
                    }

                    $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'auto_decided:' . $state . ($removed ? ':removed' : ':left'));

                    throw new AutoDecided($removed);
                }

                $this->logger->info(\sprintf('ServiceDesk Plus already set approval %s on %s %s to "%s"', $accountApprovalId, $module, $requestId, $state));
            } else {
                $client->decideApproval($requestId, $levelId, $accountApprovalId, $decision, $sdpComment, $module);
            }
        } catch (SdpException $e) {
            if ($added) {
                // Do not leave an extra pending approver behind.
                try {
                    $client->deleteApproval($requestId, $levelId, $accountApprovalId, $module);
                } catch (SdpException) {
                    $this->logger->warning(\sprintf('Approval %s added for the approval account on request %s could not be removed', $accountApprovalId, $requestId));
                }
            }

            $this->recordDecision($userId, $action, AuditLogInterface::RESULT_ERROR, $requestId, $approvalId, 'account_decide:' . $e->getKind() . ':' . $e->getSdpMessage());

            throw $e;
        }

        // The decision is recorded. Remove the approver's own pending entries on this level (the one
        // decided on, and any duplicate entry for the same person) so only the decision remains.
        $originalRemoved = true;

        foreach ($this->ownPendingIds($identity, $requestId, $levelId, $approvalId, $module) as $ownId) {
            try {
                $client->deleteApproval($requestId, $levelId, $ownId, $module);
            } catch (SdpException $e) {
                // Fine when SDP already closed the entry itself (e.g. the level completed).
                $removed = $e->getKind() === SdpException::NOT_FOUND || !$this->approvalStillPending($requestId, $levelId, $ownId, $module);

                if (!$removed) {
                    $originalRemoved = false;
                    $this->logger->warning(\sprintf('Pending approval %s of %s %s could not be removed after the decision (%s)', $ownId, $module, $requestId, $e->getKind()));
                }
            }
        }

        $this->addDecisionNote($requestId, $decision, $approverName, $sdpComment, $module);
        $this->cache->clear($identity, 'approvals:');

        $outcome = match (true) {
            !$originalRemoved                              => self::OUTCOME_ORIGINAL_LEFT,
            $this->levelStillPending($requestId, $levelId, $module) => self::OUTCOME_LEVEL_PENDING,
            default                                        => self::OUTCOME_CONFIRMED,
        };

        $this->recordDecision($userId, $action, AuditLogInterface::RESULT_OK, $requestId, $approvalId, 'via_account:' . $accountApprovalId . ':' . $outcome);

        return $outcome;
    }

    /**
     * SDP user id of the approval account (exact e-mail match), or null when it is not found.
     */
    private function approvalAccountId(): ?string
    {
        if ($this->approvalAccountId !== null) {
            return $this->approvalAccountId !== '' ? $this->approvalAccountId : null;
        }

        $email = mb_strtolower(trim($this->approvalAccountEmail));
        $found = [];

        try {
            foreach ($this->client()->findUsersByEmail($email) as $user) {
                $id = SdpFields::string($user, 'id');

                if (mb_strtolower(trim(SdpFields::string($user, 'email_id'))) === $email && preg_match('/^[0-9]{1,20}$/', $id)) {
                    $found[$id] = true;
                }
            }
        } catch (SdpException | \InvalidArgumentException $e) {
            $this->logger->warning('The approval account could not be looked up: ' . ($e instanceof SdpException ? $e->getKind() : 'invalid e-mail'));

            return null;
        }

        if (\count($found) !== 1) {
            $this->logger->warning(\sprintf('The approval account e-mail matches %d ServiceDesk Plus users; it must match exactly one', \count($found)));
            $this->approvalAccountId = '';

            return null;
        }

        return $this->approvalAccountId = (string) array_key_first($found);
    }

    /** Private request note naming the person who decided. Best effort. */
    private function addDecisionNote(string $requestId, string $decision, string $approverName, string $sdpComment, string $module): void
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $approverName) ?? '') ?: 'the assigned approver';
        $text = \sprintf(
            '%s on behalf of %s via the Joomla portal on %s UTC. ServiceDesk Plus records the decision under the API technician because it only accepts approvals from that account. Comment: %s',
            $decision === 'approve' ? 'Approved' : 'Rejected',
            $name,
            gmdate('Y-m-d H:i'),
            $sdpComment
        );

        try {
            $this->client()->addModuleNote($module, $requestId, HtmlText::fromPlain($text));
        } catch (SdpException $e) {
            // Purchase orders have no notes; the approval comment names the approver.
            if ($module !== ApprovalModule::PURCHASE_ORDER) {
                $this->logger->warning(\sprintf('Note for the decision on %s %s could not be added (%s)', $module, $requestId, $e->getKind()));
            }
        }
    }

    /**
     * Ids of the user's pending entries on one level: the one decided on first, then any duplicate
     * entry for the same person (the same approver added twice). Falls back to the decided one.
     *
     * @return list<string>
     */
    private function ownPendingIds(Identity $identity, string $entityId, string $levelId, string $approvalId, string $module): array
    {
        $ids = [$approvalId];

        try {
            foreach ($this->client()->getApprovals($entityId, $levelId, $module) as $approval) {
                $id = SdpFields::string($approval, 'id');

                if ($id !== $approvalId && $this->isMine($approval, $identity->sdpUserId()) && $this->isPendingStatus($approval) && preg_match('/^[0-9]{1,20}$/', $id)) {
                    $ids[] = $id;
                }
            }
        } catch (SdpException) {
            // Only the decided entry then.
        }

        return $ids;
    }

    /**
     * One row per record and level: an approver added twice to the same level has two pending
     * entries in SDP, but one decision covers both.
     *
     * @param  list<PendingApproval>  $pending
     *
     * @return list<PendingApproval>
     */
    private static function onePerLevel(array $pending): array
    {
        $seen = [];
        $out  = [];

        foreach ($pending as $p) {
            $key = $p->module . ':' . $p->requestId . ':' . $p->levelId;

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[]      = $p;
            }
        }

        return $out;
    }

    /** Lower-case status name of one approval, or null when it cannot be read. */
    private function approvalStatus(string $requestId, string $levelId, string $approvalId, string $module): ?string
    {
        try {
            foreach ($this->client()->getApprovals($requestId, $levelId, $module) as $approval) {
                if (SdpFields::string($approval, 'id') === $approvalId) {
                    return mb_strtolower(SdpFields::name($approval, 'status'));
                }
            }
        } catch (SdpException) {
            // Unknown: the decision call below reports any problem.
        }

        return null;
    }

    /** True when the approval is still listed as pending; true as well when that cannot be read. */
    private function approvalStillPending(string $requestId, string $levelId, string $approvalId, string $module): bool
    {
        try {
            foreach ($this->client()->getApprovals($requestId, $levelId, $module) as $approval) {
                if (SdpFields::string($approval, 'id') === $approvalId) {
                    return $this->isPendingStatus($approval);
                }
            }

            return false;
        } catch (SdpException) {
            return true;
        }
    }

    /** True when the level is still waiting for approvals (e.g. it needs all approvers). */
    private function levelStillPending(string $requestId, string $levelId, string $module): bool
    {
        try {
            foreach ($this->client()->getApprovalLevels($requestId, $module) as $level) {
                if (SdpFields::string($level, 'id') === $levelId) {
                    return mb_strtolower(SdpFields::name($level, 'status')) === mb_strtolower($this->pendingStatus);
                }
            }
        } catch (SdpException) {
            // Unknown: report success; the user can check the request.
        }

        return false;
    }

    /**
     * Find the approval through the spec's endpoints and check its approver is the user.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: string, 3: string, 4: string}
     *
     * @throws ApprovalNotFound|SdpException
     */
    private function locateOwn(Identity $identity, string $requestId, string $levelId, string $approvalId, string $module = ApprovalModule::REQUEST): array
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

            foreach ($client->getApprovalLevels($requestId, $module) as $candidate) {
                if (SdpFields::string($candidate, 'id') === $levelId) {
                    $level = $candidate;
                    break;
                }
            }

            if ($level === null) {
                throw new ApprovalNotFound();
            }

            $approval = null;

            foreach ($client->getApprovals($requestId, $levelId, $module) as $candidate) {
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

    private function stillPending(Identity $identity, string $requestId, string $levelId, string $approvalId, string $module): ?bool
    {
        try {
            [$approval, $level] = $this->locateOwn($identity, $requestId, $levelId, $approvalId, $module);

            return $this->isPending($approval, $level);
        } catch (ApprovalNotFound | SdpException) {
            return null;
        }
    }

    /**
     * First non-empty text among the given fields; lookup objects ({id, name}) give their name.
     *
     * @param array<string, mixed> $entity
     * @param list<string>         $keys
     */
    private static function firstText(array $entity, array $keys, bool $lookup): string
    {
        foreach ($keys as $key) {
            $value = $lookup || \is_array($entity[$key] ?? null) ? SdpFields::name($entity, $key) : SdpFields::string($entity, $key);

            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * Closed or cancelled records keep a "pending" approval in SDP, but nobody can change their
     * approvers any more, so the portal cannot record a decision on them.
     *
     * @param array<string, mixed> $record
     */
    private static function isClosed(array $record): bool
    {
        return \in_array(mb_strtolower(SdpFields::name($record, 'status')), ['closed', 'cancelled', 'canceled'], true);
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
