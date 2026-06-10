<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Item;
use App\Models\ProductModel;
use App\Models\Size;
use App\Services\QrCodeService;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Database\Seeders\BrandSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\ProductModelSeeder;
use Database\Seeders\SizeSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoAndQrCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([
            UserSeeder::class,
            CategorySeeder::class,
            BrandSeeder::class,
            ColorSeeder::class,
            SizeSeeder::class,
            ProductModelSeeder::class,
        ]);
    }

    public function test_can_upload_photo(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsAdmin()->post('/api/photos', [
            'photo' => UploadedFile::fake()->image('proof.jpg', 800, 600),
            'related_type' => 'stock_in',
            'related_id' => $item->id,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'photo_path', 'photo_url', 'photo_timestamp'],
            ]);

        Storage::disk('public')->assertExists($response->json('data.photo_path'));
    }

    public function test_photo_exceeding_size_is_rejected(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsAdmin()->post('/api/photos', [
            'photo' => UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg'),
            'related_type' => 'stock_in',
            'related_id' => $item->id,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_qrcode_returns_image(): void
    {
        $item = $this->createCatalogItem();

        $response = $this->actingAsAdmin()
            ->get("/api/catalogs/{$item->id}/qrcode");

        $response->assertOk();
        $this->assertStringContainsString('image/png', $response->headers->get('content-type'));
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $response->getContent());
    }

    public function test_qrcode_content_is_sku_string(): void
    {
        $item = $this->createCatalogItem();

        $this->assertStringNotContainsString('http', $item->barcode);
        $this->assertStringNotContainsString('://', $item->barcode);

        $png = base64_decode(app(QrCodeService::class)->generateQrCode($item), true);

        $this->assertNotFalse($png);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $encoded = Encoder::encode($item->barcode, ErrorCorrectionLevel::M());
        $this->assertGreaterThan(0, $encoded->getMatrix()->getWidth());

        $otherItem = $this->createCatalogItemWithSize('41');
        $otherPng = base64_decode(app(QrCodeService::class)->generateQrCode($otherItem), true);

        $this->assertNotSame($png, $otherPng);
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
        $size = Size::where('name', '40')->where('category_id', $category->id)->firstOrFail();

        return [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'color_id' => $color->id,
            'size_id' => $size->id,
        ];
    }

    private function createCatalogItem(): Item
    {
        return $this->createCatalogItemWithSize('40');
    }

    private function createCatalogItemWithSize(string $sizeName): Item
    {
        $payload = $this->catalogPayload();
        $category = Category::where('code', 'SEP')->firstOrFail();
        $payload['size_id'] = Size::where('name', $sizeName)
            ->where('category_id', $category->id)
            ->firstOrFail()
            ->id;

        $response = $this->actingAsAdmin()
            ->postJson('/api/catalogs', $payload);

        $response->assertCreated();

        return Item::query()->findOrFail($response->json('data.id'));
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
