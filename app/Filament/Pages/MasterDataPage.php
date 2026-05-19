<?php

namespace App\Filament\Pages;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Employee;
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
     * @return array<string, array{label: string, icon: string}>
     */
    public function getTabs(): array
    {
        return [
            'categories' => ['label' => 'Kategori', 'icon' => 'heroicon-o-tag'],
            'brands' => ['label' => 'Merk', 'icon' => 'heroicon-o-building-storefront'],
            'models' => ['label' => 'Model', 'icon' => 'heroicon-o-cube'],
            'colors' => ['label' => 'Warna', 'icon' => 'heroicon-o-swatch'],
            'sizes' => ['label' => 'Ukuran', 'icon' => 'heroicon-o-arrows-up-down'],
            'employees' => ['label' => 'Karyawan', 'icon' => 'heroicon-o-user-group'],
            'locations' => ['label' => 'Lokasi', 'icon' => 'heroicon-o-map-pin'],
        ];
    }

    public function getTabCount(string $tab): int
    {
        return match ($tab) {
            'categories' => Category::query()->count(),
            'brands' => Brand::query()->count(),
            'models' => ProductModel::query()->count(),
            'colors' => Color::query()->count(),
            'sizes' => Size::query()->count(),
            'employees' => Employee::query()->count(),
            'locations' => Location::query()->count(),
            default => 0,
        };
    }

    public function getActiveTabLabel(): string
    {
        return $this->getTabs()[$this->selectedTab]['label'] ?? 'Master Data';
    }

    public function selectTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->getTabs())) {
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
