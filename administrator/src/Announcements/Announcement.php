<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * One ServiceDesk Plus announcement as the portal shows it (content as plain text).
 */
final class Announcement
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $content,
        public readonly string $type,
        public readonly string $priority,
        public readonly ?int $fromTime,
        public readonly ?int $toTime,
        public readonly ?int $createdTime,
        public readonly string $createdBy = ''
    ) {
    }

    /**
     * @param  array<string, mixed>  $row  an "announcement" object of the v3 API
     */
    public static function fromSdp(array $row): self
    {
        return new self(
            SdpFields::string($row, 'id'),
            trim(SdpFields::string($row, 'title')),
            HtmlText::toPlain(SdpFields::string($row, 'content')),
            SdpFields::name($row, 'announcement_type'),
            SdpFields::name($row, 'priority'),
            SdpFields::timestamp($row, 'from_date'),
            SdpFields::timestamp($row, 'to_date'),
            SdpFields::timestamp($row, 'created_date'),
            SdpFields::name($row, 'created_by')
        );
    }

    /**
     * Shown from: the start of the display period, otherwise the creation date.
     */
    public function date(): ?int
    {
        return $this->fromTime ?? $this->createdTime;
    }

    public function excerpt(int $length = 200): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $this->content));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
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
            (string) ($a['id'] ?? ''),
            (string) ($a['title'] ?? ''),
            (string) ($a['content'] ?? ''),
            (string) ($a['type'] ?? ''),
            (string) ($a['priority'] ?? ''),
            isset($a['fromTime']) ? (int) $a['fromTime'] : null,
            isset($a['toTime']) ? (int) $a['toTime'] : null,
            isset($a['createdTime']) ? (int) $a['createdTime'] : null,
            (string) ($a['createdBy'] ?? '')
        );
    }
}
