<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * How loan assets are read from SDP. Fixed to the fields SDP build 16030 returns from
 * GET /api/v3/assets (confirmed with "Test asset endpoint"): user, is_loaned, loan_start, loan_end.
 */
final class LoanAssetConfig
{
    public const USER_FIELD  = 'user.id';
    public const LOAN_FLAG   = 'is_loaned';
    public const LOAN_START  = 'loan_start';
    public const LOAN_END    = 'loan_end';

    /** Product type: top level on this build, under product on others. */
    private const PRODUCT_TYPE_PATHS = ['product_type.name', 'product.product_type.name'];

    /** @param array<string, mixed> $asset */
    public function isAssignedTo(array $asset, string $sdpUserId): bool
    {
        $owner = AssetPath::get($asset, self::USER_FIELD);

        return $sdpUserId !== '' && \is_scalar($owner) && (string) $owner === $sdpUserId;
    }

    /**
     * SDP marks loaned assets with is_loaned. A returned asset is no longer loaned.
     *
     * @param array<string, mixed> $asset
     */
    public function isLoan(array $asset): bool
    {
        $flag = AssetPath::get($asset, self::LOAN_FLAG);

        return \is_bool($flag) ? $flag : filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    }

    /** @param array<string, mixed> $asset */
    public function productType(array $asset): string
    {
        foreach (self::PRODUCT_TYPE_PATHS as $path) {
            $value = AssetPath::text($asset, $path);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Server-side search criterion for the assigned user (results are re-checked in PHP).
     *
     * @return array<string, string>
     */
    public function userCriterion(string $sdpUserId): array
    {
        return ['field' => self::USER_FIELD, 'condition' => 'is', 'value' => $sdpUserId];
    }
}
