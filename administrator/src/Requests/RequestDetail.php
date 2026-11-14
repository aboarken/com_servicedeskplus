<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * Read-only request detail. Description and resolution are plain text.
 */
final class RequestDetail
{
    public function __construct(
        public readonly string $id,
        public readonly string $subject,
        public readonly string $description,
        public readonly string $status,
        public readonly string $priority,
        public readonly string $technician,
        public readonly string $category,
        public readonly string $resolution,
        public readonly ?int $createdAt,
        public readonly ?int $updatedAt,
        public readonly ?int $dueAt,
        public readonly ?int $resolvedAt,
        public readonly ?int $completedAt
    ) {
    }

    /** @param array<string, mixed> $r */
    public static function fromSdp(array $r, string $resolutionText): self
    {
        $created = SdpFields::timestamp($r, 'created_time');

        return new self(
            SdpFields::string($r, 'id'),
            SdpFields::string($r, 'subject'),
            HtmlText::toPlain(SdpFields::string($r, 'description')),
            SdpFields::name($r, 'status'),
            SdpFields::name($r, 'priority'),
            SdpFields::name($r, 'technician'),
            SdpFields::name($r, 'category'),
            $resolutionText,
            $created,
            RequestSummary::updatedTime($r, $created),
            SdpFields::timestamp($r, 'due_by_time'),
            SdpFields::timestamp($r, 'resolved_time'),
            SdpFields::timestamp($r, 'completed_time')
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(...$a);
    }
}
