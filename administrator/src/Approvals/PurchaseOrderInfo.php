<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\HtmlText;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Support\SdpFields;

/**
 * What an approver needs to see of a purchase order: who and what it is for, the items and the
 * amounts, as plain text taken from GET /api/v3/purchase_orders/{id}.
 */
final class PurchaseOrderInfo
{
    /** Most items listed. */
    public const MAX_ITEMS = 100;

    /**
     * @param  list<array{0: string, 1: string}>  $fields  language key of the label, value (empty ones left out)
     * @param  list<array{name: string, description: string, quantity: string, price: string, total: string}>  $items
     * @param  list<array{0: string, 1: string}>  $totals  language key of the label, amount
     * @param  string  $currency  symbol or code, '' when unknown
     */
    public function __construct(
        public readonly array $fields,
        public readonly array $items,
        public readonly array $totals,
        public readonly string $currency
    ) {
    }

    /** @param array<string, mixed> $po */
    public static function fromSdp(array $po): self
    {
        $currency = \is_array($po['currency'] ?? null) ? $po['currency'] : [];
        $symbol   = mb_substr(SdpFields::string($currency, 'symbol') ?: SdpFields::string($currency, 'code'), 0, 10);
        $text     = static fn (string $key): string => mb_substr(HtmlText::toPlain(SdpFields::name($po, $key)), 0, 2000);
        $date     = static function (string $key) use ($po): string {
            $value = $po[$key] ?? null;

            return \is_array($value) && \is_scalar($value['display_value'] ?? null) ? mb_substr(trim((string) $value['display_value']), 0, 60) : '';
        };

        $fields = array_values(array_filter([
            ['COM_SERVICEDESKPLUS_PO_NUMBER', $text('custom_po_id')],
            ['COM_SERVICEDESKPLUS_PO_VENDOR', $text('vendor')],
            ['COM_SERVICEDESKPLUS_PO_REQUESTED_BY', $text('requested_by')],
            ['COM_SERVICEDESKPLUS_PO_OWNER', $text('owner')],
            ['COM_SERVICEDESKPLUS_PO_CREATED', $date('created_date')],
            ['COM_SERVICEDESKPLUS_PO_REQUIRED', $date('required_date')],
            ['COM_SERVICEDESKPLUS_PO_COST_CENTER', $text('cost_center')],
            ['COM_SERVICEDESKPLUS_PO_GL_CODE', $text('gl_code')],
            ['COM_SERVICEDESKPLUS_PO_SHIPPING_ADDRESS', $text('shipping_address')],
            ['COM_SERVICEDESKPLUS_PO_TERMS', $text('terms')],
        ], static fn (array $f): bool => $f[1] !== ''));

        $items = [];

        foreach (\array_slice(array_filter((array) ($po['items'] ?? []), 'is_array'), 0, self::MAX_ITEMS) as $item) {
            $quantity = self::number($item['ordered_quantity'] ?? null);
            $price    = self::number($item['price'] ?? null);
            $name     = SdpFields::string($item, 'item_name') ?: SdpFields::name($item, 'product') ?: SdpFields::name($item, 'service');

            $items[] = [
                'name'        => mb_substr(HtmlText::toPlain($name), 0, 200),
                'description' => mb_substr(HtmlText::toPlain(SdpFields::string($item, 'description')), 0, 500),
                'quantity'    => $quantity === null ? '' : self::format($quantity, 0),
                'price'       => $price === null ? '' : self::format($price),
                'total'       => $quantity === null || $price === null ? '' : self::format($quantity * $price),
            ];
        }

        $totals = [];

        foreach (
            [
                'sub_total'        => ['COM_SERVICEDESKPLUS_PO_SUB_TOTAL', true],
                'discount'         => ['COM_SERVICEDESKPLUS_PO_DISCOUNT', false],
                'sales_tax'        => ['COM_SERVICEDESKPLUS_PO_SALES_TAX', false],
                'additional_tax'   => ['COM_SERVICEDESKPLUS_PO_ADDITIONAL_TAX', false],
                'shipping_price'   => ['COM_SERVICEDESKPLUS_PO_SHIPPING', false],
                'price_adjustment' => ['COM_SERVICEDESKPLUS_PO_ADJUSTMENT', false],
                'total_price'      => ['COM_SERVICEDESKPLUS_PO_TOTAL', true],
            ] as $key => [$label, $always]
        ) {
            $amount = self::number($po[$key] ?? null);

            // Zero discount, tax, shipping and adjustment are left out.
            if ($amount !== null && ($always || abs($amount) > 0.000001)) {
                $totals[] = [$label, self::format($amount)];
            }
        }

        return new self($fields, $items, $totals, $symbol);
    }

    private static function number(mixed $value): ?float
    {
        return \is_scalar($value) && is_numeric($value) ? (float) $value : null;
    }

    /** 1,234.50; whole quantities without decimals. */
    private static function format(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals === 0 && abs($value - round($value)) > 0.000001 ? 2 : $decimals);
    }
}
