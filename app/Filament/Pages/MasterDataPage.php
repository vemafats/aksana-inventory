<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\User;
use App\Models\Location;
use App\Models\ProductModel;
use App\Models\Size;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class MasterDataPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Master Data';

    protected static ?string $slug = 'master-data';

    protected static string $view = 'filament.pages.master-data';

    public string $selectedTab = 'categories';

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageMasterData();
    }

    /**
     * @return array<string, array{label: string, icon: string, count: int}>
     */
    public function getMasterTabsProperty(): array
    {
        return [
            'categories' => ['icon' => 'heroicon-o-squares-2x2', 'label' => 'Kategori', 'count' => Category::query()->count()],
            'brands' => ['icon' => 'heroicon-o-tag', 'label' => 'Merk', 'count' => Brand::query()->count()],
            'models' => ['icon' => 'heroicon-o-cube', 'label' => 'Model', 'count' => ProductModel::query()->count()],
            'colors' => ['icon' => 'heroicon-o-swatch', 'label' => 'Warna', 'count' => Color::query()->count()],
            'sizes' => ['icon' => 'heroicon-o-arrows-pointing-out', 'label' => 'Ukuran', 'count' => Size::query()->count()],
            'employees' => ['icon' => 'heroicon-o-users', 'label' => 'Karyawan', 'count' => User::query()->where('is_active', true)->count()],
            'locations' => ['icon' => 'heroicon-o-map-pin', 'label' => 'Lokasi', 'count' => Location::query()->count()],
        ];
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public function getTabs(): array
    {
        return collect($this->masterTabs)
            ->map(fn (array $tab): array => ['label' => $tab['label'], 'icon' => $tab['icon']])
            ->all();
    }

    public function getTabCount(string $tab): int
    {
        return $this->masterTabs[$tab]['count'] ?? 0;
    }

    public function getActiveTabLabel(): string
    {
        return $this->getTabs()[$this->selectedTab]['label'] ?? 'Master Data';
    }

    public function selectTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->masterTabs)) {
            return;
        }

        $this->selectedTab = $tab;
        $this->search = '';
    }

    public function displayCode(string $name): string
    {
        $slug = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', Str::slug($name)) ?? '');

        return Str::limit($slug, 6, '');
    }
}
