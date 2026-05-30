# CLAUDE.md — Aksana Inventory
# Last Updated: 2026-05-30 (post UAT Day 1)

Ini adalah file konteks utama untuk Claude Code. Baca seluruh file ini sebelum mengerjakan tugas apapun.

---

## Ringkasan Proyek

**Aksana Inventory** adalah sistem inventory dan penjualan untuk bisnis butik berbasis gudang pusat dan lokasi bazar/outlet/toko.

- **Web Admin**: Laravel + Filament (manajemen penuh)
- **Mobile App**: Flutter + Dart (operasional lapangan)
- **Backend API**: Laravel REST API
- **Database**: PostgreSQL 16 (Hostinger VPS)
- **Server**: Hostinger VPS 187.127.114.2 (Ubuntu 24.04, Apache2, Redis, Supervisor, SSL)
- **Live URL**: https://app.ftrhijab.id/admin
- **GitHub**: https://github.com/vemafats/aksana-inventory

---

## Technology Stack

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.2 + Laravel 11 |
| Auth | Laravel Sanctum (token TTL: 30 hari) |
| Web Admin | Laravel + Filament v3 |
| Mobile | Flutter + Dart |
| HTTP Client (mobile) | Dio |
| State Management | Riverpod |
| Routing (mobile) | GoRouter |
| QR Code | simplesoftwareio/simple-qrcode |
| Barcode Scanner | mobile_scanner |
| Database | PostgreSQL 16 |
| Queue/Cache | Redis 7.0 |
| Server | Apache2 + Supervisor + Certbot SSL |
| Fonts | Google Fonts (Inter + IBM Plex Mono) |

---

## Konsep Inti — WAJIB DIPAHAMI

### 1. Katalog ≠ Stok
- Menambah item ke Katalog TIDAK menambah stok
- Stok hanya bertambah melalui Barang Masuk
- `items` = master referensi barang
- `stock_balances` = jumlah stok aktual

### 2. Barcode = Identitas Varian
Format: `KAT-MRK-MODEL-WRN-UK`
Contoh: `SPT-NIK-AIRMAX-HIT-40`
Barcode unik di tabel `items`.

### 3. Stock Balance = Item + Lokasi + Status
```
UNIQUE (item_id, location_id, stock_status)
stock_status: available | damaged | lost
```

### 4. Semua Mutasi Stok Wajib StockBalanceService
- TIDAK BOLEH update `stock_balances` langsung dari controller
- Selalu gunakan `StockBalanceService`
- Wajib dalam `DB::transaction()`
- Setiap mutasi wajib membuat `StockMovement` record

### 5. Harga = Snapshot
Harga modal, harga jual, diskon harus disimpan sebagai snapshot per transaksi.

### 6. Backend Recalculate Semua Total
JANGAN percaya total dari mobile/frontend. Backend wajib hitung ulang semua.

---

## ⚠️ PERUBAHAN ARSITEKTUR PENTING (2026-05-30)

### Employee DIMERGE ke User
**Keputusan:** Employee dan User adalah entitas yang SAMA. Tidak ada entitas terpisah.

**Perubahan database:**
- `users` ditambah: `nik` (string unique), `position` (string)
- `location_assignments.employee_id` → diganti `user_id` (FK users.id)
- `sales_transactions` ditambah `user_id` (FK users.id)

**Rules:**
- Jangan buat relasi baru ke tabel `employees`
- `LocationAssignment` belongsTo `User` (bukan Employee)
- `SalesTransaction` belongsTo `User` untuk kasir
- `ReportService::resolveAccessibleLocationIds()` lookup via `user_id`
- API `/api/me` return `nik`, `position`, `location_id`, `location_name`
- Tabel `employees` masih ada tapi DEPRECATED

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
| Tutup Bazar | ✓ | ✓ | ✓ | ✓ | ✗ |
| Laporan | ✓ | ✓ | Terbatas | Terbatas | ✗ |
| Setting | ✓ | ✓ | ✗ | ✗ | ✗ |
| Harga Modal | Owner+password | ✗ | ✗ | ✗ | ✗ |
| Margin | Owner+password | ✗ | ✗ | ✗ | ✗ |

Role values: `owner` | `admin` | `admin_gudang` | `pic_bazar` | `sales`

---

## Aturan Keamanan Data

### supplier_cost — WAJIB TERSEMBUNYI
- TIDAK BOLEH muncul di API response manapun
- TIDAK BOLEH muncul di UI (kecuali Owner+password)
- `latest_supplier_cost` ada di `Item.$hidden`
- `supplier_cost_snapshot` ada di `SalesItem.$hidden` dan `TransferItem.$hidden`

### Harga Modal dan Margin
- Default: kolom Modal dan Margin TIDAK TAMPIL
- Owner klik "Lihat Harga Modal" → input password → Modal + Margin muncul
- TTL token: 15 menit (`cost_view_token`)
- Role non-Owner: tombol tidak terlihat

### Penamaan Harga — Konsisten
- **Harga Jual Dasar** = harga modal + margin → `latest_base_selling_price`
- **Harga Jual Lokasi/Bazar** = Harga Jual Dasar + nilai penyesuaian
- Tab web admin: "HARGA JUAL DASAR" (bukan "Harga Jual")
- Harga Jual di form distribusi = AUTO-CALCULATE (bukan input manual)

---

## Alur Bisnis

### Barang Masuk
```
VIA MOBILE (Admin Gudang) — scan + qty saja:
  Login → STOK → Barang Masuk →
  input PO Reference → scan QR item →
  input qty diterima (stepper) →
  foto QC (opsional, dianjurkan) →
  KONFIRMASI → tampilkan "{total_qty} unit ({n} jenis) ke gudang"
  supplier_cost = 0 (diisi via web)

VIA WEB ADMIN (Owner/Admin) — lengkap dengan harga:
  Stok → Tambah Stok →
  input PO + tanggal + supplier →
  pilih item + qty available + qty damaged →
  input harga modal + tipe margin + nilai margin →
  harga jual dasar = auto-calculate →
  upload foto (opsional) →
  SIMPAN → stok bertambah di gudang
```
**PENTING:** Harga modal HANYA bisa diinput via web admin, TIDAK via mobile.

### Transfer ke Lokasi
```
Admin → pilih item + lokasi → input penyesuaian harga (opsional) →
harga jual lokasi = auto-calculate →
stok berpindah instan (tidak ada konfirmasi "diterima")
Status: AKTIF (bukan "Dalam Perjalanan")
```

### Transaksi Jual (Mobile)
```
Login (auto-detect lokasi dari user_id) →
scan QR → item muncul → tap JUAL →
cart + stepper qty →
BAYAR → pilih lokasi jika belum assigned →
pilih metode (TUNAI/QRIS/TRANSFER) →
backend recalculate → simpan → stok berkurang
```
PENTING: Stok harus ada di lokasi yang dipilih. Jika 0 → error normal.

### Stok Opname
```
Buat sesi → scan → input qty fisik →
submit → Owner/Admin validasi →
SETELAH validasi: stok dioverwrite
Single session: hanya 1 sesi aktif sekaligus
Status: draft → pending_validation → validated/rejected
```

---

## PostgreSQL — Rules Wajib

### Timezone Queries
```php
// BENAR — gunakan helper ini:
$this->whereReportDate($query, 'transaction_date', $today)
// Yang menjalankan:
->whereRaw("DATE(column AT TIME ZONE 'Asia/Jakarta') = ?", [$date])

// SALAH:
->whereDate('transaction_date', $date)  // tidak timezone-aware
```

### GROUP BY
```php
// BENAR:
->selectRaw('item_id, SUM(qty) as total_qty')
->groupBy('item_id')

// SALAH (error di PostgreSQL):
->groupBy('item_id')  // tanpa selectRaw
```

### UUID Morphs
`personal_access_tokens.tokenable_id` harus `varchar(255)`.
Migration wajib pakai `uuidMorphs()` bukan `morphs()`.

---

## Stok Opname

### Single Session Rule
- Hanya 1 sesi aktif di satu waktu
- Cek `/api/stock-opnames/active` sebelum buat sesi baru
- Throw `OpnameSessionActiveException` jika ada sesi aktif

### Validator Flow
```
draft → pending_validation → validated (stok dioverwrite)
                           → rejected  (stok tidak berubah)
```
Yang bisa validasi: Owner, Admin saja.

---

## Mobile App

### Base URL Production
```dart
static const String baseUrl = 'https://app.ftrhijab.id/api';
```

### Tab Bar (5 tab)
`SCAN | JUAL | STOK | STAT | AKUN`

### Employee Detection
Employee TIDAK di-scan manual.
Employee = User yang sedang login.
`employee_id` di API request = null (deprecated).

### APK Versioning
Format: `aksana-v{version}.apk`
Ubah `pubspec.yaml` version setiap build baru.
Current production: v1.0.9

---

## Deployment

### Deploy Command
```bash
/root/deploy.sh
# git fetch + reset hard + composer + migrate + cache + restart
```

### VPS
```
IP: 187.127.114.2
OS: Ubuntu 24.04, Apache2, PHP 8.2, PostgreSQL 16, Redis 7.0
Deploy: /root/deploy.sh
DB: aksana_inventory, user: aksana_user
```

---

## Bug Fixes (UAT 2026-05-30)

| ID | Error | Status | Fix |
|---|---|---|---|
| B001 | PostgreSQL GROUP BY column error | ✅ Fixed | Gunakan `selectRaw` sebelum `groupBy` |
| B002 | UUID vs bigint di personal_access_tokens | ✅ Fixed | `ALTER COLUMN tokenable_id TYPE varchar(255)` |
| B003 | Scan QR tidak merespons di HP | ✅ Fixed | Rewrite ScanScreen dengan proper MobileScannerController |
| B004 | Harga Rp 0 di cart | ✅ Fixed | Parse `latest_base_selling_price` ke double |
| B005 | Location/employee tidak tersedia | ✅ Fixed | Location selector + employee_id nullable |
| B006 | API login 500 | ✅ Fixed | Sama dengan B002 |
| B007 | Dropdown lokasi kosong di Distribusi | ✅ Fixed | Update status lokasi dari draft ke active |
| B008 | Report mobile-summary selalu 0 | ✅ Fixed | Timezone-aware query `AT TIME ZONE 'Asia/Jakarta'` |
| B009 | Git pull conflict di VPS | ✅ Fixed | Deploy script pakai `git reset --hard origin/main` |
| B010 | Route [login] not defined | ✅ Non-fatal | Warning log saja, tidak affect fungsi |
| B011 | Pesan konfirmasi barang masuk "1 item" | 🔲 Pending | Harus tampilkan total qty bukan jumlah jenis item |

---

## Revisi Pending (Post-UAT)

### Status R001–R007

| ID | Item | File | Status |
|---|---|---|---|
| R001 | Status transfer: "DALAM PERJALANAN" → "AKTIF" | DistribusiPage.php | ✅ Fixed |
| R002 | Rename tab "HARGA JUAL" → "HARGA JUAL DASAR" | stok.blade.php | ✅ Fixed |
| R003 | Margin dilindungi password seperti Harga Modal | StokPage.php | 🔲 Pending |
| R004 | Harga Jual Bazar = auto-calculate di form distribusi | DistribusiPage.php | ✅ Fixed |
| R005 | Tombol Verifikasi tidak terlihat di dialog Harga Modal | Blade CSS | 🔲 Pending |
| R006 | STAT tab tidak bisa di-scroll/tap | reports_screen.dart | ✅ Fixed |
| R007 | Setting page — Tambah/Edit User belum tersambung ke DB | setting.blade.php | 🔲 Pending |

### Fitur Baru dari UAT (F-series)

| ID | Fitur | Scope | Keputusan |
|---|---|---|---|
| F001 | Input harga modal + margin saat barang masuk | **Web Admin only** | Pilihan B: hanya via web sub-tab Tambah Stok, BUKAN di mobile |
| F002 | Sub-tab Tambah Stok di web admin — form lengkap | Web (StokPage) | Alur sama dengan mobile: scan/pilih item + qty + harga modal + margin |
| F003 | Upload foto saat barang masuk | Web + Mobile | Foto QC wajib sebelum konfirmasi barang masuk |
| F004 | Stok Ringkasan: breakdown per lokasi per item | Web (stok.blade.php) | Tabel "Total Semua Stok per Item" tambah kolom per lokasi |

### Detail F001 — Barang Masuk: Harga Modal di Web Saja

**Alur yang benar:**
```
Mobile (Admin Gudang):
  Scan QR Code → input qty diterima → KONFIRMASI
  (tanpa input harga — harga diisi via web)

Web Admin (Owner/Admin):
  Stok → Tambah Stok → pilih item → input qty →
  input harga modal → input tipe margin (nominal/%) →
  sistem hitung harga jual dasar otomatis → SIMPAN
```

**Implikasi API:**
- POST /api/stock-in dari mobile: `supplier_cost` bisa 0 atau null
- Web admin memanggil POST /api/stock-in dengan `supplier_cost` yang valid
- Backend tetap menyimpan `latest_supplier_cost` di tabel `items`

### Detail B011 — Fix Pesan Konfirmasi Barang Masuk

**Sekarang:** "Kirim 1 item ke gudang pusat?"
**Seharusnya:** "Kirim {total_qty} unit ({n} jenis item) ke gudang pusat?"

Contoh: 1 jenis H&M Essential Coklat L, qty 4 →
"Kirim 4 unit (1 jenis item) ke gudang pusat?"

File: `lib/features/stock_in/presentation/stock_in_screen.dart`

### Detail F002 — Form Tambah Stok Web Admin

Form di sub-tab Tambah Stok harus memiliki:
- No. Referensi/PO (input teks)
- Tanggal Masuk (date picker)
- Nama Supplier (input teks, opsional)
- Lokasi Tujuan (auto: Gudang Pusat, read-only)
- Section ITEM DITERIMA:
  - Pilih item dari katalog (searchable dropdown)
  - Qty Diterima (required)
  - Qty Available (required, ≤ qty diterima)
  - Qty Damaged (auto = qty diterima - qty available)
  - Harga Modal / supplier_cost (required, Owner/Admin only)
  - Tipe Margin: nominal | percentage
  - Nilai Margin (input angka)
  - Harga Jual Dasar (auto-calculate, read-only)
  - QC Note (opsional)
  - Tombol + Tambah Item (untuk item berikutnya)
- Total Qty + Total Nilai Modal (auto-calculate)
- Tombol: BATAL | SIMPAN

### Detail F003 — Upload Foto Barang Masuk

**Web Admin:**
- Tambah FileUpload field di form Tambah Stok
- Upload sebelum klik SIMPAN
- Foto disimpan via PhotoService (watermark + compress)
- Foto ID dikirim ke POST /api/stock-in sebagai `photo_id`

**Mobile:**
- Tambah tombol kamera sebelum KONFIRMASI di Barang Masuk screen
- Gunakan `image_picker` package (sudah ada di pubspec.yaml)
- Upload via POST /api/photos → dapat photo_id
- Kirim photo_id saat POST /api/stock-in

### Detail F004 — Stok Ringkasan: Lokasi Per Item

Tabel "Total Semua Stok per Item" tambah kolom per lokasi:

| ITEM | BARCODE | GUDANG | BINTARO | CREATIVE | SURYA | TOTAL | STATUS |
|---|---|---|---|---|---|---|---|

- Kolom lokasi: dynamic berdasarkan lokasi yang ada di database
- Qty per lokasi = stock_balances WHERE stock_status = available
- Jika qty 0 di lokasi: tampilkan "—" (muted)
- Jika qty > 0: tampilkan angka bold

---

## Default Credentials

| Email | Password | Role | NIK |
|---|---|---|---|
| owner@aksana.id | password | owner | USR-001 |
| admin@aksana.id | password | admin | USR-002 |
| gudang@aksana.id | password | admin_gudang | USR-003 |
| picbazar@aksana.id | password | pic_bazar | USR-004 |
| sales@aksana.id | password | sales | USR-005 |

---

## Coding Rules (Ringkasan)

1. UUID primary keys di semua tabel
2. supplier_cost TIDAK BOLEH di response apapun
3. DB::transaction() untuk semua mutasi stok
4. StockBalanceService untuk semua stock_balances
5. Backend recalculate semua harga
6. PostgreSQL: gunakan whereReportDate() helper
7. PostgreSQL: selectRaw sebelum groupBy
8. Employee = User — jangan relasi baru ke tabel employees
9. API /api/me harus return location_id dan location_name
10. Transfer bersifat instan — status AKTIF bukan "Dalam Perjalanan"
