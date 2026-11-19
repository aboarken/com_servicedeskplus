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
        // Minutes list results are kept per user; empty = DEFAULT_CACHE_MINUTES (or the old cache_ttl).
        'cache_minutes'        => '',
        // Seconds; setting of versions before 1.15.0, read only until cache_minutes is saved.
        'cache_ttl'            => '',
        // ServiceDesk Plus reads sent at once where the portal reads many records (approval scan).
        'parallel_requests'    => '6',
        'rate_create_per_hour' => '10',
        'rate_decide_per_hour' => '30',
        'request_statuses'     => 'Open,On Hold,Resolved,Closed',
        // E-mail of the SDP technician that owns the service key; empty = no approval fallback.
        'approval_account_email' => '',
        // Modules whose approvals My Approvals checks.
        'approval_modules'       => 'request,change,release,purchase_order',
        // New users found by e-mail: 1 = approved at once, 0 = wait for an administrator.
        'auto_approve_matches'   => '1',
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

    public const DEFAULT_CACHE_MINUTES = 5;

    public const MAX_CACHE_MINUTES = 60;

    /**
     * Minutes list results are cached per user (0 = no cache). Sites updated from a version that
     * stored seconds keep their value, rounded up to whole minutes, until the settings are saved.
     */
    public function cacheMinutes(): int
    {
        $minutes = $this->get('cache_minutes');

        if ($minutes === '' && $this->get('cache_ttl') !== '') {
            $minutes = (string) (int) ceil(max(0, (int) $this->get('cache_ttl')) / 60);
        }

        return $minutes === '' ? self::DEFAULT_CACHE_MINUTES : max(0, min(self::MAX_CACHE_MINUTES, (int) $minutes));
    }

    /** Cache time in seconds, for the cache store. */
    public function cacheTtl(): int
    {
        return $this->cacheMinutes() * 60;
    }

    public const MAX_PARALLEL_REQUESTS = 10;

    /** Reads sent to ServiceDesk Plus at once (1 = one after another). */
    public function parallelRequests(): int
    {
        return max(1, min(self::MAX_PARALLEL_REQUESTS, (int) $this->get('parallel_requests')));
    }

    public function rateCreatePerHour(): int
    {
        return max(1, (int) $this->get('rate_create_per_hour'));
    }

    /**
     * Whether a new user found by e-mail is matched at once (true) or waits for an administrator
     * in User Mappings (false). A changed e-mail always waits.
     */
    public function autoApproveMatches(): bool
    {
        return $this->get('auto_approve_matches') !== '0';
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

        $data['cache_minutes'] = (string) $this->cacheMinutes();
        unset($data['cache_ttl']);

        // Checkboxes field: a list of values.
        $data['approval_modules'] = $this->approvalModules();

        return $data;
    }

    public function __debugInfo(): array
    {
        return $this->toFormData() + ['service_key_enc' => $this->hasServiceKey() ? '[set]' : ''];
    }
}
