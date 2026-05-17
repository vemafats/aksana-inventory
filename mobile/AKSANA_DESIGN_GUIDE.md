# AKSANA_DESIGN_GUIDE.md
# Panduan Desain dari Lovable — untuk Filament (Web) dan Flutter (Mobile)

Dokumen ini dibuat dari analisis kode Lovable aksana-inventory.
Gunakan sebagai referensi di setiap sesi Cursor saat membangun UI.

---

## 1. Design System — "Surgical Steel & Glass"

### Warna (HSL)
| Token | Nilai HSL | Hex Approx | Penggunaan |
|---|---|---|---|
| background | 210 40% 96% | #EDF1F3 | Background halaman |
| foreground/void | 222 47% 4% | #070D1E | Primary text, tombol utama |
| card | 0 0% 100% | #FFFFFF | Card, panel |
| secondary | 214 32% 91% | #DDE4EC | Input, hover, tag |
| border | 214 32% 86% | #D1DAE5 | Semua border |
| muted-foreground | 215 16% 35% | #49586B | Label, placeholder |
| accent (electric blue) | 217 91% 50% | #1660ED | Link, badge aktif |
| success | 142 71% 45% | #29A85A | Status OK, qty normal |
| warning | 38 92% 50% | #F59100 | Alert, stok menipis |
| destructive | 0 84% 60% | #F04040 | Error, hapus, stok habis |

### Font
- **Sans (body):** Inter — untuk semua teks biasa
- **Mono:** IBM Plex Mono — untuk: SKU/barcode, angka stok, nominal rupiah, kode transaksi, timestamp

### Prinsip Visual
- Background halaman: steel grey (bukan putih)
- Card: putih dengan border tipis
- Shadow: sangat tipis, glass style (bukan shadow hitam tebal)
- Border radius: 0.5rem (card besar), 0.375rem (komponen kecil)
- Angka penting: selalu monospaced (IBM Plex Mono)
- Label tabel: UPPERCASE, letter-spacing lebar, ukuran 10px

---

## 2. Komponen UI yang Dipakai

### Tombol
- **Primary:** background foreground (near-black), text putih, font semibold, tracking wide
- **Secondary:** background card + border, hover secondary
- **Destructive:** hover merah border + text

### Badge / Pill Status
- draft/pending: bg-secondary border-border text-muted
- active/ok/lunas: bg-success/15 border-success/30 text-success
- warning/menipis/perjalanan: bg-warning/15 border-warning/30 text-warning
- error/kritis/ditolak: bg-destructive/15 border-destructive/30 text-destructive
- info/transfer: bg-accent/15 border-accent/40 text-accent-foreground

### Tabel
- Header: bg-secondary/40, text 10px UPPERCASE monospaced
- Row: hover bg-secondary/30, transition-colors
- Divider: divide-y divide-border
- Action button di kolom terakhir: icon kecil (size-3), bg-secondary/50 border

### Sub-menu Tabs (dalam halaman)
- Container: bg-card border border-border rounded-lg p-1
- Tab aktif: bg-foreground text-background
- Tab non-aktif: text-muted-foreground hover:bg-secondary

### Cards Statistik
- p-4, bg-card border border-border rounded-lg shadow-xs
- Label: 10px UPPERCASE tracking-widest muted
- Value: 2xl monospaced tracking-tighter
- Sub-info: 10px mono muted

---

## 3. Struktur Menu Web Admin (Final dengan Lovable)

```
Sidebar:
  MENU:
  ├── Dashboard
  ├── Master Data
  ├── Katalog
  ├── Stok
  │   ├── Tab: Ringkasan
  │   ├── Tab: Tambah Stok
  │   ├── Tab: Riwayat Pergerakan
  │   └── Tab: Harga Jual (bukan harga modal)
  ├── Distribusi
  │   ├── Tab: Transfer Keluar
  │   ├── Tab: Retur Masuk
  │   ├── Tab: Lokasi Penjualan
  │   └── Tab: Riwayat
  ├── Penjualan
  │   ├── Tab: Ringkasan
  │   ├── Tab: Riwayat
  │   └── Tab: Top Produk
  └── Stok Opname
      ├── Tab: Sesi Aktif
      ├── Tab: Detail Temuan
      └── Tab: Riwayat

  ANALYTICS:
  ├── Laporan Lengkap
  └── Setting
```

---

## 4. Struktur Menu Mobile (Final dengan Lovable)

```
Bottom Tab Bar (4 tab):
  ├── Scan (tab 0) — quick scan QR, tampil item + action
  ├── Jual (tab 1) — POS cart + checkout
  ├── Stok (tab 2) — cek stok multi-lokasi + barang masuk + opname + return
  └── Stat (tab 3) — statistik ringkas (omzet hari ini, stok kritis, dll)

Screens tambahan dari tab:
  ├── ScanScreen → detail item → pilih aksi (Jual / Detail)
  ├── BarangMasukScreen → input PO + scan + qty
  ├── JualScreen (POS) → cart + diskon + total + bayar
  ├── StokOpnameScreen → progress scan + diff per item
  ├── ReturnSisaScreen → pilih item + qty return + generate surat
  └── CekStokScreen → stok per lokasi setelah scan
```

---

## 5. Fitur Baru dari Lovable — DITAMBAHKAN ke CLAUDE.md

### 5A. Stok: Sub-tab "Harga Jual"
Menampilkan: nama item, barcode, **harga jual**, diskon aktif, margin (%).

**KOLOM MODAL TIDAK DITAMPILKAN secara default — bahkan untuk Owner sekalipun.**
Mockup Lovable menampilkan kolom Modal secara terbuka — ini SALAH dan diabaikan.
Implementasi mengikuti keputusan di CLAUDE.md:
- Default: kolom Modal tidak ada di tabel
- Owner klik "Lihat Harga Modal" → input password → jika benar → kolom Modal muncul
- Role non-Owner: tidak melihat tombol "Lihat Harga Modal" sama sekali
- Harga Modal hanya visible 15 menit (sesuai cost_view_token TTL)
Role akses sub-tab Harga Jual: Owner, Admin (tapi kolom Modal hanya Owner+password).

### 5B. Stok: Widget "Slow Moving / Deadstock"
Item yang tidak terjual lebih dari 60 hari.
Kolom: nama item, barcode, last sold (berapa hari lalu), qty tersisa.
Action: "Pindah Bazar" — shortcut distribusi ke lokasi bazar.
Ini widget informasional, tidak butuh tabel baru.

### 5C. Stok Opname: Single Session + Validator Flow
**Business rule baru:**
- Hanya 1 sesi opname boleh berjalan di waktu yang sama
- Sesi baru tidak bisa dimulai sebelum sesi aktif selesai dan divalidasi
- Status opname: draft → menunggu validasi → tervalidasi / ditolak
- Yang bisa validasi: Owner, Admin (sebagai validator/SPV)
- Setelah tervalidasi: qty stok dioverwrite sesuai hasil scan
- Jika ditolak: stok tidak berubah, sesi ditutup

**Perubahan di database:**
Tambah field di stock_opname_transactions:
- validator_id (uuid, nullable, fk → users.id)
- validated_at (timestamp, nullable)
- validation_status (string: draft / pending_validation / validated / rejected)
- rejection_note (text, nullable)

### 5D. Mobile: "Generate Surat Return"
Button di ReturnStockScreen mobile untuk generate dokumen PDF ringkasan return.
Isi: nama lokasi, tanggal, daftar item return (qty good, qty damaged).
Ini bisa diimplementasi sebagai endpoint:
GET /api/transfers/{id}/return-document → return PDF atau HTML print view.

### 5E. Mobile: Tab "Stat"
Statistik ringkas untuk user mobile:
- Omzet hari ini (di lokasi yang di-assign ke user)
- Stok kritis (qty ≤ 1) di lokasi user
- Transaksi hari ini (jumlah nota)
- Item terlaris hari ini
API: GET /api/reports/mobile-summary (filter by location assignment)

---

## 6. Yang TIDAK BERUBAH dari TSD dan CLAUDE.md

Semua item berikut di Lovable KONSISTEN dengan TSD/CLAUDE.md:
- ✓ Struktur menu utama (Dashboard, Katalog, Stok, Distribusi, Penjualan, Opname, Laporan, Setting)
- ✓ Flow katalog: kategori → merk → model → warna → ukuran → auto SKU
- ✓ Format barcode SKU: KAT-MRK-MODEL-WRN-UK
- ✓ Flow barang masuk: scan → qty → QC → simpan
- ✓ Flow transfer: gudang → bazar, snapshot harga
- ✓ Flow penjualan: scan → cart → diskon → payment
- ✓ Flow return: bazar → gudang, good vs damaged
- ✓ Role: Owner, Admin, Admin Gudang, PIC Bazar, Sales
- ✓ Harga modal TIDAK tampil di katalog
- ✓ Stok status: available, damaged, lost
- ✓ Payment method: QRIS, Tunai (Cash), Transfer

---

## 7. Cara Pakai Panduan Ini di Cursor

Saat memulai task UI (Filament atau Flutter), tambahkan di prompt:

```
Also refer to AKSANA_DESIGN_GUIDE.md for:
- Color tokens and their hex values
- Font usage: Inter for body, IBM Plex Mono for codes/numbers
- Component patterns: table header style, badge colors, button variants
- Use near-black (#070D1E) as primary button color, not blue
- All monetary values and codes use monospace font
- Card background: white (#FFF) on steel-grey page background
```
