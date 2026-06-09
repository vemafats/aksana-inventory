# CLAUDE.md — Aksana Inventory
# Last Updated: 2026-06-09 (v1.4.0 — F025 cetak QR katalog + label 30×20mm)
# Baca seluruh file ini sebelum mengerjakan tugas apapun.

> Mobile-specific (Flutter UI, folder structure, APK) lives in `mobile/CLAUDE_MOBILE.md`. This file is the source of truth for all global business rules, API, roles, database, and web admin.

---

## Ringkasan Proyek

**Aksana Inventory** — sistem inventory dan penjualan untuk bisnis butik berbasis gudang pusat dan lokasi bazar/outlet/toko.

- **Web Admin**: Laravel 11 + Filament v3
- **Mobile**: Flutter + Dart (v1.4.0+16)
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
| Excel Export | phpoffice/phpspreadsheet |
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

### 7. Event-Based Assignment
- Lokasi penjualan diaktifkan melalui **Event** (`events` + `event_user`)
- Event = lokasi + assigned users (`pic_bazar` / `sales`) + tanggal mulai/selesai + status
- **Gudang pusat permanen** — di luar sistem event
- Tabel `location_assignments` **DEPRECATED** — diganti `events` + `event_user`
- User ↔ lokasi penjualan melalui Event aktif, bukan direct assignment
- Master Data Lokasi: hanya nama, tipe, alamat, status (tanpa tanggal di UI)
- Master Data User: nama, email, role (tidak terikat lokasi)

### 8. Event Expenses (Biaya Event)
- Setiap event bisa punya biaya operasional (`event_expenses`)
- Input manual: deskripsi, amount, tanggal
- **Net Profit = Gross Profit − Total Biaya Event**
- Web: RelationManager biaya di Event edit + kolom total di list
- API: CRUD via `/api/events/{event}/expenses`

---

## Perubahan Arsitektur Penting

### Employee DIMERGE ke User (2026-05-30)
- Tabel `employees` DEPRECATED
- `users` ditambah: `nik`, `position`
- `sales_transactions` ditambah `user_id`
- Jangan buat relasi baru ke tabel `employees`
- `SalesTransaction` belongsTo `User`

### Event-Based Assignment (2026-06)
- Tabel `location_assignments` **DEPRECATED** — jangan buat relasi baru ke tabel ini
- `locations.start_date` / `end_date` dihapus dari UI (kolom DB masih ada untuk backward compat)
- Transfer wajib pilih **Event** — `event_id` di `transfer_transactions`; `to_location_id` di-resolve dari event
- Return barang sisa via `POST /api/returns` dengan `event_id`
- `EventService::endEvent()` — validasi semua stok di lokasi event harus 0 sebelum event diakhiri
- Mobile: lokasi aktif dari event yang di-assign ke user (`GET /api/events/my-active`)

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
| Excel export Gross Profit | Owner only | ✗ | ✗ | ✗ | ✗ |
| Excel export Stok/Penjualan | ✓ | ✓ | ✗ | ✗ | ✗ |

Role values: `owner` | `admin` | `admin_gudang` | `pic_bazar` | `sales`

---

## Harga — Penamaan Konsisten

- **Harga Jual Dasar** = harga modal + margin → `latest_base_selling_price`
- **Harga Jual Lokasi/Bazar** = Harga Jual Dasar + nilai penyesuaian
- Tab web admin: "HARGA JUAL DASAR" (bukan "Harga Jual")
- Margin JUGA dilindungi password sama seperti Harga Modal
- Harga Jual Bazar di form distribusi = AUTO-CALCULATE

### Propagasi Harga Terbaru
- Saat harga item diupdate, `bazar_selling_price` pada `transfer_items` transfer **aktif** direcalculate
- Adjustment per lokasi tetap berlaku (`nominal` / `percentage`)
- Harga **manual** (`bazar_adjust_type = manual`) tidak diubah otomatis
- **Format angka:** SELALU format lengkap — `Rp 2.200.000` — **TIDAK BOLEH** JT/RB/M/K

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

### Transfer ke Lokasi (Event-Based)
```
Admin → pilih Event tujuan (bukan lokasi langsung) →
pilih item + penyesuaian harga (opsional) →
harga jual lokasi = auto-calculate →
stok berpindah INSTAN ke lokasi event
Status: AKTIF (bukan "Dalam Perjalanan")
event_id wajib di transfer_transactions
```

### Event & Return Flow
```
Owner/Admin buat Event → assign PIC Bazar + Sales →
Transfer keluar ke event → penjualan di lokasi event →
Return sisa: POST /api/returns { event_id, items[] } →
stok kembali ke Gudang Pusat →
endEvent(): validasi stok lokasi = 0 → status event = ended
```

### Event Expenses
```
Owner/Admin input biaya di Event (web RelationManager atau API) →
Laporan Gross Profit: Total Biaya per periode →
Net Profit = Gross Profit − Total Biaya
```

### Transaksi Jual
- Hanya role yang diizinkan (lihat Role dan Permission)
- Hanya mengambil stok `available` di lokasi penjualan (dari event aktif di mobile)
- Qty jual tidak boleh melebihi stok available lokasi
- Payment method: `cash` | `qris` | `transfer` (Tunai, QRIS, Transfer Bank)
- Backend wajib recalculate: subtotal, diskon, grand total, gross profit — jangan percaya total dari client
- Transaksi jual final tidak bisa dibatalkan
- **Diblokir** saat sesi stok opname aktif (`draft` / `pending_validation`)
- Alur UI mobile: lihat `mobile/CLAUDE_MOBILE.md`

### ⚠️ Stok Opname — ATURAN KRITIS

**Lokasi opname: SELALU Gudang Pusat** (web dan mobile).

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
Tombol BATALKAN DRAFT → hapus sesi draft
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

**Movement types untuk opname:**
- `stock_opname_plus` — fisik > sistem (stok bertambah)
- `stock_opname_lost` — fisik < sistem (stok berkurang)
- `available_to_damaged` — item rusak ditemukan

- Alur scan/input mobile: lihat `mobile/CLAUDE_MOBILE.md`

---

## API Endpoints

### Event
```
GET    /api/events                    → list events (filter status)
POST   /api/events                    → create event + assignments
GET    /api/events/my-active          → event aktif untuk user login
GET    /api/events/{event}            → detail + total_expenses
PUT    /api/events/{event}            → update event
POST   /api/events/{event}/end        → akhiri event (stok harus 0)
```

### Event Expenses
```
GET    /api/events/{event}/expenses
POST   /api/events/{event}/expenses
PUT    /api/events/{event}/expenses/{expense}
DELETE /api/events/{event}/expenses/{expense}
```
Auth: Sanctum. Create/update/delete: Owner + Admin (`canManageEvents()`).

### Return
```
POST   /api/returns                   → return sisa dari event ke gudang
  body: { event_id, items: [{ item_id, qty_available, qty_damaged }] }
```

### Stok Opname
```
GET  /api/stock-opnames/active        → cek sesi aktif (register SEBELUM {id})
POST /api/stock-opnames               → buat sesi baru { location_id, notes }
GET  /api/stock-opnames/{id}          → detail sesi
POST /api/stock-opnames/{id}/items    → tambah item
  body: { item_id, physical_qty, damaged_qty, system_qty }
POST /api/stock-opnames/{id}/submit   → submit untuk validasi
POST /api/stock-opnames/{id}/validate → validasi (Owner/Admin)
POST /api/stock-opnames/{id}/reject   → tolak (Owner/Admin)
```

### Report Export (Web — auth session)
```
GET /admin/reports/gross-profit/export?from=&to=   → Owner only
GET /admin/reports/stock/export                  → Owner + Admin
GET /admin/reports/sales/export?from=&to=        → Owner + Admin
```

Endpoint lain (catalog, stock-in, transfer, sales, reports): lihat `docs/TSD.md` atau route list.

---

## PostgreSQL — Rules Wajib

### Timezone Queries
```php
// TIMESTAMP (sales_transactions.transaction_date, stock_movements.created_at):
TimezoneQuery::whereTimestampEquals($query, 'transaction_date', $today);
// → DATE(column AT TIME ZONE 'Asia/Jakarta') = ?

// DATE (transfer_date, opname_date, expense_date):
TimezoneQuery::whereDateColumnFrom($query, 'expense_date', $dateFrom);
// JANGAN pakai AT TIME ZONE pada kolom DATE — akan geser 1 hari di PostgreSQL.

// SALAH untuk TIMESTAMP:
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

### Dashboard
```
Stat cards + 3 donut charts:
  Stok per Status | Penjualan Hari Ini | Stok per Lokasi
```

### Master Data
```
Sidebar layout — tab: Kategori, Merk, Model, Warna, Ukuran, Lokasi, User
Lokasi: tanpa tanggal mulai/selesai di form
User: tanpa assignment lokasi langsung
```

### Katalog
```
tombol "Cetak QR Code" di header → modal ZPL Zebra GC420 (sama seperti Stok)
```

### Sub-tab Stok
```
RINGKASAN         → stat cards (Total SKU, Total Unit, Inventory, Stok Kritis)
                    + tabel per item per lokasi + slow moving + Cetak QR Code
+ TAMBAH STOK     → form lengkap + harga modal + margin
RIWAYAT PERGERAKAN → movement table
HARGA JUAL DASAR  → harga + margin (modal & margin butuh password)
UPDATE HARGA STOK → Owner only, edit harga transaksi dari mobile
Cetak QR Code     → modal ZPL, Zebra GC420 (Browser Print localhost:9100), ukuran: 30×20 mm (default), 40×30 mm, 50×25 mm
```

### Sub-tab Distribusi
```
EVENT             → CRUD event, assign users, biaya (RelationManager)
TRANSFER KELUAR   → pilih Event Tujuan, status AKTIF (transfer instan)
RETUR MASUK       → form return dari lokasi ke gudang
LOKASI PENJUALAN  → master lokasi
RIWAYAT           → kolom Event tampil di transfer_keluar
```

### Sub-tab Penjualan
```
RINGKASAN → stat cards + per-lokasi + 3 metode bayar (Tunai, QRIS, Transfer Bank)
            + Total Biaya Event hari ini
RIWAYAT
TOP PRODUK
```

### Stok Opname Web
```
AKTIF & PENDING → sesi yang perlu divalidasi + VALIDASI/TOLAK + BATALKAN DRAFT
RIWAYAT         → sesi tervalidasi/ditolak
```

### Laporan Lengkap
```
Laporan Stok      → per item per lokasi, status AMAN/KRITIS/HABIS + Excel export
Laporan Penjualan → per lokasi + date filter + Excel export
Laporan Gross Profit → Owner only:
  Total Penjualan | HPP | Gross Profit | Total Biaya | Total Diskon |
  Net Profit | Net Margin % + per-item table + biaya per event + Excel export
```

### Analytics / Setting
```
Laporan Lengkap (entry point)
Setting → User CRUD + Roles + Menu Access (tanpa icon overlap di form input)
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
```bash
cd /var/www/aksana-inventory
git fetch origin && git reset --hard origin/main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force
php artisan optimize:clear && php artisan view:clear
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
| B013 | FlutterSecureStorage hang di Dio interceptor | ✅ Fixed |
| B014 | Riwayat Distribusi tidak tampil tanpa filter | ✅ Fixed |
| B015 | Kolom Event tidak muncul di transfer_keluar | ✅ Fixed |
| B016 | Blade @endphp mismatch di laporan-lengkap | ✅ Fixed |
| B017 | Alpine statusOk undefined di print modal | ✅ Fixed |

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
| R008 | Event-based assignment (events + event_user) | ✅ Fixed |
| R009 | Transfer form pilih Event, bukan lokasi langsung | ✅ Fixed |
| R010 | Return barang via POST /api/returns + event_id | ✅ Fixed |
| R011 | Stok Opname selalu Gudang Pusat | ✅ Fixed |
| R012 | Format angka lengkap (hapus JT/RB) | ✅ Fixed |

---

## Fitur Baru (F-series)

| ID | Fitur | Status |
|---|---|---|
| F001 | Harga modal HANYA via web admin | ✅ Implemented |
| F002 | Form Tambah Stok web lengkap | ✅ Implemented |
| F003 | Upload foto barang masuk (web+mobile) | ✅ Implemented |
| F004 | Stok Ringkasan per lokasi | ✅ Implemented |
| F005 | Edit harga transaksi dari mobile (tab Update Harga Stok) | ✅ Implemented |
| F006 | Event CRUD API + Filament EventResource | ✅ Implemented |
| F007 | Mobile active event provider + auto-select | ✅ Implemented |
| F008 | Return sisa event-based (API + mobile) | ✅ Implemented |
| F009 | Transfer dengan event_id | ✅ Implemented |
| F010 | Master Data sidebar layout | ✅ Implemented |
| F011 | Laporan Lengkap inline sub-reports | ✅ Implemented |
| F012 | Gross profit per-item breakdown + diskon proporsional | ✅ Implemented |
| F013 | Propagasi harga ke transfer_items aktif | ✅ Implemented |
| F014 | Event Expenses backend API (Part A) | ✅ Implemented |
| F015 | Penjualan ringkasan + Total Biaya Event hari ini | ✅ Implemented |
| F016 | Stok Opname mobile scan item flow (+ SCAN ITEM) | ✅ Implemented |
| F017 | Event Expenses UI (RelationManager + laporan) | ✅ Implemented |
| F018 | Net Profit di laporan gross profit | ✅ Implemented |
| F019 | Excel export laporan (stok, penjualan, gross profit) | ✅ Implemented |
| F020 | Cetak QR Code ZPL Zebra GC420 | ✅ Implemented |
| F021 | Dashboard donut charts | ✅ Implemented |
| F022 | Browse Item di mobile | ✅ Implemented |
| F023 | Foto catalog di browse/cek stok | ✅ Implemented |
| F024 | Foto per item di cart penjualan | ✅ Implemented |
| F025 | Cetak QR Code di halaman Katalog (tombol header + modal ZPL) | ✅ Implemented |

---

## Pending

| ID | Item | Status |
|---|---|---|
| P002 | Mobile: opname blocking banner global di semua tab transaksi | ⚠️ Partial — widget + guard submit ada; refresh startup belum global |

*(P001 Stok Opname mobile scan item: ✅ Fixed — lihat F016)*

---

## Default Credentials

| Email | Password | Role | NIK | Lokasi |
|---|---|---|---|---|
| owner@aksana.id | password | owner | USR-001 | - |
| admin@aksana.id | password | admin | USR-002 | - |
| gudang@aksana.id | password | admin_gudang | USR-003 | Gudang Pusat (permanen) |
| picbazar@aksana.id | password | pic_bazar | USR-004 | - (via Event assignment) |
| sales@aksana.id | password | sales | USR-005 | - (via Event assignment) |

> PIC Bazar dan Sales mendapat akses lokasi penjualan hanya saat di-assign ke Event aktif.

---

## Coding Rules

1. UUID primary keys di semua tabel
2. supplier_cost TIDAK BOLEH di response apapun
3. DB::transaction() untuk semua mutasi stok
4. StockBalanceService untuk semua stock_balances
5. Backend recalculate semua harga
6. PostgreSQL: gunakan `TimezoneQuery` helper (bukan whereDate mentah untuk TIMESTAMP)
7. PostgreSQL: selectRaw sebelum groupBy
8. Jangan buat relasi baru ke tabel `employees`
9. Mobile: lokasi penjualan dari event aktif user, bukan `location_assignments`
10. Transfer bersifat instan — status AKTIF
11. Format angka: SELALU lengkap `Rp 2.200.000` — **TIDAK BOLEH** JT/RB/M/K (web dan mobile)
12. TIDAK BOLEH ada `use` statement di @php block Blade
13. Saat opname aktif: SEMUA transaksi diblokir
14. Margin dan Harga Modal sama-sama butuh password Owner
15. Update Harga Stok tab hanya visible untuk role Owner
16. Event-based location — jangan buat relasi baru ke `location_assignments`
17. Transfer wajib pilih Event — `to_location_id` resolve dari event
18. Harga update → propagasi ke `transfer_items` transfer aktif (kecuali manual)
19. Format angka SELALU lengkap — gunakan `FormatHelper::price()` di web, `FormatUtils.formatPrice()` di mobile
