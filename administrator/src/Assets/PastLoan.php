<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * A returned loan of the user, from GET /api/v3/asset_loans: which assets, when they were lent,
 * when they were due and when they came back. Plain values only (it is cached).
 */
final class PastLoan
{
    /**
     * @param  list<string>  $assets  asset names
     */
    public function __construct(
        public readonly string $id,
        public readonly string $number,
        public readonly array $assets,
        public readonly ?int $start,
        public readonly ?int $due,
        public readonly ?int $returned,
        public readonly string $comments
    ) {
    }

    /** Most asset names kept per loan. */
    private const MAX_ASSETS = 50;

    /** @param array<string, mixed> $loan */
    public static function fromSdp(array $loan): self
    {
        $names = [];

        foreach (\array_slice(array_filter((array) ($loan['assets'] ?? []), 'is_array'), 0, self::MAX_ASSETS) as $asset) {
            // Each entry is the asset itself, or {asset: {...}}.
            $inner = \is_array($asset['asset'] ?? null) ? $asset['asset'] : $asset;
            $name  = SdpFields::name($inner, 'name') ?: SdpFields::string($inner, 'id');

            if ($name !== '') {
                $names[] = mb_substr($name, 0, 200);
            }
        }

        return new self(
            SdpFields::string($loan, 'id'),
            mb_substr(SdpFields::string($loan, 'custom_id') ?: SdpFields::string($loan, 'display_id'), 0, 40),
            $names,
            SdpFields::timestamp($loan, 'start_time'),
            SdpFields::timestamp($loan, 'extended_to') ?? SdpFields::timestamp($loan, 'end_time'),
            SdpFields::timestamp($loan, 'returned_on'),
            mb_substr(HtmlText::toPlain(SdpFields::string($loan, 'comments')), 0, 1000)
        );
    }

    /**
     * A loan that is over: returned, or no longer "On Loan" in ServiceDesk Plus.
     *
     * @param array<string, mixed> $loan
     */
    public static function isOver(array $loan): bool
    {
        $status = mb_strtolower(SdpFields::name($loan, 'status'));

        return SdpFields::timestamp($loan, 'returned_on') !== null || ($status !== '' && $status !== 'on loan');
    }

    /** Asset names as one line. */
    public function assetNames(): string
    {
        return implode(', ', $this->assets);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        $ts = static fn ($v): ?int => \is_int($v) ? $v : null;

        return new self(
            (string) ($a['id'] ?? ''),
            (string) ($a['number'] ?? ''),
            array_values(array_map('strval', (array) ($a['assets'] ?? []))),
            $ts($a['start'] ?? null),
            $ts($a['due'] ?? null),
            $ts($a['returned'] ?? null),
            (string) ($a['comments'] ?? '')
        );
    }
}
