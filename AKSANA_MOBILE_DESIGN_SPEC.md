# AKSANA_MOBILE_DESIGN_SPEC.md
# Spesifikasi Desain Flutter — dari Screenshot Lovable

Dokumen ini adalah referensi visual AKURAT berdasarkan screenshot Lovable.
Gunakan di setiap prompt Cursor saat membangun Flutter screen.

---

## 1. Warna

```dart
// Gunakan sebagai const di lib/core/theme/app_colors.dart
static const background  = Color(0xFFEDF1F3); // steel grey page bg
static const card        = Color(0xFFFFFFFF); // white card
static const void_black  = Color(0xFF070D1E); // near-black primary
static const border      = Color(0xFFD1DAE5); // card border
static const muted       = Color(0xFF49586B); // muted text/label
static const success     = Color(0xFF29A85A); // green (qty normal, trend up)
static const warning     = Color(0xFFF59100); // amber (qty menipis, damaged)
static const danger      = Color(0xFFF04040); // red (qty 0, lost, negative)
```

---

## 2. Typography

```dart
// Font family: Inter (body), IBM Plex Mono (codes/numbers)
// Tambahkan di pubspec.yaml:
// fonts:
//   - family: Inter
//   - family: IBMPlexMono

// Pola yang dipakai:
// Screen title:    Inter, 24px, FontWeight.bold, letterSpacing: -0.5
// Back nav label:  Inter, 10px, FontWeight.w600, uppercase, muted color
// Card title:      Inter, 14px, FontWeight.w600
// Card subtitle:   Inter, 11px, FontWeight.w400, muted
// Monospace value: IBMPlexMono, varies, FontWeight.bold
// Section label:   Inter, 10px, FontWeight.w700, uppercase, letterSpacing: 1.5, muted
// Tab label:       Inter, 9px, FontWeight.w700, uppercase
```

---

## 3. Komponen Umum

### ScreenHeader
```
← BACK_LABEL          ← 10px uppercase monospace muted
Screen Title           ← 24px bold Inter
```
Contoh:
- "← TERMINAL_04 / Quick Scan"
- "← BAZAR SENAYAN / Transaksi Jual"
- "← AUDIT SESI #042 / Stok Opname"

### Card
- Background: white
- Border: 1px solid Color(0xFFD1DAE5)
- BorderRadius: 12px
- Padding: 14px 16px

### Primary Button (full width)
- Background: void_black (#070D1E)
- Text: white, Inter 11px, FontWeight.w700, uppercase, letterSpacing: 1.5
- Height: 52px
- BorderRadius: 12px
- Contoh: "JUAL", "KONFIRMASI 26 ITEM", "GENERATE SURAT RETURN"

### Outline Button
- Background: transparent
- Border: 1px solid border color
- Text: void_black, same size as primary
- Contoh: "DETAIL"

### Tab Bar (bottom)
- Background: white, top border 1px
- 4 tabs: SCAN, JUAL, STOK, STAT
- Active: icon + label near-black, icon strokeWidth 2.25
- Inactive: icon + label Color(0xFFB0B8C4), strokeWidth 1.5
- Icon size: 22px
- Label: 9px uppercase Inter bold

---

## 4. Screen Specs

### SCAN — Quick Scan
```
Header: ← TERMINAL_04 / Quick Scan

[Camera View]
- Background: #0A0F1E (near-black)
- BorderRadius: 16px
- Label top-left: "CAMERA_ACTIVE" 9px mono muted white/40
- Scan line: horizontal, green (#29A85A), glow effect
- Corner brackets: white/30 opacity
- Label bottom-right: "scanning..." 9px green mono

[Result Card - appears after scan]
- Row: item_name bold left | price mono bold right
- Row: "SKU {sku} · STOK: {qty}" 11px muted mono

[Two buttons row]
- Left: JUAL (filled near-black, flex 1)
- Right: DETAIL (outline, flex 1)
- Gap: 8px
```

### BARANG MASUK
```
Header: ← RECEIVE STOCK / Barang Masuk

[PO Reference Card]
- Label: "PO REFERENCE" 10px uppercase muted
- Value: "PO-2026-XXXX" 15px bold mono

[Item Row] (white card, for each scanned item)
- Left: icon (package/box, 36x36, grey bg rounded)
- Middle: item_name 13px semibold (truncated)
          sku 11px muted mono below
- Right: [−] qty [+] stepper
  · Minus btn: 28x28, grey bg, rounded
  · Qty: 16px bold mono, w-8 center
  · Plus btn: 28x28, near-black bg, white icon, rounded

[CTA Button]
"✓ KONFIRMASI {total} ITEM"
```

### TRANSAKSI JUAL (POS)
```
Header: ← BAZAR SENAYAN / Transaksi Jual

[Item Row] (white card, per item in cart)
- item_name 13px semibold
- "qty 0{n}" 11px muted mono below
- price "Rp {x}k" 14px bold mono right

[Summary Card] — near-black background
- "SUBTOTAL" 10px uppercase white/60 | amount 12px mono white/60 right
- "DISKON BAZAR" 10px uppercase | amount red/warning right (−Rp XXk)
- Divider: white/20
- "TOTAL" 10px uppercase white/60 | "Rp {X,XXX}k" 28px bold mono white
- [BAYAR · CETAK STRUK] button — white bg, near-black text, full width inside card
  Height: 44px, BorderRadius: 8px
```

### STOK OPNAME
```
Header: ← AUDIT SESI #042 / Stok Opname

[Progress Card]
- "PROGRESS" 10px uppercase muted | "142 / 184" 13px bold mono right
- ProgressBar: height 6px, near-black fill, grey track, BorderRadius 3px

[Item Row] (white card, per scanned item)
- item_name 13px semibold
- "system {XX} · counted {XX}" 11px muted mono below
- Diff badge right:
  · negative: red "-{n}" 14px bold mono
  · zero: green "0" 14px bold mono
  · positive: green "+{n}" 14px bold mono

[Summary Row] — 3 cards side by side
- MATCH: {n} black
- LOST: {n} red (danger color)
- DAMAGED: {n} amber (warning color)
Each card: white bg, border, label 9px uppercase muted, value 18px bold mono
```

### CEK STOK
```
Header: ← MULTI-LOKASI / Cek Stok

[Item Card]
- Color swatch square (42x42, rounded, item's color code bg)
  with 2-letter abbreviation white text
- item_name 15px bold
- "{sku} · {category}" 11px muted

Section label: "DISTRIBUSI" 10px uppercase muted

[Location Row] (white card, per location)
- location_name 14px semibold
- qty right:
  · 0: "00" red mono bold
  · 1 (menipis): "0{n}" amber mono bold  
  · >1: "{n}" near-black mono bold

[Total Bar] — fixed bottom (above tab bar)
- Background: near-black
- "TOTAL STOCK" 10px uppercase white/60 left
- total_qty 24px bold mono white right
- Padding: 16px, height: 56px
```

### RETURN SISA
```
Header: ← BAZAR → GUDANG / Return Sisa

[Source Card]
- "ASAL" 10px uppercase muted
- location_name 16px bold
- "→ {warehouse_name}" 12px muted

[Item Row] (white card)
- Return icon (↩) grey left, 16px
- item_name 13px semibold
- "{sku} · sold {n}" 11px muted mono
- qty number 18px bold mono right
- "RETURN" 9px uppercase muted below qty

[CTA Button] "GENERATE SURAT RETURN"
```

### LAPORAN RINGKAS (STAT tab)
```
Header: ← HARI INI / Laporan Ringkas

[Main Card] — near-black bg, BorderRadius 16px, padding 20px
- "NET SALES" 10px uppercase white/60
- "Rp 14.8M" 36px bold mono white
- "▲ +8.2% vs kemarin" 12px green

[Two stat cards side by side]
- "ITEMS SOLD" | "AVG BASKET"
- Value: 24px bold mono near-black
- Label: 10px uppercase muted

[Bar Chart Card]
- "7-DAY TREND" 10px uppercase muted | "▲ trending" green right
- Simple bar chart: 7 bars, grey → near-black gradient (newest darkest)
- Bar height proportional to sales value

[Top SKU Row]
- "Top SKU" 12px semibold | "{sku} · {n} sold" mono muted right
```

---

## 5. Pola Warna untuk Qty/Status

```dart
Color qtyColor(int qty) {
  if (qty == 0) return AppColors.danger;   // red
  if (qty == 1) return AppColors.warning;  // amber
  return AppColors.void_black;             // normal
}

// Format qty dengan leading zero untuk 2 digit:
String formatQty(int qty) => qty.toString().padLeft(2, '0');
// Contoh: 4 → "04", 0 → "00", 18 → "18"
```

---

## 6. Cara Pakai di Prompt Cursor

Saat mengerjakan setiap Flutter screen, tambahkan di prompt:

```
Also refer to AKSANA_MOBILE_DESIGN_SPEC.md for exact:
- Colors, fonts, and spacing
- Screen layout for [nama screen]
- Component patterns (cards, buttons, tab bar)
- Qty color coding: 0=red, 1=amber, >1=black
- All monetary values use IBM Plex Mono bold
- All SKU/codes use IBM Plex Mono
- Primary button: near-black #070D1E, white text, 52px height
```
