<?php

namespace App\Services;

use InvalidArgumentException;

class PriceCalculationService
{
    public function calculateBaseSellingPrice(
        float $cost,
        string $marginType,
        float $marginValue,
    ): float {
        $price = match ($marginType) {
            'nominal' => $cost + $marginValue,
            'percentage' => $cost * (1 + $marginValue / 100),
            default => throw new InvalidArgumentException("Tipe margin tidak valid: {$marginType}"),
        };

        return $this->roundMoney($price);
    }

    public function calculateBazarSellingPrice(
        float $basePrice,
        string $adjustType,
        float $adjustValue,
    ): float {
        $price = match ($adjustType) {
            'none' => $basePrice,
            'nominal' => $basePrice + $adjustValue,
            'percentage' => $basePrice * (1 + $adjustValue / 100),
            'manual' => $adjustValue,
            default => throw new InvalidArgumentException("Tipe penyesuaian bazar tidak valid: {$adjustType}"),
        };

        return $this->roundMoney($price);
    }

    public function calculateItemDiscount(
        float $price,
        int $qty,
        string $discountType,
        float $discountValue,
    ): float {
        $lineSubtotal = $price * $qty;

        $discount = match ($discountType) {
            'none' => 0.0,
            'nominal' => $discountValue,
            'percentage' => $lineSubtotal * ($discountValue / 100),
            default => throw new InvalidArgumentException("Tipe diskon item tidak valid: {$discountType}"),
        };

        $discount = min($discount, $lineSubtotal);

        return $this->roundMoney($discount);
    }

    public function calculateTransactionDiscount(
        float $subtotalAfterItemDiscounts,
        string $discountType,
        float $discountValue,
    ): float {
        $discount = match ($discountType) {
            'none' => 0.0,
            'nominal' => min($discountValue, $subtotalAfterItemDiscounts),
            'percentage' => $subtotalAfterItemDiscounts * ($discountValue / 100),
            default => throw new InvalidArgumentException("Tipe diskon transaksi tidak valid: {$discountType}"),
        };

        return $this->roundMoney($discount);
    }

    public function calculateGrossProfit(
        float $totalAfterDiscount,
        float $supplierCostSnapshot,
        int $qty,
    ): float {
        return $this->roundMoney($totalAfterDiscount - ($supplierCostSnapshot * $qty));
    }

    /**
     * @param  array{
     *   selling_price: float,
     *   qty: int,
     *   item_discount_type: string,
     *   item_discount_value: float,
     *   supplier_cost_snapshot: float
     * }  $item
     * @return array{
     *   subtotal: float,
     *   item_discount_amount: float,
     *   total_after_discount: float,
     *   gross_profit: float
     * }
     */
    public function calculateSalesItemTotals(array $item): array
    {
        $subtotal = $this->roundMoney($item['selling_price'] * $item['qty']);

        $itemDiscountAmount = $this->calculateItemDiscount(
            $item['selling_price'],
            $item['qty'],
            $item['item_discount_type'],
            $item['item_discount_value'],
        );

        $totalAfterDiscount = $this->roundMoney($subtotal - $itemDiscountAmount);

        $grossProfit = $this->calculateGrossProfit(
            $totalAfterDiscount,
            $item['supplier_cost_snapshot'],
            $item['qty'],
        );

        return [
            'subtotal' => $subtotal,
            'item_discount_amount' => $itemDiscountAmount,
            'total_after_discount' => $totalAfterDiscount,
            'gross_profit' => $grossProfit,
        ];
    }

    /**
     * @param  list<array{
     *   subtotal: float,
     *   item_discount_amount: float,
     *   total_after_discount: float,
     *   gross_profit: float
     * }>  $items
     * @return array{
     *   subtotal_amount: float,
     *   item_discount_amount: float,
     *   total_after_item_discount: float,
     *   transaction_discount_amount: float,
     *   grand_total: float
     * }
     */
    public function calculateTransactionTotals(
        array $items,
        string $transactionDiscountType,
        float $transactionDiscountValue,
    ): array {
        $subtotalAmount = $this->roundMoney(array_sum(array_column($items, 'subtotal')));
        $itemDiscountAmount = $this->roundMoney(array_sum(array_column($items, 'item_discount_amount')));
        $totalAfterItemDiscount = $this->roundMoney(array_sum(array_column($items, 'total_after_discount')));

        $transactionDiscountAmount = $this->calculateTransactionDiscount(
            $totalAfterItemDiscount,
            $transactionDiscountType,
            $transactionDiscountValue,
        );

        $grandTotal = $this->roundMoney($totalAfterItemDiscount - $transactionDiscountAmount);

        return [
            'subtotal_amount' => $subtotalAmount,
            'item_discount_amount' => $itemDiscountAmount,
            'total_after_item_discount' => $totalAfterItemDiscount,
            'transaction_discount_amount' => $transactionDiscountAmount,
            'grand_total' => $grandTotal,
        ];
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
