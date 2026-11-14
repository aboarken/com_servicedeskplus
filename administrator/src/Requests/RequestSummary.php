<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * One row of "My requests". Plain values only; the template escapes them.
 */
final class RequestSummary
{
    public function __construct(
        public readonly string $id,
        public readonly string $subject,
        public readonly string $status,
        public readonly string $priority,
        public readonly string $technician,
        public readonly ?int $createdAt,
        public readonly ?int $updatedAt
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromSdp(array $r): self
    {
        $created = SdpFields::timestamp($r, 'created_time');

        return new self(
            SdpFields::string($r, 'id'),
            SdpFields::string($r, 'subject'),
            SdpFields::name($r, 'status'),
            SdpFields::name($r, 'priority'),
            SdpFields::name($r, 'technician'),
            $created,
            self::updatedTime($r, $created)
        );
    }

    /**
     * SDP leaves last_updated_time empty until a request is changed (and some list responses omit
     * it). A request that was never updated was last updated when it was created.
     *
     * @param array<string, mixed> $r
     */
    public static function updatedTime(array $r, ?int $created): ?int
    {
        return SdpFields::timestamp($r, 'last_updated_time')
            ?? SdpFields::timestamp($r, 'updated_time')
            ?? $created;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            (string) $a['id'],
            (string) $a['subject'],
            (string) $a['status'],
            (string) $a['priority'],
            (string) $a['technician'],
            $a['createdAt'] ?? null,
            $a['updatedAt'] ?? null
        );
    }
}
