<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The SDP modules whose approvals the portal shows, and how their v3 APIs differ.
 *
 * Sources: the ServiceDesk Plus On-Premises Postman collection published by ManageEngine
 * (folders Request/Approvals, Change_Approval, Release_Approval, PO_Approval_Level) and the
 * On-Premises v3 reference (sdpop-v3-api). Problems have no approvals in ServiceDesk Plus.
 *
 * Where builds differ ("approve" or "_approve"), SdpClient tries the listed forms in order and
 * moves on only when SDP answers "not found".
 */
final class ApprovalModule
{
    public const REQUEST        = 'request';
    public const CHANGE         = 'change';
    public const RELEASE        = 'release';
    public const PURCHASE_ORDER = 'purchase_order';

    public const ALL = [self::REQUEST, self::CHANGE, self::RELEASE, self::PURCHASE_ORDER];

    /** module => [API collection, entity key in responses, language key] */
    private const DEFS = [
        self::REQUEST        => ['requests', 'request', 'COM_SERVICEDESKPLUS_MODULE_REQUEST'],
        self::CHANGE         => ['changes', 'change', 'COM_SERVICEDESKPLUS_MODULE_CHANGE'],
        self::RELEASE        => ['releases', 'release', 'COM_SERVICEDESKPLUS_MODULE_RELEASE'],
        self::PURCHASE_ORDER => ['purchase_orders', 'purchase_order', 'COM_SERVICEDESKPLUS_MODULE_PURCHASE_ORDER'],
    ];

    /**
     * Normalise a module name from a URL, a form or SDP's "associated_entity".
     * Unknown values give null; an empty value means a request (links from older versions).
     */
    public static function normalize(?string $module): ?string
    {
        $module = strtolower(trim((string) $module));
        $module = match ($module) {
            ''                                           => self::REQUEST,
            'requests'                                   => self::REQUEST,
            'changes'                                    => self::CHANGE,
            'releases'                                   => self::RELEASE,
            'purchase_orders', 'purchaseorder', 'po'     => self::PURCHASE_ORDER,
            default                                      => $module,
        };

        return isset(self::DEFS[$module]) ? $module : null;
    }

    public static function collection(string $module): string
    {
        return self::DEFS[self::require($module)][0];
    }

    public static function entityKey(string $module): string
    {
        return self::DEFS[self::require($module)][1];
    }

    public static function labelKey(string $module): string
    {
        return self::DEFS[self::require($module)][2];
    }

    /**
     * Last path segment for a decision, in the order to try ("approve" works on the On-Premises
     * request and change APIs, "_approve" is documented for releases and purchase orders).
     *
     * @param  'approve'|'reject'  $decision
     *
     * @return list<string>
     */
    public static function decisionSegments(string $module, string $decision): array
    {
        $plain = $decision;
        $under = '_' . $decision;

        return \in_array(self::require($module), [self::REQUEST, self::CHANGE], true) ? [$plain, $under] : [$under, $plain];
    }

    /**
     * input_data for adding one approver to a level, in the order to try. Requests take a single
     * "approval" object (confirmed on build 16030); the other modules document an "approvals" list.
     *
     * @return list<array<string, mixed>>
     */
    public static function addApprovalInputs(string $module, string $approverId): array
    {
        $single = ['approval' => ['approver' => ['id' => $approverId]]];
        $list   = ['approvals' => [['approver' => ['id' => $approverId]]]];

        return self::require($module) === self::REQUEST ? [$single, $list] : [$list, $single];
    }

    /**
     * Level-wide "send approval e-mail" segment, in the order to try.
     *
     * @return list<string>
     */
    public static function notificationSegments(string $module): array
    {
        return \in_array(self::require($module), [self::REQUEST, self::CHANGE], true)
            ? ['send_notification', '_send_notification']
            : ['_send_notification', 'send_notification'];
    }

    /**
     * Fields that may hold the title, description and requester of the parent record.
     *
     * @return array{title: list<string>, description: list<string>, requester: list<string>}
     */
    public static function fields(string $module): array
    {
        return match (self::require($module)) {
            self::REQUEST        => ['title' => ['subject'], 'description' => ['description'], 'requester' => ['requester']],
            self::CHANGE         => ['title' => ['title', 'subject'], 'description' => ['description'], 'requester' => ['change_requester', 'requester', 'change_owner']],
            self::RELEASE        => ['title' => ['title', 'subject'], 'description' => ['description'], 'requester' => ['release_requester', 'requested_by', 'requester']],
            self::PURCHASE_ORDER => ['title' => ['name', 'title', 'custom_po_id'], 'description' => ['comments', 'description'], 'requester' => ['requested_by', 'owner']],
        };
    }

    private static function require(string $module): string
    {
        if (!isset(self::DEFS[$module])) {
            throw new \InvalidArgumentException('Unknown approval module');
        }

        return $module;
    }
}
