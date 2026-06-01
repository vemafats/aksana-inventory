# CLAUDE_MOBILE.md — Aksana Inventory (Flutter)

SOURCE OF TRUTH: Global business rules, API contracts, roles, database rules, and coding rules live in the ROOT `CLAUDE.md`. ALWAYS read root `CLAUDE.md` first for any business logic. This file only covers Flutter/mobile implementation details.

---

## Mobile App

### Base URL Production
```dart
static const String baseUrl = 'https://app.ftrhijab.id/api';
```

### Tab Bar (5 tab)
```
SCAN | JUAL | STOK | STAT | AKUN
```

### Format Angka
- Juta: **JT** (bukan M)
- Ribu: **RB** (bukan K)
- Misal: Rp 1.4 JT, Rp 550 RB
- Implementasi: `lib/core/utils/price_format.dart`, `lib/core/utils/format_utils.dart`
- Aturan global juga di root `CLAUDE.md` Coding Rules #11

### Employee Detection
Employee = User yang sedang login.
`employee_id` di API = null (deprecated).
`user_id` = user id dari token Sanctum / `auth_provider`.

### APK Versioning
Current production: `aksana-v1.2.0.apk` (v1.2.0+14)
Format: `aksana-v{version}.apk`

---

## Alur UI Mobile

### Transaksi Jual — Flow Screen
```
Login → auto-detect lokasi dari user_id →
scan QR → JUAL → cart + stepper qty →
BAYAR → pilih metode (TUNAI/QRIS/TRANSFER) →
backend recalculate → simpan → stok berkurang
```
- Feature: `lib/features/sales/`, `lib/features/scan/`
- Provider: `sales_provider.dart`, `scan_provider.dart`

### Stok Opname — Flow Scan
```
1. Buat sesi → WAJIB pilih lokasi
2. Scan QR Code item
3. Input QTY FISIK (qty yang dihitung secara fisik)
4. Input QTY RUSAK (opsional)
5. Lihat sistem qty vs fisik qty + selisih
6. SIMPAN ITEM → lanjut scan item berikutnya
7. Setelah semua item: SUBMIT UNTUK VALIDASI
8. Sesi status: draft → pending_validation
```
- Feature: `lib/features/stock_opname/`
- API: lihat root `CLAUDE.md` → API Stok Opname

### Stok Opname Mobile — Status Fix Needed
**Masalah saat ini:** Setelah buat sesi, tidak ada tombol scan item.
**File yang perlu difix:**
- `lib/features/stock_opname/presentation/` (cek semua file di folder ini)
- Screen setelah session dibuat harus punya tombol "+ SCAN ITEM"
- Flow: tap scan → kamera → scan QR → form qty → SIMPAN ITEM
- API: POST /api/stock-opnames/{id}/items

---

## Struktur Folder Flutter

**Stack:** Riverpod (`StateNotifier` / providers), GoRouter (`lib/core/router/app_router.dart`), Dio (`lib/core/api/api_client.dart`), `mobile_scanner` untuk QR.

```
lib/
├── main.dart
├── core/
│   ├── api/           → Dio client, interceptors, base URL
│   ├── auth/          → auth_provider, session/token
│   ├── opname/        → active_opname_provider (sesi opname aktif)
│   ├── router/        → GoRouter routes & shell
│   ├── theme/         → app_colors, app_text_styles, app_theme
│   ├── utils/         → price_format, format_utils, location_helpers
│   └── widgets/       → main_scaffold, screen_header, opname_blocking_banner
└── features/
    ├── auth/          → login (data/ + presentation/)
    ├── profile/       → akun / profil user
    ├── reports/       → STAT tab, laporan ringkas mobile
    ├── return_stock/  → retur barang sisa ke gudang
    ├── sales/         → JUAL tab, cart, checkout
    ├── scan/          → SCAN tab, QR lookup katalog
    ├── stock_check/   → cek stok per lokasi (menu di STOK tab)
    ├── stock_in/      → barang masuk dari mobile (Admin Gudang)
    └── stock_opname/  → sesi opname, scan item, submit validasi
```

**Pola per feature:** `data/` (service + API calls) + `presentation/` (screens, providers, widgets).

| Feature | data/ | presentation/ |
|---|---|---|
| auth | auth API | `login_screen.dart` |
| sales | `sales_service.dart` | `sales_screen.dart`, `sales_provider.dart`, widgets |
| scan | `scan_service.dart` | `scan_screen.dart`, `scan_provider.dart` |
| stock_in | `stock_in_service.dart` | `stock_in_screen.dart`, `stock_in_provider.dart` |
| stock_opname | `stock_opname_service.dart` | `stock_opname_screen.dart`, `opname_session_screen.dart`, providers |
| stock_check | (placeholder) | `stock_check_screen.dart`, `stock_menu_screen.dart` |
| return_stock | `return_stock_service.dart` | `return_stock_screen.dart`, provider |
| reports | `reports_service.dart` | `reports_screen.dart`, `reports_provider.dart` |
| profile | — | `profile_screen.dart` |

---

## Pending (Belum Selesai) — Mobile

### P001 — Stok Opname Mobile: Tombol Scan Item
**Masalah:** Setelah buat sesi opname, tidak ada tombol "+ SCAN ITEM"
**File:** `lib/features/stock_opname/presentation/` (cek semua file)
**Yang dibutuhkan:**
1. Tombol "+ SCAN ITEM" di session detail screen
2. Flow: tap → kamera → scan QR → form qty fisik + rusak → SIMPAN
3. API: POST /api/stock-opnames/{id}/items
4. Setelah save: kembali ke session screen, item muncul di list
5. SUBMIT UNTUK VALIDASI → status jadi pending_validation

### P002 — Stok Opname: Block Semua Transaksi saat Sesi Aktif
**Status Laravel:** ✅ Sudah diimplementasi di SalesService, StockInService, TransferService (lihat root `CLAUDE.md`)
**Status Mobile:** 🔲 Perlu tambah warning banner + disable tab saat opname aktif
- Check GET /api/stock-opnames/active saat app startup
- Simpan di `activeOpnameProvider` (`lib/core/opname/active_opname_provider.dart`)
- Tampilkan banner amber di semua screen transaksi (`lib/core/widgets/opname_blocking_banner.dart`)
- Disable JUAL tab dan Barang Masuk
