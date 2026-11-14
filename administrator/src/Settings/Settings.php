<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Settings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals\ApprovalModule;

/**
 * Immutable view of the component settings stored in #__sdp_settings.
 */
final class Settings
{
    public const DEFAULTS = [
        'base_url'             => '',
        'service_key_enc'      => '',
        'timeout'              => '20',
        'connect_timeout'      => '5',
        'verify_ssl'           => '1',
        'ca_bundle'            => '',
        'ssl_pin'              => '',
        'connect_via'          => '',
        'cache_ttl'            => '60',
        'rate_create_per_hour' => '10',
        'rate_decide_per_hour' => '30',
        'request_statuses'     => 'Open,On Hold,Resolved,Closed',
        // E-mail of the SDP technician that owns the service key; empty = no approval fallback.
        'approval_account_email' => '',
        // Modules whose approvals My Approvals checks.
        'approval_modules'       => 'request,change,release,purchase_order',
    ];

    /** @param array<string, string> $values */
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $name): string
    {
        return $this->values[$name] ?? self::DEFAULTS[$name] ?? '';
    }

    public function baseUrl(): string
    {
        return $this->get('base_url');
    }

    public function serviceKeyCiphertext(): string
    {
        return $this->get('service_key_enc');
    }

    public function hasServiceKey(): bool
    {
        return $this->serviceKeyCiphertext() !== '';
    }

    public function timeout(): int
    {
        return max(1, min(300, (int) $this->get('timeout')));
    }

    public function connectTimeout(): int
    {
        return max(1, min($this->timeout(), (int) $this->get('connect_timeout')));
    }

    public function verifySsl(): bool
    {
        return $this->get('verify_ssl') === '1';
    }

    public function caBundle(): string
    {
        return $this->get('ca_bundle');
    }

    public function sslPin(): string
    {
        return $this->get('ssl_pin');
    }

    public function connectVia(): string
    {
        return $this->get('connect_via');
    }

    public function cacheTtl(): int
    {
        return max(0, min(600, (int) $this->get('cache_ttl')));
    }

    public function rateCreatePerHour(): int
    {
        return max(1, (int) $this->get('rate_create_per_hour'));
    }

    /**
     * E-mail address of the technician that owns the service key, used to approve on behalf of
     * the assigned approver when SDP refuses a direct decision. Empty when not configured.
     */
    public function approvalAccountEmail(): string
    {
        return $this->get('approval_account_email');
    }

    /**
     * Modules whose approvals My Approvals checks, in display order. Each one costs extra
     * ServiceDesk Plus calls when the list is built.
     *
     * @return list<string>
     */
    public function approvalModules(): array
    {
        $chosen = array_map(
            static fn (string $m): ?string => ApprovalModule::normalize($m),
            self::parseList($this->get('approval_modules'))
        );

        return array_values(array_filter(ApprovalModule::ALL, static fn (string $m): bool => \in_array($m, $chosen, true)));
    }

    public function rateDecidePerHour(): int
    {
        return max(1, (int) $this->get('rate_decide_per_hour'));
    }

    /**
     * Status names offered in the "My requests" filter (as named in SDP).
     *
     * @return list<string>
     */
    public function requestStatuses(): array
    {
        return self::parseList($this->get('request_statuses'));
    }

    /**
     * Split a comma or newline separated list; trims, drops empties and duplicates.
     *
     * @return list<string>
     */
    public static function parseList(string $value, int $maxItems = 30, int $maxLength = 100): array
    {
        $items = [];

        foreach (preg_split('/[,\n]/', $value) ?: [] as $item) {
            $item = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $item) ?? '');

            if ($item !== '' && mb_strlen($item) <= $maxLength && !\in_array($item, $items, true)) {
                $items[] = $item;
            }
        }

        return \array_slice($items, 0, $maxItems);
    }

    /**
     * Values safe to show in a form: the encrypted key is never included.
     *
     * @return array<string, string>
     */
    public function toFormData(): array
    {
        $data = array_merge(self::DEFAULTS, $this->values);
        unset($data['service_key_enc']);

        // Checkboxes field: a list of values.
        $data['approval_modules'] = $this->approvalModules();

        return $data;
    }

    public function __debugInfo(): array
    {
        return $this->toFormData() + ['service_key_enc' => $this->hasServiceKey() ? '[set]' : ''];
    }
}
