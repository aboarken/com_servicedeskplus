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
 * A returned loan of the user, from GET /api/v3/asset_loans: which assets (name, type, model,
 * serial number), when they were lent, when they were due and when they came back. Plain values
 * only (it is cached).
 */
final class PastLoan
{
    /**
     * @param  list<array{id: string, name: string, type: string, model: string, serial: string}>  $assets
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

    /** Most assets kept per loan. */
    private const MAX_ASSETS = 50;

    /** @param array<string, mixed> $loan */
    public static function fromSdp(array $loan): self
    {
        $assets = [];

        foreach (\array_slice(array_filter((array) ($loan['assets'] ?? []), 'is_array'), 0, self::MAX_ASSETS) as $asset) {
            // Each entry is the asset itself, or {asset: {...}}.
            $inner = \is_array($asset['asset'] ?? null) ? $asset['asset'] : $asset;
            $row   = self::assetRow($inner);

            if ($row['name'] !== '' || $row['id'] !== '') {
                $assets[] = $row;
            }
        }

        return new self(
            SdpFields::string($loan, 'id'),
            mb_substr(SdpFields::string($loan, 'custom_id') ?: SdpFields::string($loan, 'display_id'), 0, 40),
            $assets,
            SdpFields::timestamp($loan, 'start_time'),
            SdpFields::timestamp($loan, 'extended_to') ?? SdpFields::timestamp($loan, 'end_time'),
            SdpFields::timestamp($loan, 'returned_on'),
            mb_substr(HtmlText::toPlain(SdpFields::string($loan, 'comments')), 0, 1000)
        );
    }

    /**
     * Name, type, model and serial number of an asset (from the loan, or from the asset itself).
     *
     * @param  array<string, mixed>  $asset
     *
     * @return array{id: string, name: string, type: string, model: string, serial: string}
     */
    public static function assetRow(array $asset): array
    {
        $id     = SdpFields::string($asset, 'id');
        $serial = AssetPath::text($asset, 'org_serial_number');

        return [
            'id'     => preg_match('/^[0-9]{1,20}$/', $id) ? $id : '',
            'name'   => mb_substr(AssetPath::text($asset, 'name'), 0, 200),
            'type'   => mb_substr((new LoanAssetConfig())->productType($asset), 0, 100),
            'model'  => mb_substr(AssetPath::text($asset, 'product.name'), 0, 200),
            'serial' => mb_substr($serial !== '' ? $serial : AssetPath::text($asset, 'serial_number'), 0, 100),
        ];
    }

    /**
     * A copy with the details read from the assets themselves filled in where the loan had none.
     *
     * @param  array<string, array{id: string, name: string, type: string, model: string, serial: string}>  $details  asset id => row
     */
    public function withDetails(array $details): self
    {
        $assets = array_map(static function (array $row) use ($details): array {
            $more = $details[$row['id']] ?? null;

            if ($more === null) {
                return $row;
            }

            foreach (['name', 'type', 'model', 'serial'] as $key) {
                if ($row[$key] === '') {
                    $row[$key] = $more[$key];
                }
            }

            return $row;
        }, $this->assets);

        return new self($this->id, $this->number, $assets, $this->start, $this->due, $this->returned, $this->comments);
    }

    /**
     * Ids of assets that still lack a type, model or serial number.
     *
     * @return list<string>
     */
    public function idsWithoutDetails(): array
    {
        return array_values(array_map(
            static fn (array $row): string => $row['id'],
            array_filter($this->assets, static fn (array $row): bool => $row['id'] !== '' && ($row['type'] === '' || $row['model'] === '' || $row['serial'] === ''))
        ));
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

    /** Asset names and details as one line (search and sort). */
    public function assetNames(): string
    {
        return implode(', ', array_map(static fn (array $a): string => trim($a['name'] . ' ' . $a['type'] . ' ' . $a['model'] . ' ' . $a['serial']), $this->assets));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        $ts  = static fn ($v): ?int => \is_int($v) ? $v : null;
        $row = static fn ($r): array => [
            'id'     => (string) ($r['id'] ?? ''),
            'name'   => (string) ($r['name'] ?? ''),
            'type'   => (string) ($r['type'] ?? ''),
            'model'  => (string) ($r['model'] ?? ''),
            'serial' => (string) ($r['serial'] ?? ''),
        ];

        return new self(
            (string) ($a['id'] ?? ''),
            (string) ($a['number'] ?? ''),
            array_values(array_map($row, array_filter((array) ($a['assets'] ?? []), 'is_array'))),
            $ts($a['start'] ?? null),
            $ts($a['due'] ?? null),
            $ts($a['returned'] ?? null),
            (string) ($a['comments'] ?? '')
        );
    }
}
