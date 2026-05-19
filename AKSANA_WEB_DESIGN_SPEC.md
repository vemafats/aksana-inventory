# AKSANA_WEB_DESIGN_SPEC.md
# Spesifikasi Desain Filament Web Admin — dari Screenshot Lovable

---

## 1. Layout Global

### Sidebar (lebar 240px, fixed)
```
[Logo area]
■ VON_VELVET          ← near-black square icon 28px
  ADMIN CONSOLE       ← 9px uppercase muted mono

MENU                  ← section label 10px uppercase muted
  ▣ Dashboard
  ▣ Master Data
  ▣ Katalog
  ▣ Stok
  ▣ Distribusi        ← active: near-black bg + white text, full-width rounded-md
  ▣ Penjualan
  ▣ Stok Opname

ANALYTICS             ← section label 10px uppercase muted
  ▣ Laporan Lengkap
  ▣ Setting

[System bar - bottom]  ← near-black bg rounded-lg padding 12px
SYSTEM
● node_01_online       ← green dot animated pulse + mono text 11px
```

### Top Bar (header)
```
ADMIN / PAGE_NAME     ← breadcrumb 11px uppercase mono muted, slash separator
                         right side: search bar (264px) + bell icon + avatar circle
Search placeholder: "Cari SKU, nama, kategori..."
Avatar: near-black circle, initials white text
```

### Page Header
```
[Page Title]          ← 28px bold Inter, tracking tight
[Subtitle]            ← 13px muted, metadata info
                         right: [EXPORT button] [PRIMARY ACTION button]

EXPORT button:    outline (border + bg-white), icon left, 36px height
PRIMARY button:   near-black bg, white text, icon left, 36px height
                  text: uppercase, tracking wide, 11px bold
                  examples: "+ ITEM BARU", "↑ RETUR BARU", "↗ TRANSFER BARU"
```

---

## 2. Stats Cards (4-column grid)

```
┌─────────────────────────┐
│ LABEL           [icon]  │  ← 10px uppercase bold muted + icon right
│                         │
│ 08                      │  ← 28-32px bold mono, normal=near-black
│ outlet & bazar berjalan │  ← 12px mono muted
└─────────────────────────┘

Variasi warna value:
- Normal:  near-black
- Warning: amber #F59100 (contoh: MENUNGGU RETUR = 05)
- Danger:  red #F04040   (contoh: RETUR DAMAGED = 27)
```

---

## 3. Sub-menu Tabs (horizontal)

```
[▣ TRANSFER KELUAR] [↩ RETUR MASUK] [● LOKASI PENJUALAN] [↺ RIWAYAT]
         ↑ active tab
```
- Active: near-black bg, white text, rounded-md, padding 8px 16px
- Inactive: text-muted hover:bg-secondary
- Icon before label, 14px, uppercase, font-semibold
- Seluruh tab row: tidak ada border/container, tabs berdiri sendiri

---

## 4. Tabel

### Header Row
```
KODE    NAMA ITEM    KATEGORI    MERK    ...    AKSI
↑ semua header: 11px bold Inter uppercase, letter-spacing 0.08em, muted color
```

### Data Row
- Height: ~52px
- Hover: bg-secondary/30
- Border-bottom: 1px solid border color
- Kode/SKU/barcode columns: mono font, muted color
- Nama columns: semibold Inter
- Angka/qty: mono bold
- Pagination footer: "showing 1-5 of 5", prev/next buttons, current page = near-black btn

### Action Buttons (kolom AKSI)
- Icon button 28x28px, rounded-md
- Edit: pencil icon, bg-secondary/50 border, hover bg-secondary
- Delete: trash icon, bg-secondary/50 border, hover border-red text-red
- Khusus tombol aksi penting (RETUR, DETAIL): text button filled near-black 28px height

---

## 5. Badge / Status Pills

```dart
// Format: rounded pill, padding 4px 10px, 9-10px mono uppercase bold

// Tipe lokasi
GUDANG    → grey outline (border-border bg-secondary/40 text-muted)
OUTLET    → grey outline
BAZAR     → grey outline
POP-UP    → grey outline
PERSIAPAN → grey outline

// Status lokasi
PERMANEN       → dark grey (bg-foreground/10 text-foreground border-foreground/20)
AKTIF          → green (bg-success/15 border-success/30 text-success)
BERAKHIR 3 HARI → amber (bg-warning/15 border-warning/30 text-warning)

// Status transfer
DITERIMA         → green
DALAM PERJALANAN → blue (bg-accent/15 border-accent/40 text-accent)
DISIAPKAN        → grey

// Tipe distribusi history
RETUR    → amber pill
TRANSFER → blue outline pill
```

---

## 6. Master Data Page Layout

```
┌────────────────────────────────────────────────────────────┐
│ Master Data                        [EXPORT] [+ TAMBAH X]   │
│ Kelola data referensi · 7 tabel master                     │
├─────────────────────┬──────────────────────────────────────┤
│  TABEL MASTER       │  [Title] · {n} entri   [Cari...]     │
│  ▣ Kategori    05   │ ─────────────────────────────────── │
│  ▣ Merk        04   │  COL1   COL2   COL3          AKSI    │
│  ● Model       04   │  data   data   data         ✏️ 🗑️   │
│    (active=          │  data   data   data         ✏️ 🗑️   │
│    near-black)      │                                      │
│  ▣ Warna       05   │  showing 1-5 of 5  [prev] [1] [next]│
│  ▣ Ukuran      05   │                                      │
│  ▣ Karyawan    04   │                                      │
│  ▣ Lokasi      04   │                                      │
└─────────────────────┴──────────────────────────────────────┘

Left panel:
- white bg, border, rounded-lg, padding 8px
- "TABEL MASTER" label: 10px uppercase bold muted
- Tab item: icon 14px + label 12px semibold + count badge right (mono muted)
- Active: near-black bg, white text, white icon, full-width rounded-md

Right panel:
- white bg, border, rounded-lg, overflow-hidden
- Header: icon + title semibold + count muted | search input right
- Table inside
```

### Kolom per tabel Master Data
```
Kategori : KODE (mono muted) | NAMA KATEGORI | DESKRIPSI | AKSI
Merk     : KODE | NAMA MERK | NEGARA | AKSI
Model    : KODE | NAMA MODEL | KATEGORI | AKSI
Warna    : KODE | NAMA WARNA | HEX (colored square + code) | AKSI
Ukuran   : KODE | LABEL | SISTEM | AKSI
Karyawan : NIK (mono) | NAMA | ROLE | AKSI
Lokasi   : KODE | NAMA LOKASI | TIPE | AKSI
```

### Warna: kolom HEX
```
Tampilkan: [■] #F4EFE6   ← colored square 14px rounded + hex code mono
Color square: background = hex value, border = border color
```

---

## 7. Katalog Page

```
Katalog                               [EXPORT] [+ ITEM BARU]
Master item · 8 entri · barcode auto: kategori-merk-model-warna-ukuran

KATEGORI [Semua] [Sepatu] [Bottoms] [Outerwear] [Knitwear]
         ↑ filter chips: active=near-black filled, inactive=outline

┌─ Daftar Item · 8 item          [Cari nama atau barcode...] ─────┐
│ FOTO  NAMA ITEM     KAT   MERK    MODEL    WARNA  UK.  BARCODE  HARGA  AKSI │
│ [img] Nike Air Max  Sepa  Nike    Air Max  Hitam  40   [▣ AIR…] Rp2.4M ✏️🗑️ │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Thumbnail Katalog (kolom FOTO)
```
56×56px total frame:
- Colored square bg (item color hex) + shirt/shoe icon white
- Top-left corner: category badge (3 chars: SPT, BTM, OUT, KNT)
  → tiny rounded pill, light bg, muted text
- Bottom-right corner: size badge (ukuran number/letter)
  → tiny pill on top of thumbnail
```

### Barcode Badge (kolom BARCODE)
```
Near-black pill:
[▣ icon] AIRMAX-HIT-...   ← truncated with ellipsis
bg: near-black, text: white, font: mono, size: 10px
```

---

## 8. Distribusi Page

### Retur Masuk — Split Layout (2/3 + 1/3)

```
LEFT (2/3): Form "Transaksi Retur dari Lokasi Penjualan"   RTR-XXXX-001
  ┌─ Row: [Lokasi Asal (dropdown)] [Tanggal] [PIC Lokasi] ─────────────┐
  │                                                                     │
  │  ITEM SISA UNTUK DIRETUR              [▣ SCAN BARCODE]             │
  │  ITEM         KIRIM  TERJUAL  SISA  ✓GOOD  ✗DAMAGED               │
  │  Item Name      6      4       2   [✓][2]   [✗][0]                 │
  │  SKU below                                                          │
  └────────────────────────────────────────────────────────────────────┘
  CATATAN RETUR: [textarea placeholder]
  [BATAL] [↗ SIMPAN RETUR]

RIGHT (1/3): "Ringkasan Retur" sidebar
  LOKASI: Bazar Mall Kelapa Gading
  ┌──────────┬───────────┐
  │ ✓ GOOD   │ ✗ DAMAGED │
  │ 10       │ 2         │
  │kembali…  │menunggu…  │
  └──────────┴───────────┘
  Total Dikirim  35
  Terjual        23
  Diretur        12

  Good input → green circle-check icon left of input
  Damaged input → red circle-x icon left of input
  Input: 40px wide, border, mono text, center-aligned
```

---

## 10. Stok Page

### Sub-tabs & Action Buttons
```
[▣ RINGKASAN] [+ TAMBAH STOK] [↺ RIWAYAT PERGERAKAN] [◆ HARGA JUAL]
                                           ← active = near-black
Action buttons: [EXPORT STOK] [◆ MULAI OPNAME]  ← MULAI OPNAME = near-black
```

### Ringkasan — Stat Cards
```
TOTAL SKU     TOTAL UNIT STOK    NILAI INVENTORY     STOK KRITIS
1,248         8,427              Rp 3.84M            12
katalog aktif across 4 lokasi    harga modal         di bawah min
                                                     ↑ RED value
```

### Ringkasan — 3-column Widget Row
```
┌── Stok Terbanyak ──┬── Stok Paling Sedikit ──┬── Paling Laku (30D) ──┐
│ 01 Linen Trousers  │ 01 Atelier Noir Skirt 2  │ 01 Nike Air Max  248  │
│    BTM-...   142   │    BTM-...  min 10       │    SPT-...    +18%    │
│ 02 Nike Air Max    │    (qty in amber)        │    (sold + green %)   │
│    ...       118   │                          │                       │
└────────────────────┴──────────────────────────┴───────────────────────┘
Ranking number: near-black square badge (01=near-black+white, rest=grey)
```

### Ringkasan — Distribusi + Per Kategori (split 2/3 + 1/3)
```
LEFT: Distribusi Stok per Lokasi
┌─ Gudang Pusat Cikupa  [WAREHOUSE]  6,248 unit · 74% ──────────────────┐
│  ████████████████████████████████████░░░░░░░░░  ← near-black progress │
└─ Outlet PIK Avenue    [OUTLET]     1,184 unit · 14% ──────────────────┘
   ███████░░░░░░
Location badge: grey pill (WAREHOUSE, OUTLET, BAZAR)

RIGHT: Per Kategori
Sepatu    2,840 · 34%  ████████████████░░ ← accent blue fill
Bottoms   2,104 · 25%  ████████████░░░░░░
```

### Ringkasan — Slow Moving / Deadstock Table
```
◈ Slow Moving / Deadstock · tidak terjual > 60 hari    3 item perlu tindakan (amber)
ITEM          BARCODE           LAST SOLD    QTY TERSISA    AKSI
Knit Cardigan KNT-LNC-...      92 hari       38          [PINDAH BAZAR]
Wool Vest XL  OUT-VVT-...      78 hari       22          [PINDAH BAZAR]
LAST SOLD: amber mono text ("92 hari", "78 hari")
PINDAH BAZAR button: outline, small
```

### Ringkasan — Total Semua Stok per Item Table
```
◉ Total Semua Stok per Item · breakdown per lokasi      8 item · 4 lokasi
ITEM         BARCODE      GUDANG  PIK  SENAYAN  PLAZA.  TOTAL  STATUS  AKSI
Nike Air Max SPT-NIK-...     84   18     10       6      118   [AMAN]  [↗ DISTRIBUSI]
Atelier Noir BTM-ATN-...      2    0      0       0        2  [KRITIS] [↗ DISTRIBUSI]

STATUS badge:
AMAN   → grey outline pill
KRITIS → red outline pill (bg-danger/10 text-danger border-danger/30)
DISTRIBUSI button: near-black filled with icon
```

### Tambah Stok — Split Layout (2/3 + 1/3)
```
LEFT: "+ Transaksi Penambahan Stok"        "draft · belum tersimpan"
┌──────────────────────────────────────────────────────────────────┐
│ NO. REFERENSI / PO          TANGGAL MASUK                        │
│ [P0-2026-0419]              [📅 08 May 2026]                     │
│ SUPPLIER                    LOKASI TUJUAN                        │
│ [PT Sportif Indonesia]      [📍 Gudang Pusat Cikupa]             │
│                                                                  │
│ ITEM DITERIMA                            + TAMBAH BARIS          │
│ ┌── Nike Air Max Hitam 42 ──── Rp 1.750k ── 24 ──🗑️ ──────────┐ │
│ │   SPT-NIK-AIRMAX-HIT-42                                       │ │
│ └──────────────────────────────────────────────────────────────┘ │
│ TOTAL QTY: 54 unit          TOTAL NILAI MODAL: Rp 76.20jt        │
└──────────────────────────────────────────────────────────────────┘
[BATAL]                              [▣ SIMPAN & SCAN] ← near-black

RIGHT: "Penambahan Terbaru"
┌─ P0-2026-0418          08 May 2026 · 09:42 ──────────────────────┐
│ Nike Air Max Hitam 42                              +24 (green)    │
│ Supplier · PT Sportif                                             │
└──────────────────────────────────────────────────────────────────┘
```

### Riwayat Pergerakan Table
```
↺ Riwayat Pergerakan Stok · 30 hari terakhir    [📅 Apr 08 — May 08] [EXPORT]

TANGGAL              TIPE        ITEM              DARI            KE      QTY   REF
08 May 2026 · 09:42 [MASUK]     Nike Air Max 42  Supplier·PT..  ↕ Gudang  +24   PO-...
07 May 2026 · 16:08 [DISTRIBUSI] Linen Trousers  Gudang Pusat  ↕ PIK     -12   DST-...
07 May 2026 · 14:21 [KELUAR]    Adidas Samba     PIK Avenue    ↕ Penjual  -3   INV-...
06 May 2026 · 09:50 [RETUR]     Atelier Noir     Pop-up Indo. ↕ Gudang   +2   RTN-...

TIPE badges:
MASUK      → green  (bg-success/15 text-success border-success/30)
DISTRIBUSI → blue   (bg-accent/15 text-accent border-accent/40)
KELUAR     → red    (bg-danger/15 text-danger border-danger/30)
RETUR      → amber  (bg-warning/15 text-warning border-warning/30)

QTY: +n = green mono, -n = near-black mono
↕ icon between DARI and KE (ArrowLeftRight)
```

### Harga Jual Table
```
◆ Harga Jual per Item · jual · margin     [EXPORT PRICELIST] [✏ UPDATE MASSAL]

TAMPILAN DEFAULT (semua role):
ITEM                    BARCODE              HARGA JUAL  DISKON   MARGIN   AKSI
Nike Air Max Hitam 42  SPT-NIK-AIRMAX-..   Rp 2.45M    —        ↗ 40%    ✏️
Nike Air Max Putih 40  SPT-NIK-AIRMAX-..   Rp 2.45M    -10%     ↗ 26%    ✏️

KOLOM MODAL TIDAK ADA secara default.
Mockup Lovable menampilkan modal — ini SALAH. Implementasi harus mengikuti
keputusan yang sudah ditetapkan di CLAUDE.md:

"Harga modal HANYA ditampilkan untuk Owner SETELAH verifikasi password."

SETELAH Owner klik "Lihat Harga Modal" + input password yang benar:
ITEM                    BARCODE              MODAL       HARGA JUAL  DISKON   MARGIN   AKSI
Nike Air Max Hitam 42  SPT-NIK-AIRMAX-..   Rp 1.75M    Rp 2.45M    —        ↗ 40%    ✏️
                                            ↑ kolom Modal baru muncul setelah verified

DISKON: "—" if none, amber "-10%" if active
MARGIN: green "↗ 40%" with TrendingUp icon
```

---

## 11. Penjualan Page

### Sub-tabs
```
[▣ RINGKASAN] [≡ RIWAYAT] [↗ TOP PRODUK]
Action buttons: [EXPORT] only
```

### Ringkasan — Split Layout (2/3 + 1/3)
```
LEFT: "Penjualan per Lokasi" · hari ini
Bazar Plaza Indonesia  ████████████████░░░░  Rp 6.4M  28 trx
Outlet PIK Avenue      ███████████░░░░░░░░░  Rp 4.8M  22 trx
(bar proportional to share, near-black fill, grey track)

RIGHT: "Metode Pembayaran"
┌─ [◉ QRIS icon]  QRIS  ────────────────── Rp 12.3M ─┐
│  58 transaksi                                        │
├─ [💵 Tunai icon] Tunai ────────────────── Rp 6.1M  ─┤
│  26 transaksi                                        │
└──────────────────────────────────────────────────────┘
Payment card: white bg + border + rounded, icon in 28px grey square

Transaksi Terbaru table (below split):
KODE          WAKTU                LOKASI               KASIR      ITEM   TOTAL      BAYAR   STATUS
INV-0805-021  08 Mei 2026 14:22   Bazar Plaza Indonesia  👤 Dina P.   4   Rp 2.180k   QRIS   [LUNAS]
KASIR: person icon + name
STATUS LUNAS: green pill
```

### Riwayat Table
```
≡ Riwayat Transaksi                    [📅 08 Mei 2026] [EXPORT]
Same columns as Terbaru + AKSI column with [DETAIL] button (outline text button)
```

### Top Produk Table
```
↗ Top Produk Terjual                                    30 hari terakhir
#   BARCODE                ITEM                    VOLUME              QTY    OMZET
01  SPT-NIK-AIRMAX-HIT-40  Nike Air Max Hitam 40   ████████████████░   38    Rp 93.1M
02  SPT-ADS-SAMBA-HJU-41   Adidas Samba Hijau 41   █████████████░░░░   31    Rp 58.5M

# column: mono "01", "02" (padded 2 digits)
BARCODE: mono bold near-black
VOLUME: inline progress bar (near-black fill, grey track)
```

---

## 12. Stok Opname Page

### Sub-tabs
```
[☑ SESI AKTIF] [☐ DETAIL TEMUAN] [↺ RIWAYAT]
Action buttons: [EXPORT] only
Stat cards: SESI BERJALAN | ITEM TERSCAN | SELISIH DITEMUKAN | MENUNGGU VALIDASI
```

### Sesi Aktif Tab
```
[Warning banner — amber bg amber border]
⚠ Sesi tunggal aktif. Stok opname hanya dilakukan di Gudang Pusat dan dibatasi
  1 sesi berjalan. Sesi baru tidak dapat dimulai sebelum sesi aktif diselesaikan & divalidasi.

[Active Session Card]
● Sesi Aktif · SO-2026-018  [MENUNGGU VALIDASI]     [LIHAT DETAIL] [+ SESI BARU (TERKUNCI)]
                              ↑ amber badge          ↑ outline      ↑ disabled grey button
● = green dot animated pulse
4-column info grid:
LOKASI           PETUGAS              ITEM TERSCAN     SELISIH
Gudang Pusat     Rian (Staff Gudang)  1,284            26 (red)
Mulai · date     Validator · —        dari 1,310 SKU   +8 plus · 18 minus

[Sesi Sebelumnya table]
KODE SESI    MULAI              SELESAI            PETUGAS        VALIDATOR      TERSCAN  SELISIH  STATUS
SO-2026-017  01 May 2026·08:10  01 May 2026·12:05  Dewi(Gudang)  Hendra(SPV)    1,276    14(amb)  [TERVALIDASI]
SO-2026-014  ...                ...                ...           —              1,198    22(red)  [DITOLAK]

SELISIH: amber if moderate, red if high
STATUS: TERVALIDASI=green, DITOLAK=red
```

### Detail Temuan — Split Layout (2/3 + 1/3)
```
LEFT: "Temuan SO-2026-018"  [MENUNGGU VALIDASI badge]
Foto setiap item dihasilkan dari mobile app saat scan

FOTO      ITEM                      AWAL  SCAN  SELISIH  KONDISI    CATATAN
[📷 img]  Nike Air Force 1 Putih 42  18    16     -2      [GOOD]    Selisih -2...
          SPT-NIKE-AF1-WHT-42
[📷 img]  New Balance 550 Cream 40   22    21     -1      [DAMAGED] 1 unit sol...

FOTO cell: 40×40 grey placeholder, camera icon badge bottom-right
SELISIH: green +n, red -n, muted 0
KONDISI pills:
  GOOD    → green  (bg-success/15 text-success border-success/30)
  DAMAGED → red    (bg-danger/15 text-danger border-danger/30)

RIGHT: "Validasi Sesi"
Konfirmasi akan meng-overwrite stok awal dengan jumlah hasil scan

PETUGAS                    TANGGAL
Rian (Staff Gudang)        08 May 2026 · 08:00

Total Awal          106
Total Hasil Scan    102
Plus / Minus        +1 (green) · -4 (red)
Damaged             2 (red)

[Warning amber banner]
⚠ Setelah dikonfirmasi, jumlah temuan akan menjadi dasar stok berikutnya.
  Aksi tidak dapat dibatalkan.

[⊙ KONFIRMASI & OVERWRITE STOK]  ← near-black full width
[✗ TOLAK SESI]                   ← outline full width

Validator: Bapak Hendra (SPV Gudang)  ← small footer text
```

### Riwayat Table
```
↺ Riwayat Stok Opname
Sesi yang telah divalidasi atau ditolak

TANGGAL              KODE        PETUGAS          VALIDATOR         SCAN    SELISIH  STATUS
01 May 2026 · 12:05  SO-2026-017 Dewi(Gudang)     Bapak Hendra(SPV) 1,276    14     [TERVALIDASI]
10 Apr 2026 · 11:55  SO-2026-014 Rian(Gudang)     —                 1,198    22     [DITOLAK]

DITOLAK badge: red pill
TERVALIDASI badge: green pill
```

---

## 14. Setting Page

### Sub-tabs
```
[👤 Users] [🛡 Roles] [🔑 Menu Access]
Active: near-black filled, white text, rounded-md
Inactive: white bg, muted text, border
```

### Tab 1: Users — Split Layout (2/3 + 1/3)
```
LEFT: "Daftar User · {n} akun"  [Cari user...]  [+ USER near-black]

Table columns:
[avatar 32px initials near-black] USERNAME(mono muted) | NAMA LENGKAP(semibold)
ROLE badge: owner=#070D1E, admin=#1660ED, admin_gudang=#0F6E56,
            pic_bazar=#29A85A, sales=#49586B — all white text pill
STATUS: ● green "Aktif" / ● grey "Nonaktif"
LAST LOGIN: "X menit lalu" (Carbon diffForHumans)
AKSI: pencil icon btn + trash icon btn (28x28 grey bg)

RIGHT: "Edit User" panel (shows when user selected)
- Avatar 48px initials + name bold + "USR-XXX" code muted
- Fields: USERNAME (👤 icon), EMAIL (✉ icon),
  ROLE (select), PASSWORD BARU (🔒 icon, optional)
- STATUS AKUN: toggle (green=AKTIF)
- [SIMPAN PERUBAHAN] near-black full width
- [RESET] outline button
```

### Tab 2: Roles
```
"Role · 5 role"  [+ ROLE]

2-column card grid:
Each card: shield icon (28x28) + role name bold + user count small
           description text muted
           edit + trash icon buttons top right

5 roles:
Owner       → "Akses penuh ke seluruh modul & pengaturan sistem."
Admin       → "Kelola master data, katalog, stok & laporan."
Admin Gudang → "Kelola stok masuk, distribusi & stok opname."
PIC Bazar   → "Kelola penjualan harian & cek stok cabang."
Sales       → "Input transaksi jual & cek stok lokasi."
```

### Tab 3: Menu Access — Split Layout (240px + fill)
```
LEFT: "PILIH ROLE" (10px uppercase muted)
List of 5 roles with shield icon + user count badge
Active: near-black bg white text rounded-md

RIGHT: "Hak Akses Menu — {Role}"  [SIMPAN near-black]

Permission matrix table:
MENU          | VIEW | CREATE | EDIT | DELETE
Dashboard     | ☑    | ☑      | ☑    | ☑
Master Data   | ☑    | ☑      | ☑    | ☑
Katalog       | ☑    | ☑      | ☑    | ☑
Stok          | ☑    | ☑      | ☑    | ☑
Distribusi    | ☑    | ☑      | ☑    | ☑
Penjualan     | ☑    | ☑      | ☑    | ☑
Stok Opname   | ☑    | ☑      | ☑    | ☑
Laporan       | ☑    | ☑      | ☑    | ☑
Setting       | ☑    | ☑      | ☑    | ✗

Checkbox style:
Checked: near-black square + white checkmark
Unchecked: grey border square
Footer: "Centang untuk mengizinkan akses · klik SIMPAN untuk menerapkan."
        "9 menu × 4 permission" right

PERMISSIONS stored in settings table as JSON:
setting_key: 'role_permissions'
```

### System Status Bar (sidebar bottom)
```
<div style="background:#070D1E; border-radius:8px; padding:12px">
  SYSTEM (10px uppercase white/40)
  ● node_01_online (green pulse dot + 11px mono white)
</div>
```

Untuk setiap task Filament, tambahkan referensi sesuai halaman:

```
# Untuk halaman Stok:
Refer to AKSANA_WEB_DESIGN_SPEC.md section 10 (Stok Page) for:
- 4 sub-tabs layout
- Ringkasan 3-column widget (Terbanyak, Sedikit, Paling Laku)
- AMAN/KRITIS badge colors
- Slow Moving table with PINDAH BAZAR button
- Riwayat movement type badges (MASUK=green, DISTRIBUSI=blue, KELUAR=red, RETUR=amber)
- Harga Jual table: modal muted, margin=green with arrow, diskon=amber

# Untuk halaman Penjualan:
Refer to section 11 (Penjualan Page) for:
- Per-lokasi bar chart layout
- Payment method cards (QRIS + Tunai)
- Transaksi table with person icon on kasir column
- LUNAS status = green pill

# Untuk halaman Stok Opname:
Refer to section 12 (Stok Opname Page) for:
- Warning banner (amber) for single session rule
- Active session card with green pulse dot + MENUNGGU VALIDASI badge
- SESI BARU (TERKUNCI) disabled button style
- Detail Temuan split layout (2/3 table + 1/3 validation sidebar)
- GOOD=green / DAMAGED=red condition pills
- KONFIRMASI & OVERWRITE STOK = near-black full-width button
- TOLAK SESI = outline full-width button
```
