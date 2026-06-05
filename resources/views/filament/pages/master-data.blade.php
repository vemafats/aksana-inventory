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
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-[var(--aksana-muted)]">Kelola data referensi · 7 tabel master</p>
        @if ($selectedTab === 'categories')
            <a href="{{ CategoryResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH KATEGORI</a>
        @elseif ($selectedTab === 'brands')
            <a href="{{ BrandResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH MERK</a>
        @elseif ($selectedTab === 'models')
            <a href="{{ ProductModelResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH MODEL</a>
        @elseif ($selectedTab === 'colors')
            <a href="{{ ColorResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH WARNA</a>
        @elseif ($selectedTab === 'sizes')
            <a href="{{ SizeResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH UKURAN</a>
        @elseif ($selectedTab === 'employees')
            <a href="{{ UserResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH KARYAWAN</a>
        @elseif ($selectedTab === 'locations')
            <a href="{{ LocationResource::getUrl('create') }}" class="aksana-tab aksana-tab-active">+ TAMBAH LOKASI</a>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[240px_1fr]">
        <aside class="rounded-lg border border-[var(--aksana-border)] bg-white p-2">
            <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-[0.1em] text-[#3d4a5c]">
                Tabel Master
            </p>
            <nav class="flex flex-col gap-1">
                @foreach ($this->getTabs() as $key => $tab)
                    <button
                        type="button"
                        wire:click="selectTab('{{ $key }}')"
                        @class([
                            'aksana-master-tab flex w-full items-center gap-2 rounded-md text-left transition',
                            'aksana-master-tab-active bg-[var(--aksana-void)] text-white' => $selectedTab === $key,
                            'text-[var(--aksana-void)] hover:bg-gray-100' => $selectedTab !== $key,
                        ])
                    >
                        <x-dynamic-component :component="$tab['icon']" @class([
                            'h-3.5 w-3.5 shrink-0',
                            'text-white' => $selectedTab === $key,
                        ]) />
                        <span class="flex-1">{{ $tab['label'] }}</span>
                        <span @class([
                            'font-mono text-[11px]',
                            'text-white/70' => $selectedTab === $key,
                            'text-[var(--aksana-muted)]' => $selectedTab !== $key,
                        ])>
                            {{ str_pad((string) $this->getTabCount($key), 2, '0', STR_PAD_LEFT) }}
                        </span>
                    </button>
                @endforeach
            </nav>
        </aside>

        <section class="overflow-hidden rounded-lg border border-[var(--aksana-border)] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--aksana-border)] px-4 py-3">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-table-cells class="h-4 w-4 text-[var(--aksana-muted)]" />
                    <h2 class="text-base font-bold text-[var(--aksana-void)]">{{ $this->getActiveTabLabel() }}</h2>
                    <span class="text-[13px] font-medium text-[#3d4a5c]">· {{ $this->getTabCount($selectedTab) }} entri</span>
                </div>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari..."
                    class="w-full max-w-xs rounded-md border border-[var(--aksana-border)] px-3 py-2 text-sm"
                />
            </div>

            <div class="overflow-x-auto p-4">
                @if ($selectedTab === 'categories')
                    @php
                        $rows = Category::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)))
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Kategori</th>
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
                                        <a href="{{ CategoryResource::getUrl('edit', ['record' => $cat]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'brands')
                    @php
                        $rows = Brand::query()
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Merk</th>
                                <th>Negara</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $brand)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $this->displayCode($brand->name) }}</td>
                                    <td class="font-semibold">{{ $brand->name }}</td>
                                    <td class="text-[var(--aksana-muted)]">—</td>
                                    <td>
                                        <a href="{{ BrandResource::getUrl('edit', ['record' => $brand]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'models')
                    @php
                        $rows = ProductModel::query()
                            ->with('category')
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Model</th>
                                <th>Kategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $model)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $this->displayCode($model->name) }}</td>
                                    <td class="font-semibold">{{ $model->name }}</td>
                                    <td>{{ $model->category?->name ?? '—' }}</td>
                                    <td>
                                        <a href="{{ ProductModelResource::getUrl('edit', ['record' => $model]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'colors')
                    @php
                        $rows = Color::query()
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Warna</th>
                                <th>Hex</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $color)
                                @php $hex = $color->code ?? '#CCCCCC'; @endphp
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ $this->displayCode($color->name) }}</td>
                                    <td class="font-semibold">{{ $color->name }}</td>
                                    <td>
                                        <span class="inline-flex items-center gap-2 font-mono text-xs">
                                            <span style="width:14px;height:14px;border-radius:4px;background:{{ $hex }};border:1px solid var(--aksana-border)"></span>
                                            {{ strtoupper($hex) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ ColorResource::getUrl('edit', ['record' => $color]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'sizes')
                    @php
                        $rows = Size::query()
                            ->when($like, fn ($q) => $q->where('name', 'like', $like))
                            ->orderBy('sort_order')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Label</th>
                                <th>Sistem</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $size)
                                <tr>
                                    <td class="aksana-mono text-[var(--aksana-muted)]">{{ str_pad((string) $size->sort_order, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="font-semibold">{{ $size->name }}</td>
                                    <td>{{ match ($size->size_type) { 'clothing' => 'Pakaian', 'shoes' => 'Sepatu', default => $size->size_type ?? '—' } }}</td>
                                    <td>
                                        <a href="{{ SizeResource::getUrl('edit', ['record' => $size]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'employees')
                    @php
                        $rows = User::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('nik', 'like', $like)->orWhere('email', 'like', $like)))
                            ->orderBy('name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>NIK</th>
                                <th>Nama</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $staff)
                                <tr>
                                    <td class="aksana-mono">{{ $staff->nik ?? '—' }}</td>
                                    <td class="font-semibold">{{ $staff->name }}</td>
                                    <td>{{ $staff->role->label() }}</td>
                                    <td>
                                        <a href="{{ UserResource::getUrl('edit', ['record' => $staff]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @elseif ($selectedTab === 'locations')
                    @php
                        $rows = Location::query()
                            ->when($like, fn ($q) => $q->where(fn ($q) => $q->where('location_name', 'like', $like)->orWhere('location_code', 'like', $like)))
                            ->orderBy('location_name')
                            ->get();
                    @endphp
                    <table class="aksana-table w-full">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Lokasi</th>
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
                                        <a href="{{ LocationResource::getUrl('edit', ['record' => $location]) }}" class="text-[13px] font-semibold text-[var(--aksana-void)]">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-[var(--aksana-muted)]">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>

