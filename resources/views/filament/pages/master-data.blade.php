@php
    use App\Enums\LocationType;
    use App\Filament\Resources\BrandResource;
    use App\Filament\Resources\CategoryResource;
    use App\Filament\Resources\ColorResource;
    use App\Filament\Resources\UserResource;
    use App\Filament\Resources\LocationResource;
    use App\Filament\Resources\ProductModelResource;
    use App\Filament\Resources\SizeResource;
    use App\Models\Brand;
    use App\Models\Category;
    use App\Models\Color;
    use App\Models\Location;
    use App\Models\ProductModel;
    use App\Models\Size;
    use App\Models\User;

    $search = trim($this->search);
    $like = $search !== '' ? '%'.$search.'%' : null;
@endphp

<x-filament-panels::page>
    @php
        $createUrls = [
            'categories' => CategoryResource::getUrl('create'),
            'brands' => BrandResource::getUrl('create'),
            'models' => ProductModelResource::getUrl('create'),
            'colors' => ColorResource::getUrl('create'),
            'sizes' => SizeResource::getUrl('create'),
            'employees' => UserResource::getUrl('create'),
            'locations' => LocationResource::getUrl('create'),
        ];
        $createLabels = [
            'categories' => '+ TAMBAH KATEGORI',
            'brands' => '+ TAMBAH MERK',
            'models' => '+ TAMBAH MODEL',
            'colors' => '+ TAMBAH WARNA',
            'sizes' => '+ TAMBAH UKURAN',
            'employees' => '+ TAMBAH KARYAWAN',
            'locations' => '+ TAMBAH LOKASI',
        ];
    @endphp

    <div style="display:flex; gap:24px; align-items:flex-start;">
        <div style="width:280px; flex-shrink:0; background:white; border-radius:12px; border:1px solid #e5e7eb; padding:16px;">
            <h4 style="font-size:11px; font-weight:700; letter-spacing:1.5px; color:#6b7280; margin:0 0 12px; text-transform:uppercase;">Tabel Master</h4>
            @foreach ($this->masterTabs as $key => $tab)
                <button wire:click="selectTab('{{ $key }}')" type="button"
                    style="display:flex; align-items:center; width:100%; padding:10px 12px; border:none; border-radius:8px; cursor:pointer; margin-bottom:4px; gap:10px; font-size:14px; font-weight:{{ $selectedTab === $key ? '600' : '500' }}; {{ $selectedTab === $key ? 'background:#1a1a2e; color:white;' : 'background:transparent; color:#374151;' }}">
                    <x-dynamic-component :component="$tab['icon']" style="width:18px; height:18px; flex-shrink:0;" />
                    <span style="flex:1; text-align:left;">{{ $tab['label'] }}</span>
                    <span style="font-size:12px; font-weight:600; {{ $selectedTab === $key ? 'color:rgba(255,255,255,0.7);' : 'color:#9ca3af;' }}">
                        {{ str_pad((string) $tab['count'], 2, '0', STR_PAD_LEFT) }}
                    </span>
                </button>
            @endforeach
        </div>

        <div style="flex:1; min-width:0; background:white; border-radius:12px; border:1px solid #e5e7eb; overflow:hidden;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                    <x-heroicon-o-table-cells style="width:18px; height:18px; color:#9ca3af; flex-shrink:0;" />
                    <h2 style="margin:0; font-size:16px; font-weight:700; color:#1a1a2e;">{{ $this->getActiveTabLabel() }}</h2>
                    <span style="font-size:13px; color:#6b7280;">· {{ $this->getTabCount($selectedTab) }} entri</span>
                </div>
                <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari..."
                        style="width:200px; padding:8px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px;"
                    />
                    <a href="{{ $createUrls[$selectedTab] ?? '#' }}" class="aksana-tab aksana-tab-active" style="white-space:nowrap;">
                        {{ $createLabels[$selectedTab] ?? '+ TAMBAH' }}
                    </a>
                </div>
            </div>

            <div style="overflow-x:auto; padding:16px 20px;">
                @if ($selectedTab === 'categories')
                    @php
                        $sortMap = ['code' => 'code', 'name' => 'name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = Category::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama Kategori
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Deskripsi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $cat)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $cat->code }}</td>
                                    <td class="font-semibold">{{ $cat->name }}</td>
                                    <td class="text-[var(--aksana-muted)]">—</td>
                                    <td>
                                        <a href="{{ CategoryResource::getUrl('edit', ['record' => $cat]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'brands')
                    @php
                        $sortMap = ['code' => 'code', 'name' => 'name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = Brand::query()
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama Merk
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Negara</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $brand)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $brand->code ?? '—' }}</td>
                                    <td class="font-semibold">{{ $brand->name }}</td>
                                    <td class="text-[var(--aksana-muted)]">—</td>
                                    <td>
                                        <a href="{{ BrandResource::getUrl('edit', ['record' => $brand]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'models')
                    @php
                        $sortMap = ['code' => 'product_models.code', 'name' => 'product_models.name', 'kategori' => 'categories.name', 'merk' => 'brands.name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'product_models.name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = ProductModel::query()
                            ->select('product_models.*')
                            ->with(['category', 'brand'])
                            ->leftJoin('categories', 'categories.id', '=', 'product_models.category_id')
                            ->leftJoin('brands', 'brands.id', '=', 'product_models.brand_id')
                            ->when($like, fn ($q) => $q->where('product_models.name', 'like', $like))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama Model
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('kategori')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kategori
                                        <x-dynamic-component :component="$this->sortIcon('kategori')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('merk')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Merk
                                        <x-dynamic-component :component="$this->sortIcon('merk')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $model)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $model->code ?? '—' }}</td>
                                    <td class="font-semibold">{{ $model->name }}</td>
                                    <td>{{ $model->category?->name ?? '—' }}</td>
                                    <td>{{ $model->brand?->name ?? '—' }}</td>
                                    <td>
                                        <a href="{{ ProductModelResource::getUrl('edit', ['record' => $model]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'colors')
                    @php
                        $sortMap = ['code' => 'item_code', 'name' => 'name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = Color::query()
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama Warna
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Hex</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $color)
                                @php $hex = $color->code ?? '#CCCCCC'; @endphp
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $color->item_code ?? '—' }}</td>
                                    <td class="font-semibold">{{ $color->name }}</td>
                                    <td>
                                        <span class="inline-flex items-center gap-2 font-mono text-xs">
                                            <span style="width:14px;height:14px;border-radius:4px;background:{{ $hex }};border:1px solid var(--aksana-border)"></span>
                                            {{ strtoupper($hex) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ ColorResource::getUrl('edit', ['record' => $color]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'sizes')
                    @php
                        $sortMap = ['code' => 'sizes.code', 'name' => 'sizes.name', 'kategori' => 'categories.name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? null;
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $query = Size::query()
                            ->select('sizes.*')
                            ->with('category')
                            ->leftJoin('categories', 'categories.id', '=', 'sizes.category_id')
                            ->when($like, fn ($q) => $q->where('sizes.name', 'like', $like));

                        if ($sortCol !== null) {
                            $query->orderBy($sortCol, $sortDir);
                        } else {
                            $query->orderBy('sizes.sort_order');
                        }

                        $rows = $query->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Label
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('kategori')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kategori
                                        <x-dynamic-component :component="$this->sortIcon('kategori')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $size)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $size->code ?? '—' }}</td>
                                    <td class="font-semibold">{{ $size->name }}</td>
                                    <td>{{ $size->category?->name ?? '—' }}</td>
                                    <td>
                                        <a href="{{ SizeResource::getUrl('edit', ['record' => $size]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'employees')
                    @php
                        $sortMap = ['nik' => 'nik', 'name' => 'name', 'email' => 'email'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = User::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('nik', 'like', $like)->orWhere('email', 'like', $like)))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('nik')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        NIK
                                        <x-dynamic-component :component="$this->sortIcon('nik')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('email')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Email
                                        <x-dynamic-component :component="$this->sortIcon('email')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $staff)
                                <tr>
                                    <td class="aksana-mono">{{ $staff->nik ?? '—' }}</td>
                                    <td class="font-semibold">{{ $staff->name }}</td>
                                    <td>{{ $staff->email }}</td>
                                    <td>{{ $staff->role->label() }}</td>
                                    <td>
                                        <a href="{{ UserResource::getUrl('edit', ['record' => $staff]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'locations')
                    @php
                        $sortMap = ['code' => 'location_code', 'name' => 'location_name'];
                        $sortCol = $sortMap[$this->sortColumn] ?? 'location_name';
                        $sortDir = in_array($this->sortDirection, ['asc', 'desc']) ? $this->sortDirection : 'asc';

                        $rows = Location::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('location_name', 'like', $like)->orWhere('location_code', 'like', $like)))
                            ->orderBy($sortCol, $sortDir)
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button wire:click="toggleSort('code')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Kode
                                        <x-dynamic-component :component="$this->sortIcon('code')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>
                                    <button wire:click="toggleSort('name')" type="button" style="display:flex; align-items:center; gap:4px; background:none; border:none; cursor:pointer; font:inherit; color:inherit; padding:0;">
                                        Nama Lokasi
                                        <x-dynamic-component :component="$this->sortIcon('name')" style="width:14px; height:14px;" />
                                    </button>
                                </th>
                                <th>Tipe</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $location)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $location->location_code }}</td>
                                    <td class="font-semibold">{{ $location->location_name }}</td>
                                    <td>
                                        {{ $location->location_type instanceof LocationType
                                            ? $location->location_type->label()
                                            : (LocationType::tryFrom((string) $location->location_type)?->label() ?? '—') }}
                                    </td>
                                    <td>
                                        <a href="{{ LocationResource::getUrl('edit', ['record' => $location]) }}" title="Edit"
                                            style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb;">
                                            <x-heroicon-o-pencil style="width:16px; height:16px; color:#6b7280;" />
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>

