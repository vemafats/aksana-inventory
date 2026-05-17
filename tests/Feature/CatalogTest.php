<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\ProductModel;
use App\Models\Size;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            UserSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ColorSeeder::class,
            SizeSeeder::class,
            ProductModelSeeder::class,
        ]);
    }

    public function test_can_create_catalog_item(): void
    {
        $payload = $this->catalogPayload();

        $response = $this->actingAsAdmin()
            ->postJson('/api/catalogs', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'sku', 'barcode', 'item_name'],
            ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString('latest_supplier_cost', $content);
        $this->assertStringNotContainsString('supplier_cost', $content);
    }

    public function test_item_name_auto_generated(): void
    {
        $payload = $this->catalogPayload();
        unset($payload['item_name']);

        $response = $this->actingAsAdmin()
            ->postJson('/api/catalogs', $payload);

        $response->assertCreated();

        $itemName = $response->json('data.item_name');
        $this->assertNotNull($itemName);
        $this->assertNotSame('', trim($itemName));
        $this->assertStringContainsString('Nike', $itemName);
        $this->assertStringContainsString('Air Max', $itemName);
    }

    public function test_sku_auto_generated_and_unique(): void
    {
        $payload = $this->catalogPayload();

        $first = $this->actingAsAdmin()->postJson('/api/catalogs', $payload);
        $second = $this->actingAsAdmin()->postJson('/api/catalogs', $payload);

        $first->assertCreated();
        $second->assertCreated();

        $firstSku = $first->json('data.sku');
        $secondSku = $second->json('data.sku');
        $firstBarcode = $first->json('data.barcode');
        $secondBarcode = $second->json('data.barcode');

        $this->assertNotSame($firstSku, $secondSku);
        $this->assertNotSame($firstBarcode, $secondBarcode);
    }

    public function test_can_find_item_by_barcode(): void
    {
        $create = $this->actingAsAdmin()
            ->postJson('/api/catalogs', $this->catalogPayload());

        $barcode = $create->json('data.barcode');

        $response = $this->actingAsAdmin()
            ->getJson("/api/catalogs/by-barcode/{$barcode}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.barcode', $barcode);
    }

    public function test_returns_404_for_unknown_barcode(): void
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/catalogs/by-barcode/TIDAK-ADA');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('barcode', 'TIDAK-ADA');

        $this->assertStringContainsString(
            'Buat katalog dulu',
            $response->json('message')
        );
    }

    public function test_sales_cannot_create_catalog_item(): void
    {
        $login = $this->postJson('/api/login', [
            'email' => 'sales@aksana.id',
            'password' => 'password',
        ]);

        $response = $this->withToken($login->json('data.token'))
            ->postJson('/api/catalogs', $this->catalogPayload());

        $response->assertForbidden();
    }

    public function test_supplier_cost_never_in_response(): void
    {
        $create = $this->actingAsAdmin()
            ->postJson('/api/catalogs', $this->catalogPayload());

        $itemId = $create->json('data.id');

        $response = $this->actingAsAdmin()
            ->getJson("/api/catalogs/{$itemId}");

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('latest_supplier_cost', $content);
        $this->assertStringNotContainsString('supplier_cost', $content);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPayload(): array
    {
        $category = Category::where('code', 'SEP')->firstOrFail();
        $brand = Brand::where('name', 'Nike')->firstOrFail();
        $model = ProductModel::where('name', 'Air Max')
            ->where('category_id', $category->id)
            ->where('brand_id', $brand->id)
            ->firstOrFail();
        $color = Color::where('name', 'Hitam')->firstOrFail();
        $size = Size::where('name', '40')->where('size_type', 'shoes')->firstOrFail();

        return [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'color_id' => $color->id,
            'size_id' => $size->id,
        ];
    }

    private function actingAsAdmin(): static
    {
        $login = $this->postJson('/api/login', [
            'email' => 'admin@aksana.id',
            'password' => 'password',
        ]);

        return $this->withToken($login->json('data.token'));
    }
}
