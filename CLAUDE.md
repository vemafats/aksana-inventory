# CLAUDE.md — Aksana Inventory

Ini adalah file konteks utama untuk Claude Code. Baca seluruh file ini sebelum mengerjakan tugas apapun.

---

## Ringkasan Proyek

**Aksana Inventory** adalah sistem inventory dan penjualan untuk bisnis butik berbasis gudang pusat dan lokasi bazar/outlet/toko.

- **Web Admin**: Laravel + Filament (manajemen penuh)
- **Mobile App**: Flutter + Dart (operasional lapangan)
- **Backend API**: Laravel REST API
- **Database**: PostgreSQL
- **Server**: Hostinger VPS (Ubuntu, Nginx, PHP-FPM, Redis, Supervisor, SSL)

---

## Technology Stack


| Layer                | Teknologi                                |
| -------------------- | ---------------------------------------- |
| Backend              | PHP 8.2 + Laravel 11                     |
| Auth                 | Laravel Sanctum                          |
| Web Admin            | Laravel + Filament v3                    |
| Mobile               | Flutter + Dart                           |
| HTTP Client (mobile) | Dio                                      |
| State Management     | Riverpod                                 |
| Routing (mobile)     | GoRouter                                 |
| QR Code Generator    | simplesoftwareio/simple-qrcode (Laravel) |
| Barcode Scanner      | mobile_scanner                           |
| Camera               | camera package                           |
| Database             | PostgreSQL 18                            |
| Queue/Cache          | Redis                                    |
| Server               | Hostinger VPS                            |


---

## Konsep Inti — WAJIB DIPAHAMI

### 1. Konsep CRUD — Digunakan di Seluruh Aplikasi

CRUD (Create, Read, Update, Delete) adalah pola dasar yang diterapkan di semua modul:

- **Create**: tambah item baru (katalog, barang masuk, transfer, penjualan)
- **Read**: tampil list + detail (semua modul, dengan filter/search/pagination)
- **Update**: edit data (katalog, master data, harga)
- **Delete**: hapus data (soft delete untuk katalog dan master data, hard delete tidak digunakan)

Di web admin, CRUD diimplementasi via **Filament Resources** (form + table + actions).
Di API, CRUD diimplementasi via **RESTful endpoints** (GET/POST/PUT/DELETE).
Di mobile, tidak ada akses ke CRUD master data — hanya operasional (scan, transaksi, opname).

---

### 2. Katalog ≠ Stok

- Menambah item ke **Katalog** TIDAK menambah stok.
- Stok hanya bertambah melalui **Barang Masuk & QC**.
- `items` table = master referensi barang.
- `stock_balances` table = jumlah stok aktual.

### 3. Barcode = Identitas Varian

- Barcode mewakili varian item (merk + model + warna + ukuran), BUKAN unit satuan.
- Contoh: `SPT-NIK-AIRMAX-HIT-40` = semua Nike Air Max Hitam ukuran 40.
- Barcode harus unik di tabel `items`.

### 4. Stock Balance = Item + Lokasi + Status

```
stock_balances: UNIQUE (item_id, location_id, stock_status)
stock_status: available | damaged | lost
```

### 5. Semua Mutasi Stok Wajib Stock Movement

- TIDAK BOLEH update `stock_balances` langsung dari controller.
- Selalu gunakan `StockBalanceService` yang membuat record `stock_movements`.
- Setiap perubahan stok harus atomic menggunakan `DB::transaction()`.

### 6. Harga = Snapshot

- Harga modal, harga jual, dan diskon pada setiap transaksi harus disimpan sebagai snapshot.
- Snapshot tidak boleh berubah saat harga master diupdate.

### 7. Backend Menghitung Ulang Semua Total

- JANGAN percaya total yang dikirim dari mobile/frontend.
- Backend wajib menghitung ulang: harga, diskon, grand total, gross profit.

---

## Role dan Permission


| Modul              | Owner        | Admin | Admin Gudang | PIC Bazar | Sales    |
| ------------------ | ------------ | ----- | ------------ | --------- | -------- |
| Dashboard Admin    | ✓            | ✓     | Terbatas     | ✗         | ✗        |
| Master Data        | ✓            | ✓     | ✗            | ✗         | ✗        |
| Katalog (CRUD)     | ✓            | ✓     | Terbatas     | Lihat     | Lihat    |
| Cetak QR Code      | ✓            | ✓     | ✓            | ✗         | ✗        |
| Barang Masuk & QC  | ✓            | ✓     | ✓            | ✗         | ✗        |
| Distribusi Stok    | ✓            | ✓     | ✓            | Terbatas  | ✗        |
| Transaksi Jual     | ✓            | ✓     | ✗            | ✓         | ✓        |
| Stok Opname        | ✓            | ✓     | ✓            | ✓         | Terbatas |
| Return Barang Sisa | ✓            | ✓     | ✓            | ✓         | ✗        |
| Tutup Bazar        | ✓            | ✓     | ✓            | ✓         | ✗        |
| Laporan Lengkap    | ✓            | ✓     | Terbatas     | Terbatas  | ✗        |
| Laporan Ringkas    | ✓            | ✓     | ✓            | ✓         | ✓        |
| Harga Modal (Stok) | ✓ Owner saja | ✗     | ✗            | ✗         | ✗        |
| Setting Sistem     | ✓            | ✓     | ✗            | ✗         | ✗        |
| Low-Stock Badge    | ✓            | ✓     | ✓            | ✓         | ✓        |


---

## Business Rules Final

### Katalog

- Katalog dibuat sebelum barang datang dari supplier.
- Nama item di-generate otomatis: `{Merk} {Model} {Warna} {Ukuran}` — bisa diedit user.
- SKU format: `{KAT}-{MERK}-{MODEL}-{WARNA}-{UKURAN}`.
- Barcode = SKU (atau custom), harus unik.
- Foto katalog adalah foto umum tipe barang, bukan foto transaksi.
- **Format barcode adalah QR Code** — bukan Code128 atau barcode linear lain.
- QR Code di-generate menggunakan package `simplesoftwareio/simple-qrcode`.
- Menu **Cetak QR Code** tersedia di dalam halaman detail Katalog (web admin).
- Layout cetak: A4, grid label dengan QR Code + nama item + kode SKU per label.
- **Harga modal (supplier_cost) TIDAK ditampilkan di halaman Katalog** — bahkan untuk Owner sekalipun. Harga modal hanya dapat diakses di menu Stok dengan verifikasi password.

### Barang Masuk & QC

- Scan barcode → barcode wajib ada di katalog. Jika tidak ditemukan, tolak dan arahkan buat katalog dulu.
- `qty_received = qty_available + qty_damaged` (validasi wajib).
- `qty_available` → stok Available di Gudang Pusat.
- `qty_damaged` → stok Damaged di Gudang Pusat.
- Foto bukti masuk + timestamp otomatis wajib.
- Satu transaksi barang masuk dapat berisi multiple item (scan barcode bergantian).

### Distribusi / Transfer

- Transfer hanya dari Gudang Pusat ke Bazar/Outlet/Toko, atau sebaliknya (return).
- Hanya stok `available` yang bisa ditransfer.
- Harga jual bazar disimpan snapshot di `transfer_items.bazar_selling_price`.
- `bazar_adjust_type`: none | nominal | percentage | manual.

### Penjualan

- Penjualan hanya mengambil stok `available` di lokasi penjualan.
- Qty jual tidak boleh melebihi stok available lokasi.
- Diskon: per item dan/atau per transaksi.
- Payment method: cash | qris | transfer.
- Backend menghitung ulang: subtotal, diskon, grand total, gross profit.
- Gross profit per item = `total_after_discount - (supplier_cost_snapshot × qty)`.
- Transaksi jual yang final tidak bisa dibatalkan.

### Retur Barang dari Customer

- Tidak ada fitur retur jual standar.
- Jika ada case khusus, barang dicatat sebagai stok **Damaged** via Stock Opname manual.
- Gross profit transaksi original tidak berubah retroaktif.

### Stok Opname

- Pilih lokasi → scan barcode → input qty fisik.
- Sistem menampilkan qty sistem saat itu.
- Koreksi plus → `adjustment_plus` movement.
- Koreksi minus → `adjustment_lost` movement, qty hilang masuk `lost`.
- Barang rusak ditemukan → `available_to_damaged` movement.
- Foto + timestamp wajib.

### Harga Modal — Akses Terbatas Owner dengan Verifikasi Password

- Harga modal (`supplier_cost`) adalah informasi **rahasia bisnis**.
- **Hanya ditampilkan di menu Stok (web admin)**, tidak di Katalog, tidak di riwayat transaksi, tidak di mobile app.
- **Hanya Owner** yang bisa mengakses harga modal. Role lain tidak punya akses sama sekali.
- **Alur akses di web admin:**
  1. Owner buka halaman Stok
  2. Data stok tampil normal (qty, lokasi, status) — tanpa kolom harga modal
  3. Owner klik tombol **"Lihat Harga Modal"**
  4. Muncul dialog → input password (sama dengan password login)
  5. Backend verifikasi via `POST /api/verify-password`
  6. Jika benar → backend return `cost_view_token` (valid 15 menit) → kolom harga modal muncul di tabel
  7. Jika salah → tampil pesan error, kolom tidak muncul
- **API:** endpoint stok TIDAK menyertakan `supplier_cost` dalam response biasa. Field hanya dikirim jika request menyertakan header `X-Cost-View-Token` yang valid dan belum expired.
- Di mobile app: harga modal tidak ditampilkan di mana pun dan tidak ada endpoint untuk mengaksesnya.
- Gross profit di laporan tetap dihitung backend secara internal tanpa user perlu melihat harga modal satuan.

### Rekonsiliasi & Tutup Bazar

- Tutup bazar = UPDATE `locations.status = 'closed'`.
- Syarat tutup: semua `stock_balances.qty` untuk lokasi tersebut = 0.
- Role yang bisa tutup: Owner, Admin, Admin Gudang, PIC Bazar. **Sales tidak bisa.**
- UI: tombol "Tutup Bazar" → konfirmasi dialog → tap "Tutup" sekali lagi (double confirm).

### Notifikasi Stok Menipis

- Ditampilkan sebagai badge di dashboard (bukan push notification).
- Threshold **tetap di angka 1** (tidak bisa dikonfigurasi user) — item dianggap menipis jika qty available = 1 atau kurang.
- `settings.low_stock_threshold` = 1 (hardcoded default, tidak perlu UI setting untuk mengubah ini).
- Owner/Admin → lihat semua lokasi.
- Admin Gudang → lihat gudang pusat.
- PIC Bazar & Sales → lihat lokasi yang di-assign ke mereka.
- API: `GET /api/reports/low-stock-items`.

---

## Struktur Database Utama

```
users, categories, brands, product_models, colors, sizes
employees, locations, location_assignments
items (katalog)
stock_balances (item + lokasi + status)
stock_movements (audit trail semua mutasi stok)
stock_in_transactions, stock_in_items
transfer_transactions, transfer_items
sales_transactions, sales_items
stock_opname_transactions, stock_opname_items
photos
settings
```

Unique key kritis:

- `items`: barcode UNIQUE, sku UNIQUE
- `stock_balances`: UNIQUE(item_id, location_id, stock_status)
- `stock_movements`: movement_number UNIQUE

---

## Struktur Folder Laravel

```
app/
  Models/
  Http/
    Controllers/Api/
    Controllers/Admin/
    Requests/
  Services/
    CatalogService.php
    StockBalanceService.php
    StockMovementService.php
    StockInService.php
    TransferService.php
    SalesService.php
    StockOpnameService.php
    PriceCalculationService.php
    PhotoService.php
    ReportService.php
  Enums/
    StockStatus.php         → AVAILABLE, DAMAGED, LOST
    MovementType.php        → STOCK_IN_AVAILABLE, STOCK_IN_DAMAGED, TRANSFER_AVAILABLE,
                              SALE, STOCK_OPNAME_PLUS, STOCK_OPNAME_LOST,
                              AVAILABLE_TO_DAMAGED, RETURN_TO_WAREHOUSE
    DiscountType.php        → NONE, NOMINAL, PERCENTAGE
    LocationType.php        → CENTRAL_WAREHOUSE, BAZAR, OUTLET, STORE, EVENT
    LocationStatus.php      → DRAFT, ACTIVE, CLOSED, CANCELLED
  Policies/
  DTO/
```

---

## API Endpoints

### Auth

```
POST   /api/login
POST   /api/logout
GET    /api/me
POST   /api/verify-password   ← verifikasi password Owner untuk akses harga modal
                                 request: { password }
                                 response: { cost_view_token, expires_at }
                                 hanya bisa diakses role: owner
```

### Catalog

```
GET    /api/catalogs
POST   /api/catalogs
GET    /api/catalogs/{id}
PUT    /api/catalogs/{id}
DELETE /api/catalogs/{id}
GET    /api/catalogs/by-barcode/{barcode}
GET    /api/catalogs/{id}/qrcode          ← generate & return QR Code image (PNG)
POST   /api/catalogs/{id}/generate-qrcode ← regenerate QR Code dan simpan
```

> Response katalog TIDAK pernah menyertakan field supplier_cost dalam bentuk apapun.

### Stock In

```
POST   /api/stock-in
GET    /api/stock-in
GET    /api/stock-in/{id}
```

### Stock

```
GET    /api/stocks
GET    /api/stocks/warehouse
GET    /api/stocks/location/{location_id}
GET    /api/stocks/item/{item_id}
```

> Semua endpoint stok TIDAK menyertakan supplier_cost dalam response default.
> Jika header `X-Cost-View-Token: {token}` disertakan DAN role adalah owner DAN token valid,
> maka response menyertakan field `supplier_cost` per item. Token dari POST /api/verify-password.

### Transfer

```
POST   /api/transfers
GET    /api/transfers
GET    /api/transfers/{id}
POST   /api/transfers/{id}/complete
POST   /api/transfers/{id}/cancel
```

### Sales

```
POST   /api/sales
GET    /api/sales
GET    /api/sales/{id}
```

### Stock Opname

```
POST   /api/stock-opnames
GET    /api/stock-opnames
GET    /api/stock-opnames/{id}
POST   /api/stock-opnames/{id}/complete
```

### Location

```
POST   /api/locations/{id}/close
```

### Photo

```
POST   /api/photos
GET    /api/photos/{id}
```

### Reports

```
GET    /api/reports/dashboard-summary
GET    /api/reports/warehouse-stock
GET    /api/reports/location-stock
GET    /api/reports/total-capital
GET    /api/reports/gross-profit
GET    /api/reports/best-selling-products
GET    /api/reports/product-summary
GET    /api/reports/sales-by-location
GET    /api/reports/sales-by-employee
GET    /api/reports/low-stock-items
```

---

## Aturan Coding Wajib

1. **Jangan update `stock_balances` dari controller.** Selalu pakai `StockBalanceService`.
2. **Setiap mutasi stok wajib membuat `stock_movements`.** Gunakan `StockMovementService`.
3. **Gunakan `DB::transaction()` untuk stock_in, transfer, sales, stock_opname.**
4. **Jangan percaya total dari frontend.** Hitung ulang di backend.
5. **Primary key UUID.** Gunakan `Str::uuid()` atau `$table->uuid('id')->primary()`.
6. **Form Request validation** untuk semua endpoint.
7. **Tulis test** untuk setiap Service class.
8. **Harga transaksi = snapshot.** Jangan referensi harga master saat query histori.
9. **Satu task = satu fokus.** Jangan gabungkan banyak modul dalam satu sesi.
10. **Tanya dulu jika ada ambiguitas** sebelum mulai implementasi.

---

## Deployment Checklist (Laravel)

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart all
```

## Deployment Checklist (Flutter)

```bash
flutter clean
flutter pub get
flutter build apk --release
```

---

## Referensi Dokumen

- `docs/FSD.md` — Functional Specification Document
- `docs/TSD.md` — Technical Specification Document
- `docs/CLAUDE_TASKS.md` — Task breakdown per milestone

