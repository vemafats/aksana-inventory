# CLAUDE.md — Aksana Inventory
# Last Updated: 2026-05-31 23:45:00 WIB (post UAT Day 2)
# Baca seluruh file ini sebelum mengerjakan tugas apapun.

> Mobile-specific (Flutter UI, folder structure, APK) lives in `mobile/CLAUDE_MOBILE.md`. This file is the source of truth for all global business rules, API, roles, database, and web admin.

---

## Ringkasan Proyek

**Aksana Inventory** — sistem inventory dan penjualan untuk bisnis butik berbasis gudang pusat dan lokasi bazar/outlet/toko.

- **Web Admin**: Laravel 11 + Filament v3
- **Mobile**: Flutter + Dart
- **Backend API**: Laravel REST API
- **Database**: PostgreSQL 16
- **Server**: Hostinger VPS 187.127.114.2 (Ubuntu 24.04, Apache2, Redis, Supervisor, SSL)
- **Live URL**: https://app.ftrhijab.id/admin
- **GitHub**: https://github.com/vemafats/aksana-inventory
- **Mobile folder**: aksana-inventory/mobile/ (monorepo)

---

## Technology Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.2 + Laravel 11 |
| Auth | Laravel Sanctum (30 hari) |
| Web Admin | Filament v3 |
| Mobile | Flutter + Dart |
| HTTP Client | Dio |
| State Management | Riverpod |
| Routing | GoRouter |
| Scanner | mobile_scanner |
| Database | PostgreSQL 16 |
| Cache/Queue | Redis 7.0 |
| Server | Apache2 + Supervisor + Certbot SSL |

---

## Konsep Inti

### 1. Katalog ≠ Stok
- Tambah item ke Katalog TIDAK menambah stok
- Stok hanya bertambah via Barang Masuk
- `items` = master referensi
- `stock_balances` = stok aktual

### 2. Barcode = Identitas Varian
Format: `KAT-MRK-MODEL-WRN-UK`
Contoh: `SPT-NIK-AIRMAX-HIT-40`

### 3. Stock Balance = Item + Lokasi + Status
```
UNIQUE (item_id, location_id, stock_status)
stock_status: available | damaged | lost
```

### 4. Wajib StockBalanceService
- TIDAK BOLEH update stock_balances langsung dari controller
- Wajib DB::transaction()
- Setiap mutasi wajib StockMovement record

### 5. Backend Recalculate Semua Harga
Jangan percaya total dari frontend/mobile.

### 6. supplier_cost WAJIB TERSEMBUNYI
- TIDAK BOLEH di API response manapun
- Hanya Owner + password yang bisa lihat
- `latest_supplier_cost` ada di Item.$hidden
- `supplier_cost_snapshot` ada di SalesItem.$hidden

---

## Perubahan Arsitektur Penting

### Employee DIMERGE ke User (2026-05-30)
- Tabel `employees` DEPRECATED
- `users` ditambah: `nik`, `position`
- `location_assignments.employee_id` → `user_id`
- `sales_transactions` ditambah `user_id`
- Jangan buat relasi baru ke tabel `employees`
- `LocationAssignment` belongsTo `User`
- `SalesTransaction` belongsTo `User`

---

## Role dan Permission

| Modul | Owner | Admin | Admin Gudang | PIC Bazar | Sales |
|---|---|---|---|---|---|
| Dashboard | ✓ | ✓ | Terbatas | ✗ | ✗ |
| Master Data | ✓ | ✓ | ✗ | ✗ | ✗ |
| Katalog CRUD | ✓ | ✓ | Terbatas | Lihat | Lihat |
| Barang Masuk | ✓ | ✓ | ✓ | ✗ | ✗ |
| Distribusi | ✓ | ✓ | ✓ | Terbatas | ✗ |
| Transaksi Jual | ✓ | ✓ | ✗ | ✓ | ✓ |
| Stok Opname | ✓ | ✓ | ✓ | ✓ | Terbatas |
| Return Sisa | ✓ | ✓ | ✓ | ✓ | ✗ |
| Laporan | ✓ | ✓ | Terbatas | Terbatas | ✗ |
| Setting | ✓ | ✓ | ✗ | ✗ | ✗ |
| Harga Modal + Margin | Owner+password | ✗ | ✗ | ✗ | ✗ |
| Update Harga Stok tab | Owner only | ✗ | ✗ | ✗ | ✗ |
| Gross Profit report | Owner only | ✗ | ✗ | ✗ | ✗ |

Role values: `owner` | `admin` | `admin_gudang` | `pic_bazar` | `sales`

---

## Harga — Penamaan Konsisten

- **Harga Jual Dasar** = harga modal + margin → `latest_base_selling_price`
- **Harga Jual Lokasi/Bazar** = Harga Jual Dasar + nilai penyesuaian
- Tab web admin: "HARGA JUAL DASAR" (bukan "Harga Jual")
- Margin JUGA dilindungi password sama seperti Harga Modal
- Harga Jual Bazar di form distribusi = AUTO-CALCULATE

### Barang Masuk — Harga Modal

**Penting:** Harga modal HANYA bisa diinput via web admin (Pilihan B).

```
VIA MOBILE (Admin Gudang):
  Scan QR → input qty → foto QC (opsional) → KONFIRMASI
  supplier_cost = 0 (diisi via web)
  Pesan konfirmasi: "Kirim {total_qty} unit ({n} jenis) ke gudang"

VIA WEB ADMIN (Owner/Admin):
  Stok → Tambah Stok → pilih item + qty + harga modal + margin
  → harga jual dasar auto-calculate → SIMPAN
  ATAU
  Stok → Update Harga Stok (Owner only) → edit harga transaksi
  yang masuk dari mobile
```

---

## Alur Bisnis

### Transfer ke Lokasi
```
Admin → pilih item + lokasi → input penyesuaian (opsional) →
harga jual lokasi = auto-calculate →
stok berpindah INSTAN
Status: AKTIF (bukan "Dalam Perjalanan")
```

### Transaksi Jual
- Hanya role yang diizinkan (lihat Role dan Permission)
- Hanya mengambil stok `available` di lokasi penjualan
- Qty jual tidak boleh melebihi stok available lokasi
- Lokasi penjualan dari assignment `user_id` yang login
- Payment method: `cash` | `qris` | `transfer`
- Backend wajib recalculate: subtotal, diskon, grand total, gross profit — jangan percaya total dari client
- Transaksi jual final tidak bisa dibatalkan
- **Diblokir** saat sesi stok opname aktif (`draft` / `pending_validation`)
- Alur UI mobile (scan, cart, bayar): lihat `mobile/CLAUDE_MOBILE.md`

### ⚠️ Stok Opname — ATURAN KRITIS

**Saat sesi opname aktif → SEMUA transaksi DIBLOKIR:**
- Tidak bisa jual
- Tidak bisa barang masuk
- Tidak bisa transfer
- SalesService, StockInService, TransferService wajib throw exception jika ada sesi aktif

**Flow Web Admin (Owner/Admin):**
```
Stok Opname → tab AKTIF & PENDING →
Lihat sesi pending dengan detail:
  ITEM | SISTEM | FISIK | SELISIH | RUSAK
Tombol VALIDASI → stok dioverwrite sesuai fisik
Tombol TOLAK → stok tidak berubah
```

**Status flow:**
```
draft → pending_validation → validated (stok dioverwrite)
                           → rejected  (stok tidak berubah)
```

**Validator:** Owner dan Admin saja.

**Single Session Rule:**
- Hanya 1 sesi aktif (draft/pending_validation) di satu waktu
- Cek GET /api/stock-opnames/active sebelum buat sesi baru

**API Stok Opname:**
```
GET  /api/stock-opnames/active    → cek sesi aktif (register SEBELUM {id})
POST /api/stock-opnames           → buat sesi baru { location_id, notes }
GET  /api/stock-opnames/{id}      → detail sesi
POST /api/stock-opnames/{id}/items → tambah item
  body: { item_id, physical_qty, damaged_qty, system_qty }
POST /api/stock-opnames/{id}/submit   → submit untuk validasi
POST /api/stock-opnames/{id}/validate → validasi (Owner/Admin)
POST /api/stock-opnames/{id}/reject   → tolak (Owner/Admin)
```

**Movement types untuk opname:**
- `stock_opname_plus` — fisik > sistem (stok bertambah)
- `stock_opname_lost` — fisik < sistem (stok berkurang)
- `available_to_damaged` — item rusak ditemukan

- Alur scan/input mobile: lihat `mobile/CLAUDE_MOBILE.md`

---

## PostgreSQL — Rules Wajib

### Timezone Queries
```php
// BENAR — gunakan helper:
$this->whereReportDate($query, 'transaction_date', $today)
// Yang menjalankan:
->whereRaw("DATE(column AT TIME ZONE 'Asia/Jakarta') = ?", [$date])

// SALAH:
->whereDate('transaction_date', $date)
```

### GROUP BY
```php
// BENAR:
->selectRaw('item_id, SUM(qty) as total_qty')
->groupBy('item_id')

// SALAH (error di PostgreSQL):
->groupBy('item_id')
```

### UUID Morphs
`personal_access_tokens.tokenable_id` harus `varchar(255)`.
Gunakan `uuidMorphs()` bukan `morphs()` di migration.

### Blade Templates
TIDAK BOLEH ada `use` statement di dalam `@php` block.
Gunakan fully qualified class names: `\App\Models\Item::all()`

---

## Struktur Web Admin

### Sub-tab Stok
```
RINGKASAN         → stat cards + tabel per item per lokasi
+ TAMBAH STOK     → form lengkap + harga modal + margin
RIWAYAT PERGERAKAN → movement table
HARGA JUAL DASAR  → harga + margin (modal & margin butuh password)
UPDATE HARGA STOK → Owner only, edit harga transaksi dari mobile
```

### Sub-tab Distribusi
```
TRANSFER KELUAR   → status: AKTIF (transfer instan)
RETUR MASUK       → form return dari lokasi ke gudang
LOKASI PENJUALAN  → master lokasi
RIWAYAT
```

### Sub-tab Penjualan
```
RINGKASAN → per-lokasi + metode pembayaran
RIWAYAT
TOP PRODUK
```

### Stok Opname Web
```
AKTIF & PENDING → sesi yang perlu divalidasi + tombol VALIDASI/TOLAK
RIWAYAT         → sesi tervalidasi/ditolak
```

### Laporan Lengkap
```
Laporan Stok     → per item per lokasi, status AMAN/KRITIS/HABIS
Laporan Penjualan → per lokasi + date filter
Laporan Gross Profit → Owner only, total sales, HPP, profit, margin %
```

### Analytics
```
Laporan Lengkap
Setting          → User Management (CRUD) + Roles + Menu Access
Riwayat Pergerakan
```

---

## Deployment

### Deploy Command (VPS)
```bash
/root/deploy.sh
# git reset --hard + composer + migrate + cache + restart workers
```

### VPS Info
```
IP: 187.127.114.2
OS: Ubuntu 24.04, Apache2, PHP 8.2, PostgreSQL 16, Redis 7.0
SSL: Let's Encrypt (expire 2026-08-27)
```

### Setelah Deploy
Jika ada migrate + seed yang perlu dijalankan:
```bash
cd /var/www/aksana-inventory
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=LocationAssignmentSeeder --force
```

---

## Bug Fixes Status

| ID | Error | Status |
|---|---|---|
| B001 | PostgreSQL GROUP BY | ✅ Fixed |
| B002 | UUID vs bigint personal_access_tokens | ✅ Fixed |
| B003 | Scan QR tidak merespons di HP | ✅ Fixed |
| B004 | Harga Rp 0 di cart | ✅ Fixed |
| B005 | Location/employee tidak tersedia | ✅ Fixed |
| B006 | API login 500 | ✅ Fixed |
| B007 | Dropdown lokasi kosong di Distribusi | ✅ Fixed |
| B008 | Report mobile-summary selalu 0 | ✅ Fixed |
| B009 | Git pull conflict di VPS | ✅ Fixed |
| B010 | Route [login] not defined | ✅ Non-fatal |
| B011 | Pesan konfirmasi qty barang masuk | ✅ Fixed |
| B012 | Tombol SIMPAN tidak ada di Tambah Stok | ✅ Fixed |

---

## Revisi Status

| ID | Item | Status |
|---|---|---|
| R001 | Status transfer "AKTIF" | ✅ Fixed |
| R002 | Tab "Harga Jual Dasar" | ✅ Fixed |
| R003 | Margin dilindungi password | ✅ Fixed |
| R004 | Harga Jual Bazar auto-calculate | ✅ Fixed |
| R005 | Tombol Verifikasi di dialog | ✅ Fixed |
| R006 | STAT tab scroll/tap | ✅ Fixed |
| R007 | Setting page User CRUD | ✅ Fixed |

---

## Fitur Baru (F-series)

| ID | Fitur | Status |
|---|---|---|
| F001 | Harga modal HANYA via web admin | ✅ Implemented |
| F002 | Form Tambah Stok web lengkap | ✅ Implemented |
| F003 | Upload foto barang masuk (web+mobile) | ✅ Implemented |
| F004 | Stok Ringkasan per lokasi | ✅ Implemented |
| F005 | Edit harga transaksi dari mobile (tab Update Harga Stok) | ✅ Implemented |

---

## Default Credentials

| Email | Password | Role | NIK | Lokasi |
|---|---|---|---|---|
| owner@aksana.id | password | owner | USR-001 | - |
| admin@aksana.id | password | admin | USR-002 | - |
| gudang@aksana.id | password | admin_gudang | USR-003 | Gudang Pusat |
| picbazar@aksana.id | password | pic_bazar | USR-004 | Bintaro Avenue |
| sales@aksana.id | password | sales | USR-005 | Creative Box Bintaro |

---

## Coding Rules

1. UUID primary keys di semua tabel
2. supplier_cost TIDAK BOLEH di response apapun
3. DB::transaction() untuk semua mutasi stok
4. StockBalanceService untuk semua stock_balances
5. Backend recalculate semua harga
6. PostgreSQL: gunakan whereReportDate() helper
7. PostgreSQL: selectRaw sebelum groupBy
8. Jangan buat relasi baru ke tabel employees
9. API /api/me harus return location_id dan location_name
10. Transfer bersifat instan — status AKTIF
11. Format angka: JT (juta), RB (ribu) — web dan mobile
12. TIDAK BOLEH ada `use` statement di @php block Blade
13. Saat opname aktif: SEMUA transaksi diblokir
14. Margin dan Harga Modal sama-sama butuh password Owner
15. Update Harga Stok tab hanya visible untuk role Owner
