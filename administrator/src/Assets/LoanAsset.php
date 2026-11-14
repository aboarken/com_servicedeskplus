<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Read-only loan asset as shown to its user.
 */
final class LoanAsset
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $product,
        public readonly string $serialNumber,
        public readonly string $state,
        public readonly ?int $loanStart,
        public readonly ?int $loanEnd
    ) {
    }

    /** @param array<string, mixed> $a */
    public static function fromSdp(array $a, LoanAssetConfig $config): self
    {
        $serial = AssetPath::text($a, 'org_serial_number');

        return new self(
            AssetPath::text($a, 'id'),
            AssetPath::text($a, 'name'),
            $config->productType($a),
            AssetPath::text($a, 'product.name'),
            $serial !== '' ? $serial : AssetPath::text($a, 'serial_number'),
            AssetPath::text($a, 'state.name'),
            AssetPath::timestamp($a, LoanAssetConfig::LOAN_START),
            AssetPath::timestamp($a, LoanAssetConfig::LOAN_END)
        );
    }

    /**
     * Still on loan (it is listed only while SDP marks it loaned) and past the loan end.
     */
    public function isOverdue(int $now): bool
    {
        return $this->loanEnd !== null && $this->loanEnd < $now;
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
