<?php

namespace App\Filament\Pages;

use App\Enums\LocationType;
use App\Enums\UserRole;
use App\Helpers\SupplierCostHelper;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockInItem;
use App\Models\StockInTransaction;
use App\Models\StockMovement;
use App\Services\PhotoService;
use App\Services\PriceCalculationService;
use App\Services\StockInService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StokPage extends Page
{
    use WithFileUploads;

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

    public string $poReference = '';

    public string $transactionDate = '';

    public string $supplierName = '';

    /** @var array<int, array<string, mixed>> */
    public array $stockInItems = [];

    public ?string $stockInPhotoId = null;

    public ?string $stockInPhotoPreview = null;

    public string $stockInPhotoDraftId = '';

    public $stockInPhoto = null;

    public bool $stockInLoading = false;

    public string $stockInError = '';

    public string $stockInSuccess = '';

    public string $stockInWarning = '';

    public bool $stockInConfirmZeroCost = false;

    public ?string $editingStockInId = null;

    public ?string $editingStockInItemId = null;

    public string $editMarginType = 'percentage';

    public float $editCalculatedPrice = 0;

    public string $editQcNote = '';

    /** @var int|float|string|null */
    public $editSupplierCost = 0;

    /** @var int|float|string|null */
    public $editMarginValue = 0;

    public string $editStockInError = '';

    public string $editStockInSuccess = '';

    public string $printItemBarcode = '';

    public string $printLabelSize = '40x20';

    public int $printQty = 1;

    public bool $showPrintModal = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->canManageCatalog();
    }

    public function mount(): void
    {
        $this->transactionDate = now()->toDateString();
        $this->stockInPhotoDraftId = (string) Str::uuid();
        $this->refreshSummaryStats();
    }

    public function selectTab(string $tab): void
    {
        if ($tab === 'update-harga' && auth()->user()?->role !== UserRole::OWNER) {
            return;
        }

        if (! in_array($tab, ['ringkasan', 'tambah-stok', 'riwayat-pergerakan', 'harga-jual', 'update-harga'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function isOwner(): bool
    {
        return auth()->user()?->role === UserRole::OWNER;
    }

    public function openPrintModal(): void
    {
        $this->showPrintModal = true;
        $this->printItemBarcode = '';
        $this->printLabelSize = '40x20';
        $this->printQty = 1;
    }

    public function closePrintModal(): void
    {
        $this->showPrintModal = false;
    }

    /**
     * @return list<array{barcode: string, item_name: string}>
     */
    public function searchPrintItems(string $search = ''): array
    {
        $query = Item::query()
            ->where('is_active', true)
            ->select('barcode', 'item_name')
            ->orderBy('item_name');

        $term = trim($search);

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(fn ($q) => $q
                ->where('item_name', 'like', $like)
                ->orWhere('barcode', 'like', $like));
        }

        return $query
            ->limit(50)
            ->get()
            ->map(fn (Item $item): array => [
                'barcode' => $item->barcode,
                'item_name' => $item->item_name,
            ])
            ->values()
            ->all();
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
            'harga-jual' => 'Harga Jual Dasar',
            'update-harga' => 'Update Harga Stok',
            default => 'Stok',
        };
    }

    public function addStockInItem(): void
    {
        $this->stockInItems[] = $this->emptyStockInItemRow();
        $this->stockInError = '';
        $this->stockInSuccess = '';
    }

    public function removeStockInItem(int $index): void
    {
        if (! isset($this->stockInItems[$index])) {
            return;
        }

        unset($this->stockInItems[$index]);
        $this->stockInItems = array_values($this->stockInItems);
    }

    public function updateStockInItem(int $index, string $field, mixed $value): void
    {
        if (! isset($this->stockInItems[$index])) {
            return;
        }

        $this->stockInItems[$index][$field] = $value;

        if ($field === 'item_id' && filled($value)) {
            $item = Item::query()->find($value);

            if ($item !== null) {
                $item->makeVisible(['latest_supplier_cost']);
                $this->stockInItems[$index]['item_name'] = $item->item_name;
                $this->stockInItems[$index]['barcode'] = $item->barcode;
                $this->stockInItems[$index]['sku'] = $item->sku;
                $this->stockInItems[$index]['base_selling_price'] = (float) $item->latest_base_selling_price;

                if ((float) ($this->stockInItems[$index]['supplier_cost'] ?? 0) <= 0) {
                    $this->stockInItems[$index]['supplier_cost'] = (float) $item->latest_supplier_cost;
                }
            }
        }

        if (in_array($field, ['qty_received', 'qty_available'], true)) {
            $received = max(0, (int) ($this->stockInItems[$index]['qty_received'] ?? 0));
            $available = max(0, (int) ($this->stockInItems[$index]['qty_available'] ?? 0));
            $available = min($available, $received);
            $this->stockInItems[$index]['qty_received'] = $received;
            $this->stockInItems[$index]['qty_available'] = $available;
            $this->stockInItems[$index]['qty_damaged'] = max(0, $received - $available);
        }

        if (in_array($field, ['supplier_cost', 'margin_type', 'margin_value', 'item_id'], true)) {
            $this->recalculateItemSellingPrice($index);
        }

        $this->stockInConfirmZeroCost = false;
        $this->stockInWarning = '';
    }

    public function getTotalQty(): int
    {
        return (int) collect($this->stockInItems)->sum(fn (array $row): int => (int) ($row['qty_received'] ?? 0));
    }

    public function getTotalModal(): float
    {
        return (float) collect($this->stockInItems)->sum(function (array $row): float {
            $qty = (int) ($row['qty_available'] ?? 0);
            $cost = (float) ($row['supplier_cost'] ?? 0);

            return $qty * $cost;
        });
    }

    public function uploadStockInPhoto(): void
    {
        $this->validate([
            'stockInPhoto' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        if (! $this->stockInPhoto instanceof TemporaryUploadedFile) {
            return;
        }

        try {
            $photo = app(PhotoService::class)->uploadPhoto(
                $this->stockInPhoto,
                'stock_in',
                $this->stockInPhotoDraftId,
                auth()->user(),
            );

            $this->stockInPhotoId = $photo->id;
            $this->stockInPhotoPreview = app(PhotoService::class)->getPhotoUrl($photo);
            $this->stockInPhoto = null;
            $this->stockInError = '';
        } catch (\InvalidArgumentException $exception) {
            $this->stockInError = $exception->getMessage();
        }
    }

    public function removeStockInPhoto(): void
    {
        $this->stockInPhotoId = null;
        $this->stockInPhotoPreview = null;
        $this->stockInPhoto = null;
        $this->stockInPhotoDraftId = (string) Str::uuid();
    }

    public function resetStockInForm(): void
    {
        $this->poReference = '';
        $this->transactionDate = now()->toDateString();
        $this->supplierName = '';
        $this->stockInItems = [];
        $this->removeStockInPhoto();
        $this->stockInError = '';
        $this->stockInSuccess = '';
        $this->stockInWarning = '';
        $this->stockInConfirmZeroCost = false;
        $this->stockInLoading = false;
    }

    public function submitStockIn(): void
    {
        $this->stockInError = '';
        $this->stockInSuccess = '';

        if (trim($this->poReference) === '') {
            $this->stockInError = 'No. Referensi/PO wajib diisi.';

            return;
        }

        if ($this->stockInItems === []) {
            $this->stockInError = 'Tambahkan minimal 1 item.';

            return;
        }

        foreach ($this->stockInItems as $index => $row) {
            if (empty($row['item_id']) || (int) ($row['qty_received'] ?? 0) <= 0) {
                $this->stockInError = 'Semua item harus dipilih dengan qty diterima lebih dari 0.';

                return;
            }

            $received = (int) ($row['qty_received'] ?? 0);
            $available = (int) ($row['qty_available'] ?? 0);
            $damaged = (int) ($row['qty_damaged'] ?? 0);

            if ($received !== $available + $damaged) {
                $this->stockInError = 'Qty diterima harus sama dengan qty available + qty damaged pada baris '.($index + 1).'.';

                return;
            }
        }

        $hasZeroCost = collect($this->stockInItems)->contains(
            fn (array $row): bool => SupplierCostHelper::isUnset($row['supplier_cost'] ?? 0)
        );

        if ($hasZeroCost && ! $this->stockInConfirmZeroCost) {
            $this->stockInWarning = 'Harga modal belum diisi. Stok akan dicatat tanpa harga.';
            $this->stockInConfirmZeroCost = true;

            return;
        }

        $user = auth()->user();

        if ($user === null || ! $user->role->canStockIn()) {
            $this->stockInError = 'Anda tidak memiliki akses untuk barang masuk.';

            return;
        }

        $this->stockInLoading = true;

        try {
            $noteParts = array_filter([
                'Ref/PO: '.trim($this->poReference),
                trim($this->supplierName) !== '' ? 'Supplier: '.trim($this->supplierName) : null,
            ]);

            $payload = [
                'supplier_name' => trim($this->supplierName) !== '' ? trim($this->supplierName) : null,
                'transaction_date' => $this->transactionDate,
                'note' => implode("\n", $noteParts),
                'photo_id' => $this->stockInPhotoId,
                'items' => collect($this->stockInItems)->map(function (array $row): array {
                    $marginType = $row['margin_type'] ?? 'none';

                    return [
                        'barcode' => $row['barcode'],
                        'qty_received' => (int) $row['qty_received'],
                        'qty_available' => (int) $row['qty_available'],
                        'qty_damaged' => (int) $row['qty_damaged'],
                        'supplier_cost' => (float) ($row['supplier_cost'] ?? 0),
                        'base_margin_type' => $marginType,
                        'base_margin_value' => $marginType === 'none' ? 0 : (float) ($row['margin_value'] ?? 0),
                        'base_selling_price' => (float) ($row['calculated_selling_price'] ?? 0),
                        'qc_note' => filled($row['qc_note'] ?? null) ? $row['qc_note'] : null,
                    ];
                })->all(),
            ];

            app(StockInService::class)->createTransaction($payload, $user);

            $this->resetStockInForm();
            $this->refreshSummaryStats();
            $this->stockInSuccess = 'Barang masuk berhasil disimpan ke gudang pusat.';
        } catch (InvalidArgumentException $exception) {
            $this->stockInError = $exception->getMessage();
        } finally {
            $this->stockInLoading = false;
        }
    }

    public function canEditStockInPrice(): bool
    {
        $user = auth()->user();

        return $user !== null && in_array($user->role, [UserRole::OWNER, UserRole::ADMIN], true);
    }

    public function selectStockInForEdit(string $transactionId): void
    {
        if (! $this->canEditStockInPrice()) {
            $this->editStockInError = 'Hanya Owner dan Admin yang dapat mengedit harga modal.';

            return;
        }

        $transaction = StockInTransaction::query()
            ->with(['stockInItems.item'])
            ->findOrFail($transactionId);

        $this->editingStockInId = $transactionId;
        $this->editStockInError = '';
        $this->editStockInSuccess = '';

        $transaction->stockInItems->each->makeVisible(['supplier_cost']);

        $targetItem = $transaction->stockInItems->first(
            fn (StockInItem $row): bool => SupplierCostHelper::isUnset($row->supplier_cost)
        ) ?? $transaction->stockInItems->first();

        if ($targetItem === null) {
            $this->editStockInError = 'Transaksi tidak memiliki item.';

            return;
        }

        $this->editingStockInItemId = $targetItem->id;
        $this->editSupplierCost = (float) $targetItem->supplier_cost;
        $this->editMarginType = (string) ($targetItem->base_margin_type ?? 'percentage');
        $this->editMarginValue = (float) ($targetItem->base_margin_value ?? 0);
        $this->editQcNote = $targetItem->qc_note ?? '';
        $this->recalculateEditPrice();
    }

    public function getEditingStockInTransaction(): ?StockInTransaction
    {
        if ($this->editingStockInId === null) {
            return null;
        }

        return StockInTransaction::query()
            ->with(['stockInItems.item'])
            ->find($this->editingStockInId);
    }

    public function getEditingStockInItem(): ?StockInItem
    {
        if ($this->editingStockInItemId === null) {
            return null;
        }

        $item = StockInItem::query()
            ->with('item')
            ->find($this->editingStockInItemId);

        $item?->makeVisible(['supplier_cost']);

        return $item;
    }

    /**
     * @return array{color: string, label: string, isInbound: bool}
     */
    public function movementTypeMeta(StockMovement $movement): array
    {
        $movementType = $movement->movement_type instanceof \BackedEnum
            ? $movement->movement_type->value
            : (string) $movement->movement_type;

        $typeColors = [
            'stock_in_available' => 'bg-green-100 text-green-700',
            'stock_in_damaged' => 'bg-orange-100 text-orange-700',
            'transfer_available' => 'bg-blue-100 text-blue-700',
            'sale' => 'bg-red-100 text-red-700',
            'return_to_warehouse' => 'bg-yellow-100 text-yellow-700',
        ];

        $typeLabels = [
            'stock_in_available' => 'MASUK',
            'stock_in_damaged' => 'MASUK RUSAK',
            'transfer_available' => 'DISTRIBUSI',
            'sale' => 'KELUAR',
            'return_to_warehouse' => 'RETUR',
        ];

        return [
            'color' => $typeColors[$movementType] ?? 'bg-gray-100 text-gray-600',
            'label' => $typeLabels[$movementType] ?? strtoupper($movementType),
            'isInbound' => in_array($movementType, ['stock_in_available', 'stock_in_damaged', 'return_to_warehouse'], true),
        ];
    }

    public function itemMarginPercent(Item $item): int
    {
        $item->makeVisible(['latest_supplier_cost']);

        if ((float) $item->latest_supplier_cost <= 0) {
            return 0;
        }

        return (int) round((($item->latest_base_selling_price - $item->latest_supplier_cost) / $item->latest_supplier_cost) * 100);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array{location_id: mixed, location_name: mixed, qty: int}>
     */
    public function locationQtyMap(Item $item): \Illuminate\Support\Collection
    {
        return collect($item->per_location ?? [])->keyBy('location_id');
    }

    public function locationQtyFor(Item $item, string $locationId): int
    {
        return (int) ($this->locationQtyMap($item)->get($locationId)['qty'] ?? 0);
    }

    public function recalculateEditPrice(): void
    {
        $cost = (float) ($this->editSupplierCost ?? 0);
        $margin = (float) ($this->editMarginValue ?? 0);

        $this->editCalculatedPrice = match ($this->editMarginType) {
            'nominal' => $cost + $margin,
            'percentage' => $cost > 0
                ? round($cost * (1 + $margin / 100))
                : 0,
            default => $cost,
        };
    }

    public function cancelStockInPriceEdit(): void
    {
        $this->editingStockInId = null;
        $this->editingStockInItemId = null;
        $this->editSupplierCost = 0;
        $this->editMarginType = 'percentage';
        $this->editMarginValue = 0;
        $this->editCalculatedPrice = 0;
        $this->editQcNote = '';
        $this->editStockInError = '';
        $this->editStockInSuccess = '';
    }

    public function saveStockInPrice(): void
    {
        if (! $this->canEditStockInPrice()) {
            $this->editStockInError = 'Hanya Owner dan Admin yang dapat mengedit harga modal.';

            return;
        }

        if ($this->editingStockInId === null || $this->editingStockInItemId === null) {
            return;
        }

        $this->validate([
            'editSupplierCost' => ['required', 'numeric', 'min:1'],
            'editMarginType' => ['required', 'in:nominal,percentage,none'],
            'editMarginValue' => ['required', 'numeric', 'min:0'],
        ]);

        $this->editStockInError = '';
        $this->editStockInSuccess = '';

        try {
            $transaction = StockInTransaction::query()->findOrFail($this->editingStockInId);
            $item = StockInItem::query()->findOrFail($this->editingStockInItemId);

            app(StockInService::class)->updateItemPrice(
                $transaction,
                $item,
                (float) $this->editSupplierCost,
                $this->editMarginType,
                (float) $this->editMarginValue,
                $this->editQcNote !== '' ? $this->editQcNote : null,
            );

            $this->cancelStockInPriceEdit();
            $this->editStockInSuccess = 'Harga berhasil disimpan.';
            $this->refreshSummaryStats();
        } catch (InvalidArgumentException $exception) {
            $this->editStockInError = $exception->getMessage();
        }
    }

    public function stockInHasMissingPrice(StockInTransaction $transaction): bool
    {
        if (! $transaction->relationLoaded('stockInItems')) {
            $transaction->load('stockInItems');
        }

        $transaction->stockInItems->each->makeVisible(['supplier_cost']);

        return $transaction->stockInItems->contains(
            fn (StockInItem $row): bool => SupplierCostHelper::isUnset($row->supplier_cost)
        );
    }

    protected function getViewData(): array
    {
        return [
            'stockItems' => Item::query()
                ->where('is_active', true)
                ->with(['stockBalances.location'])
                ->withSum(['stockBalances as total_available' => fn ($q) => $q->where('stock_status', 'available')], 'qty')
                ->withSum(['stockBalances as total_damaged' => fn ($q) => $q->where('stock_status', 'damaged')], 'qty')
                ->withSum(['stockBalances as total_stock' => fn ($q) => $q->whereIn('stock_status', ['available', 'damaged'])], 'qty')
                ->orderBy('item_name')
                ->get()
                ->map(function (Item $item) {
                    $perLocation = $item->stockBalances
                        ->where('stock_status', 'available')
                        ->groupBy('location_id')
                        ->map(fn ($balances) => [
                            'location_id' => $balances->first()->location_id,
                            'location_name' => $balances->first()->location?->location_name ?? '-',
                            'qty' => (int) $balances->sum('qty'),
                        ])
                        ->values();

                    $item->per_location = $perLocation;

                    return $item;
                }),
            'allLocations' => Location::query()
                ->where('status', 'active')
                ->orderBy('location_type')
                ->orderBy('location_name')
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
            'catalogItems' => Item::query()
                ->where('is_active', true)
                ->orderBy('item_name')
                ->get(['id', 'item_name', 'barcode', 'sku']),
            'printableItems' => Item::query()
                ->where('is_active', true)
                ->select('barcode', 'item_name')
                ->orderBy('item_name')
                ->get(),
            'centralWarehouse' => Location::query()
                ->where('location_type', LocationType::CENTRAL_WAREHOUSE->value)
                ->where('status', 'active')
                ->orderBy('created_at')
                ->first(),
            'recentStockIns' => StockInTransaction::query()
                ->with(['stockInItems.item'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->each(function (StockInTransaction $transaction): void {
                    $transaction->stockInItems->each->makeVisible(['supplier_cost']);
                }),
            'stockInHistory' => StockInTransaction::query()
                ->with(['items.item', 'createdBy'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(function (StockInTransaction $transaction): StockInTransaction {
                    $transaction->items->each->makeVisible(['supplier_cost']);
                    $transaction->has_unpriced_items = $transaction->items->contains(
                        fn (StockInItem $item): bool => SupplierCostHelper::isUnset($item->supplier_cost)
                    );

                    return $transaction;
                }),
        ];
    }

    /**
     * @return list<array{label: string, value: string, sub: string, warn: bool, danger?: bool}>
     */
    public function getRingkasanStatCards(): array
    {
        $cards = [
            [
                'label' => 'Total SKU',
                'value' => number_format($this->totalSku),
                'sub' => 'item aktif di katalog',
                'warn' => false,
            ],
            [
                'label' => 'Total Unit Stok',
                'value' => number_format($this->totalUnits),
                'sub' => 'unit available di seluruh lokasi',
                'warn' => false,
            ],
        ];

        if ($this->isOwner()) {
            $cards[] = [
                'label' => 'Nilai Inventory',
                'value' => \App\Helpers\FormatHelper::price($this->totalCapitalValue),
                'sub' => 'total harga modal stok available',
                'warn' => false,
            ];
        }

        $cards[] = [
            'label' => 'Stok Kritis',
            'value' => number_format($this->lowStockCount),
            'sub' => 'item dengan stok ≤ 1 unit',
            'warn' => $this->lowStockCount > 0,
            'danger' => true,
        ];

        return $cards;
    }

    private function refreshSummaryStats(): void
    {
        $this->totalSku = Item::query()->where('is_active', true)->count();
        $this->totalUnits = (int) StockBalance::query()
            ->where('stock_status', 'available')
            ->sum('qty');
        $this->locationCount = Location::query()->where('status', 'active')->count();
        $this->categoryCount = Category::query()->where('is_active', true)->count();
        $this->lowStockCount = StockBalance::query()
            ->where('stock_status', 'available')
            ->selectRaw('item_id, SUM(qty) as total_qty')
            ->groupBy('item_id')
            ->havingRaw('SUM(qty) <= 1')
            ->get()
            ->count();
        $this->totalCapitalValue = (float) StockBalance::query()
            ->where('stock_status', 'available')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->sum(DB::raw('stock_balances.qty * items.latest_supplier_cost'));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStockInItemRow(): array
    {
        return [
            'item_id' => '',
            'item_name' => '',
            'barcode' => '',
            'sku' => '',
            'base_selling_price' => 0,
            'qty_received' => 1,
            'qty_available' => 1,
            'qty_damaged' => 0,
            'supplier_cost' => 0,
            'margin_type' => 'nominal',
            'margin_value' => 0,
            'calculated_selling_price' => 0,
            'qc_note' => '',
        ];
    }

    private function recalculateItemSellingPrice(int $index): void
    {
        if (! isset($this->stockInItems[$index])) {
            return;
        }

        $row = &$this->stockInItems[$index];
        $cost = (float) ($row['supplier_cost'] ?? 0);
        $marginType = $row['margin_type'] ?? 'none';
        $marginValue = (float) ($row['margin_value'] ?? 0);

        $row['calculated_selling_price'] = app(PriceCalculationService::class)
            ->calculateBaseSellingPrice($cost, $marginType, $marginValue);
    }
}
