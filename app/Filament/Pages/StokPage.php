<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StokPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Stok';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Stok';

    protected static ?string $slug = 'stok';

    protected static string $view = 'filament.pages.stok';

    public string $activeTab = 'ringkasan';

    public bool $showCost = false;

    public bool $showPasswordModal = false;

    public string $costPassword = '';

    public string $passwordError = '';

    public int $totalSku = 0;

    public int $totalUnits = 0;

    public int $locationCount = 0;

    public int $categoryCount = 0;

    public int $lowStockCount = 0;

    public float $totalCapitalValue = 0;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageCatalog();
    }

    public function mount(): void
    {
        $this->totalSku = Item::query()->where('is_active', true)->count();
        $this->totalUnits = (int) StockBalance::query()->sum('qty');
        $this->locationCount = Location::query()->where('status', 'active')->count();
        $this->categoryCount = Category::query()->where('is_active', true)->count();
        $this->lowStockCount = StockBalance::query()
            ->where('stock_status', 'available')
            ->groupBy('item_id')
            ->havingRaw('SUM(qty) <= 1')
            ->get()
            ->count();
        $this->totalCapitalValue = (float) StockBalance::query()
            ->where('stock_status', 'available')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->sum(DB::raw('stock_balances.qty * items.latest_supplier_cost'));
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['ringkasan', 'tambah-stok', 'riwayat-pergerakan', 'harga-jual'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function toggleCostView(): void
    {
        if (auth()->user()?->role !== UserRole::OWNER) {
            return;
        }

        if ($this->showCost) {
            $this->showCost = false;
        } else {
            $this->showPasswordModal = true;
        }
    }

    public function verifyCostPassword(): void
    {
        if (Hash::check($this->costPassword, auth()->user()->password)) {
            $this->showCost = true;
            $this->showPasswordModal = false;
            $this->costPassword = '';
            $this->passwordError = '';

            return;
        }

        $this->passwordError = 'Password tidak sesuai.';
    }

    public function cancelCostView(): void
    {
        $this->showPasswordModal = false;
        $this->costPassword = '';
        $this->passwordError = '';
    }

    public function getActiveTabLabel(): string
    {
        return match ($this->activeTab) {
            'ringkasan' => 'Ringkasan',
            'tambah-stok' => 'Tambah Stok',
            'riwayat-pergerakan' => 'Riwayat Pergerakan',
            'harga-jual' => 'Harga Jual',
            default => 'Stok',
        };
    }

    protected function getViewData(): array
    {
        return [
            'stockItems' => Item::query()
                ->where('is_active', true)
                ->withSum(['stockBalances as total_available' => fn ($q) => $q->where('stock_status', 'available')], 'qty')
                ->withSum(['stockBalances as total_damaged' => fn ($q) => $q->where('stock_status', 'damaged')], 'qty')
                ->withSum(['stockBalances as total_stock' => fn ($q) => $q->whereIn('stock_status', ['available', 'damaged'])], 'qty')
                ->orderBy('item_name')
                ->get(),
            'recentMovements' => StockMovement::query()
                ->with(['item', 'fromLocation', 'toLocation'])
                ->where('created_at', '>=', now()->subDays(30))
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'priceItems' => Item::query()
                ->where('is_active', true)
                ->orderBy('item_name')
                ->get(),
        ];
    }
}
