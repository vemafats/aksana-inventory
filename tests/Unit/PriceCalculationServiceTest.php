<?php

namespace Tests\Unit;

use App\Services\PriceCalculationService;
use InvalidArgumentException;
use Tests\TestCase;

class PriceCalculationServiceTest extends TestCase
{
    private PriceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PriceCalculationService;
    }

    public function test_calculate_base_price_nominal_margin(): void
    {
        $result = $this->service->calculateBaseSellingPrice(100000, 'nominal', 50000);

        $this->assertSame(150000.0, $result);
    }

    public function test_calculate_base_price_percentage_margin(): void
    {
        $result = $this->service->calculateBaseSellingPrice(100000, 'percentage', 40);

        $this->assertSame(140000.0, $result);
    }

    public function test_calculate_bazar_price_none_adjustment(): void
    {
        $result = $this->service->calculateBazarSellingPrice(150000, 'none', 0);

        $this->assertSame(150000.0, $result);
    }

    public function test_calculate_bazar_price_nominal_adjustment(): void
    {
        $result = $this->service->calculateBazarSellingPrice(150000, 'nominal', 20000);

        $this->assertSame(170000.0, $result);
    }

    public function test_calculate_bazar_price_percentage_adjustment(): void
    {
        $result = $this->service->calculateBazarSellingPrice(150000, 'percentage', 10);

        $this->assertSame(165000.0, $result);
    }

    public function test_calculate_bazar_price_manual_override(): void
    {
        $result = $this->service->calculateBazarSellingPrice(150000, 'manual', 200000);

        $this->assertSame(200000.0, $result);
    }

    public function test_item_discount_none(): void
    {
        $discount = $this->service->calculateItemDiscount(100000, 2, 'none', 0);

        $this->assertSame(0.0, $discount);
    }

    public function test_item_discount_nominal(): void
    {
        $discount = $this->service->calculateItemDiscount(100000, 2, 'nominal', 30000);

        $this->assertSame(30000.0, $discount);
    }

    public function test_item_discount_percentage(): void
    {
        $discount = $this->service->calculateItemDiscount(100000, 2, 'percentage', 10);

        $this->assertSame(20000.0, $discount);
    }

    public function test_item_discount_cannot_exceed_subtotal(): void
    {
        $discount = $this->service->calculateItemDiscount(100000, 1, 'nominal', 150000);

        $this->assertSame(100000.0, $discount);
    }

    public function test_transaction_discount_percentage(): void
    {
        $discount = $this->service->calculateTransactionDiscount(500000, 'percentage', 20);

        $this->assertSame(100000.0, $discount);
    }

    public function test_gross_profit_positive(): void
    {
        $grossProfit = $this->service->calculateGrossProfit(200000, 120000, 1);

        $this->assertSame(80000.0, $grossProfit);
    }

    public function test_gross_profit_negative_when_selling_below_cost(): void
    {
        $grossProfit = $this->service->calculateGrossProfit(90000, 120000, 1);

        $this->assertSame(-30000.0, $grossProfit);
    }

    public function test_calculate_sales_item_totals(): void
    {
        $totals = $this->service->calculateSalesItemTotals([
            'selling_price' => 200000,
            'qty' => 2,
            'item_discount_type' => 'percentage',
            'item_discount_value' => 10,
            'supplier_cost_snapshot' => 120000,
        ]);

        $this->assertSame(400000.0, $totals['subtotal']);
        $this->assertSame(40000.0, $totals['item_discount_amount']);
        $this->assertSame(360000.0, $totals['total_after_discount']);
        $this->assertSame(120000.0, $totals['gross_profit']);
    }

    public function test_calculate_transaction_totals(): void
    {
        $itemTotals = [
            [
                'subtotal' => 200000,
                'item_discount_amount' => 0,
                'total_after_discount' => 200000,
                'gross_profit' => 0,
            ],
            [
                'subtotal' => 200000,
                'item_discount_amount' => 0,
                'total_after_discount' => 200000,
                'gross_profit' => 0,
            ],
        ];

        $totals = $this->service->calculateTransactionTotals(
            $itemTotals,
            'percentage',
            5,
        );

        $this->assertSame(400000.0, $totals['total_after_item_discount']);
        $this->assertSame(20000.0, $totals['transaction_discount_amount']);
        $this->assertSame(380000.0, $totals['grand_total']);
    }

    public function test_invalid_margin_type_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculateBaseSellingPrice(100000, 'invalid', 10);
    }
}
