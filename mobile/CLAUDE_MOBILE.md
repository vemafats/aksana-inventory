# CLAUDE_MOBILE.md — Aksana Inventory (Flutter)

SOURCE OF TRUTH: Global business rules, API contracts, roles, database rules, and coding rules live in the ROOT `CLAUDE.md`. ALWAYS read root `CLAUDE.md` first for any business logic. This file only covers Flutter/mobile implementation details.

---

## Mobile App

### Base URL Production
```dart
static const String baseUrl = 'https://app.ftrhijab.id/api';
```

### Version
- **Current:** v1.4.0+16 (`pubspec.yaml`)
- **APK naming:** `aksana-v{version}+{build}.apk` (e.g. `aksana-v1.4.0+16.apk`)
- Build number auto-increment via `build_apk.ps1`

### Tab Bar (5 tab)
```
SCAN | JUAL | STOK | STAT | AKUN
```
**Default tab setelah login:** `/stock` (STOK) — bukan SCAN.

### Format Angka
- **Selalu format lengkap:** `Rp 2.200.000`
- **TIDAK BOLEH** JT/RB/M/K
- Implementasi: `lib/core/utils/format_utils.dart` (`FormatUtils.formatPrice`)
- Legacy alias: `lib/core/utils/price_format.dart` → delegates ke `FormatUtils`
- Aturan global: root `CLAUDE.md` Coding Rules #11

### Employee / User Detection
User yang sedang login = operator transaksi.
`employee_id` di API = null (deprecated).
`user_id` = user id dari token Sanctum / `auth_provider`.

### Event-Based Location
- Lokasi penjualan dari **Event aktif** yang di-assign ke user
- Provider: `lib/core/event/active_event_provider.dart`, model `active_event.dart`
- API: `GET /api/events/my-active`
- Auto-select jika user hanya punya 1 event aktif (`MainScaffold`)

---

## Alur UI Mobile

### Transaksi Jual
```
Login → fetch event aktif → pilih/auto-select event →
scan QR → JUAL → cart + stepper qty + foto per item →
diskon nominal per item (opsional) →
BAYAR → Tunai / QRIS / Transfer Bank →
backend recalculate → simpan → stok berkurang
```
- Feature: `lib/features/sales/`, `lib/features/scan/`
- Foto item: `lib/features/sales/data/photo_service.dart`
- Provider: `sales_provider.dart`, `scan_provider.dart`
- Format harga di cart: selalu lengkap (`FormatUtils.formatPrice`)

### Browse Item
```
STOK → Browse Item → list katalog + foto + search →
tap item → detail / cek stok
```
- `lib/features/stock_check/presentation/browse_items_screen.dart`
- `lib/features/stock_check/data/browse_items_service.dart`
- `lib/features/stock_check/presentation/browse_items_provider.dart`

### Return Sisa
```
STOK menu → Return Sisa → pilih Event →
scan barcode → input qty good + damaged → KIRIM RETURN
```
- `lib/features/return_stock/` — event picker + `POST /api/returns`
- Provider: `return_stock_provider.dart`

### Stok Opname
```
SELALU Gudang Pusat (location_id gudang pusat hardcoded/auto)
1. Buat sesi
2. Session screen → tombol + SCAN ITEM
3. Scan QR → input QTY FISIK + QTY RUSAK
4. SIMPAN ITEM → lanjut scan
5. SUBMIT UNTUK VALIDASI → pending_validation
```
- Feature: `lib/features/stock_opname/`
- API: lihat root `CLAUDE.md` → API Stok Opname

### Cek Stok
```
STOK → Cek Stok → scan / browse →
card item + foto catalog di bawah card
```
- `lib/features/stock_check/presentation/stock_check_screen.dart`

### Opname Blocking (saat sesi aktif)
- Backend memblokir jual/barang masuk/transfer
- Mobile: `lib/core/opname/active_opname_provider.dart`
- Widget banner: `lib/core/widgets/opname_blocking_banner.dart`
- Guard submit: `isActiveOpnameBlocking()` sebelum sales/stock-in
- ⚠️ Banner belum ditampilkan global di semua tab (lihat Pending P002)

---

## Struktur Folder Flutter

**Stack:** Riverpod, GoRouter (`lib/core/router/app_router.dart`), Dio (`lib/core/api/api_client.dart`), `mobile_scanner` untuk QR.

```
lib/
├── main.dart
├── core/
│   ├── api/           → Dio client, interceptors, base URL
│   ├── auth/          → auth_provider, session/token
│   ├── event/         → active_event.dart, active_event_provider.dart
│   ├── opname/        → active_opname_provider (sesi opname aktif)
│   ├── router/        → GoRouter routes & shell (initialLocation: /stock)
│   ├── theme/         → app_colors, app_text_styles, app_theme
│   ├── utils/         → format_utils, price_format, location_helpers
│   └── widgets/       → main_scaffold, screen_header, opname_blocking_banner
└── features/
    ├── auth/          → login
    ├── profile/       → akun / profil user
    ├── reports/       → STAT tab, laporan ringkas mobile
    ├── return_stock/  → retur barang sisa ke gudang (event-based)
    ├── sales/         → JUAL tab, cart, checkout, photo_service
    ├── scan/          → SCAN tab, QR lookup katalog
    ├── stock_check/   → cek stok, browse items + foto catalog
    ├── stock_in/      → barang masuk (Admin Gudang)
    └── stock_opname/  → sesi opname, scan item, submit validasi
```

**Pola per feature:** `data/` (service + API) + `presentation/` (screens, providers, widgets).

| Feature | data/ | presentation/ |
|---|---|---|
| auth | auth API | `login_screen.dart` |
| sales | `sales_service.dart`, `photo_service.dart` | `sales_screen.dart`, `sales_provider.dart` |
| scan | `scan_service.dart` | `scan_screen.dart`, `scan_provider.dart` |
| stock_in | `stock_in_service.dart` | `stock_in_screen.dart`, `stock_in_provider.dart` |
| stock_opname | `stock_opname_service.dart` | `stock_opname_screen.dart`, `opname_session_screen.dart` |
| stock_check | `browse_items_service.dart` | `browse_items_screen.dart`, `stock_check_screen.dart`, `stock_menu_screen.dart` |
| return_stock | `return_stock_service.dart` | `return_stock_screen.dart`, `return_stock_provider.dart` |
| reports | `reports_service.dart` | `reports_screen.dart`, `reports_provider.dart` |
| profile | — | `profile_screen.dart` |

---

## Build & Versioning

### build_apk.ps1
- Auto-increment **build number** (`+N`) setiap build
- Semver (`x.y.z`) di-edit manual di `pubspec.yaml`
- Output: `build/app/outputs/flutter-apk/aksana-v{version}+{build}.apk`

### bump_version.ps1
- Manual semver bump (major/minor/patch)
- Setelah bump, jalankan `build_apk.ps1` untuk build

### Pattern
```
pubspec.yaml:  version: 1.4.0+16
APK file:      aksana-v1.4.0+16.apk
```

---

## Pending (Belum Selesai) — Mobile

| ID | Item | Status |
|---|---|---|
| P002 | Opname blocking banner global + disable tab saat sesi aktif | ⚠️ Partial — `OpnameBlockingBanner` + submit guard ada; belum refresh global di startup / semua screen |

*(P001 Stok Opname scan item: ✅ Fixed — tombol + SCAN ITEM di `opname_session_screen.dart`)*
