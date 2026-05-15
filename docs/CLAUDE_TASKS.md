# CLAUDE_TASKS.md — Aksana Inventory

Task breakdown per milestone untuk vibe coding dengan Claude Code.
Setiap task dirancang sebagai **satu sesi Claude Code** — fokus, spesifik, dan dapat diverifikasi.

> Baca `CLAUDE.md` sebelum mengerjakan task apapun.

---

## CARA PAKAI

1. Buka sesi Claude Code baru.
2. Ketik: `Read CLAUDE.md first, then do this task: [copy task di bawah]`
3. Review output sebelum lanjut ke task berikutnya.
4. Tandai task selesai dengan `[x]`.
5. Jangan gabungkan lebih dari 2 task dalam satu sesi.

---

## MILESTONE 1 — Backend Foundation

### M1-T1: Setup Laravel Project
```
Create a new Laravel 11 project named aksana-inventory.
Install packages: laravel/sanctum, spatie/laravel-permission, intervention/image.
Setup PostgreSQL connection in .env.example.
Create folder structure for Services, Enums, DTO, Actions as defined in CLAUDE.md.
Create all Enum classes: StockStatus, MovementType, DiscountType, LocationType, LocationStatus.
```

### M1-T2: Database Migrations — Master Data
```
Create migrations for these tables in order:
1. users (id uuid, name, email unique, password, role, is_active, timestamps)
2. categories (id uuid, code unique, name, is_active, timestamps)
3. brands (id uuid, name unique, is_active, timestamps)
4. product_models (id uuid, category_id fk, brand_id fk, name, is_active, timestamps) unique(category_id, brand_id, name)
5. colors (id uuid, name unique, code nullable, is_active, timestamps)
6. sizes (id uuid, name, size_type nullable, sort_order default 0, is_active, timestamps)
7. employees (id uuid, employee_code unique, name, phone nullable, email nullable, is_active, timestamps)
8. locations (id uuid, location_code unique, location_name, location_type, address nullable, start_date nullable, end_date nullable, status, timestamps)
9. location_assignments (id uuid, location_id fk, employee_id fk, role, start_date nullable, end_date nullable, is_active, timestamps)
10. settings (id uuid, setting_key unique, setting_value nullable, timestamps)
All primary keys must be UUID.
```

### M1-T3: Database Migrations — Inventory Core
```
Create migrations for inventory tables:
1. photos (id uuid, related_type nullable, related_id uuid nullable, photo_path text, photo_timestamp timestamp, latitude decimal(10,7) nullable, longitude decimal(10,7) nullable, watermark_text nullable, taken_by uuid fk users, timestamps)
2. items (id uuid, category_id fk, brand_id fk, model_id fk, color_id fk, size_id fk, sku unique, barcode unique, item_name, catalog_photo_path nullable, latest_supplier_cost decimal(18,2) default 0, latest_base_margin_type nullable, latest_base_margin_value decimal(18,2) default 0, latest_base_selling_price decimal(18,2) default 0, description nullable, is_active default true, timestamps)
3. stock_balances (id uuid, item_id fk, location_id fk, stock_status varchar, qty integer default 0, timestamps) unique(item_id, location_id, stock_status)
4. stock_movements (id uuid, movement_number unique, movement_type, item_id fk, from_location_id uuid nullable, to_location_id uuid nullable, from_stock_status nullable, to_stock_status nullable, qty integer, reference_type, reference_id uuid, note nullable, created_by fk users, created_at timestamp)
```

### M1-T4: Database Migrations — Transactions
```
Create migrations for transaction tables:
1. stock_in_transactions (id uuid, transaction_number unique, supplier_name nullable, transaction_date date, total_qty_received int, total_qty_available int, total_qty_damaged int, note nullable, photo_id uuid nullable fk photos, created_by fk users, timestamps)
2. stock_in_items (id uuid, stock_in_transaction_id fk, item_id fk, qty_received int, qty_available int, qty_damaged int, supplier_cost decimal(18,2), base_margin_type, base_margin_value decimal(18,2), base_selling_price decimal(18,2), qc_note nullable, timestamps)
3. transfer_transactions (id uuid, transfer_number unique, from_location_id fk, to_location_id fk, transfer_date date, status, note nullable, created_by fk users, timestamps)
4. transfer_items (id uuid, transfer_transaction_id fk, item_id fk, qty int, supplier_cost_snapshot decimal(18,2), base_margin_type_snapshot nullable, base_margin_value_snapshot decimal(18,2), base_selling_price_snapshot decimal(18,2), bazar_adjust_type, bazar_adjust_value decimal(18,2), bazar_selling_price decimal(18,2), note nullable, timestamps)
5. sales_transactions (id uuid, sales_number unique, location_id fk, employee_id fk, transaction_date timestamp, subtotal_amount decimal(18,2), item_discount_amount decimal(18,2), total_after_item_discount decimal(18,2), transaction_discount_type, transaction_discount_value decimal(18,2), transaction_discount_amount decimal(18,2), grand_total decimal(18,2), payment_method, note nullable, photo_id uuid nullable fk photos, created_by fk users, timestamps)
6. sales_items (id uuid, sales_transaction_id fk, item_id fk, qty int, supplier_cost_snapshot decimal(18,2), base_selling_price_snapshot decimal(18,2), bazar_selling_price_snapshot decimal(18,2), selling_price decimal(18,2), subtotal decimal(18,2), item_discount_type, item_discount_value decimal(18,2), item_discount_amount decimal(18,2), total_after_discount decimal(18,2), gross_profit decimal(18,2), timestamps)
7. stock_opname_transactions (id uuid, opname_number unique, location_id fk, opname_date date, status, note nullable, photo_id uuid nullable fk photos, created_by fk users, timestamps)
8. stock_opname_items (id uuid, stock_opname_transaction_id fk, item_id fk, system_available_qty int, physical_available_qty int, available_difference_qty int, damaged_qty int default 0, lost_qty int default 0, note nullable, timestamps)
```

### M1-T5: Models dan Relationships
```
Create Eloquent Models for all tables with complete relationships:
- User hasMany LocationAssignment, StockMovement
- Category hasMany ProductModel, Item
- Brand hasMany ProductModel, Item
- ProductModel belongsTo Category, Brand; hasMany Item
- Color hasMany Item
- Size hasMany Item
- Employee hasMany LocationAssignment, SalesTransaction
- Location hasMany LocationAssignment, StockBalance, StockMovement, TransferTransaction, SalesTransaction, StockOpnameTransaction
- Item belongsTo Category, Brand, ProductModel, Color, Size; hasMany StockBalance, StockMovement, StockInItem, TransferItem, SalesItem, StockOpnameItem
- StockBalance belongsTo Item, Location
- StockMovement belongsTo Item, User
All UUID primary keys. Add $fillable or use $guarded = [].
```

### M1-T6: Seeders
```
Create seeders with realistic data for butik:
- RoleSeeder: create 5 roles (owner, admin, admin_gudang, pic_bazar, sales)
- UserSeeder: create one user per role with password 'password'
- CategorySeeder: Sepatu, Tas, Kaos, Topi, Celana, Jaket
- BrandSeeder: Nike, Adidas, Zara, H&M, Lokal A, Lokal B
- ColorSeeder: Hitam, Putih, Merah, Biru, Abu-abu, Coklat, Navy, Hijau
- SizeSeeder: S, M, L, XL, XXL (tipe: clothing) dan 37, 38, 39, 40, 41, 42, 43 (tipe: shoes)
- LocationSeeder: 1 Gudang Pusat (status: active), 2 Bazar (status: active, draft)
- SettingSeeder: low_stock_threshold=1, enable_item_discount=true, enable_transaction_discount=true
- ProductModelSeeder: 3 models per brand-category combination
Run: php artisan db:seed
```

### M1-T7: Auth API
```
Create Auth API with Laravel Sanctum:
- POST /api/login: validate email+password, return token + user data + role
- POST /api/logout: revoke current token
- GET /api/me: return authenticated user with role and assigned locations
Create AuthController in app/Http/Controllers/Api/
Create LoginRequest with validation rules
Apply 'auth:sanctum' middleware to protected routes
Apply rate limiting to login endpoint (5 attempts per minute)
Write feature test: AuthTest (login success, login wrong password, logout)
```

---

## MILESTONE 2 — Admin Foundation

### M2-T1: Filament Setup dan Master Data CRUD
```
Install Filament v3: composer require filament/filament
Run: php artisan filament:install --panels
Create Filament Resources for:
- CategoryResource (code, name, is_active) with search and filter
- BrandResource (name, is_active)
- ProductModelResource (category, brand, name, is_active) with relationship selects
- ColorResource (name, code, is_active) — show color swatch preview
- SizeResource (name, size_type, sort_order, is_active) — sortable table
- EmployeeResource (employee_code, name, phone, email, is_active)
- LocationResource (location_code, location_name, location_type, address, start_date, end_date, status)
Apply role-based access: only Owner and Admin can access Master Data.
```

### M2-T2: CatalogService dan API
```
Create CatalogService with methods:
- generateItemName(brand, model, color, size): string
- generateSku(category, brand, model, color, size): string → format SPT-NIK-AIRMAX-HIT-40
- generateBarcode(sku): string (default = sku, must be unique)
- createCatalogItem(data): Item
- updateCatalogItem(item, data): Item

Create API endpoints:
- GET /api/catalogs (paginated, filter by category/brand/is_active, search by name/barcode)
- POST /api/catalogs
- GET /api/catalogs/{id}
- PUT /api/catalogs/{id}
- GET /api/catalogs/by-barcode/{barcode}

Create StoreCatalogRequest with validation.
Write feature test: CatalogTest (create, update, find by barcode, duplicate barcode rejected).
```

### M2-T3: Photo Upload API dan QR Code
```
Create PhotoService:
- uploadPhoto(file, relatedType, relatedId, takenBy): Photo
- addTimestamp(photo): annotate photo with date/time watermark
- compressPhoto(file): max 1MB, maintain aspect ratio

Create API:
- POST /api/photos (multipart/form-data, return photo_id)
- GET /api/photos/{id}

Create QR Code generation using simplesoftwareio/simple-qrcode:
- GET /api/catalogs/{id}/qrcode: generate and return QR Code image (PNG, size 200x200px)
  QR Code content = item barcode/SKU string (bukan URL)
  Response: image/png
- POST /api/catalogs/{id}/generate-qrcode: regenerate QR Code, simpan ke storage

QR Code spec:
- Format: QR Code (bukan Code128 atau barcode linear lain)
- Size: 200x200 pixel
- Error correction level: M (15%)
- Content: SKU string (contoh: SPT-NIK-AIRMAX-HIT-40)
- Background: putih, foreground: hitam

Validation foto: max file size 5MB, allowed types: jpg/jpeg/png.
Write test: PhotoUploadTest, QrCodeTest.
```

### M2-T4: Filament Catalog, QR Code Print, dan Harga Modal
```
Create Filament CatalogResource:
- Form: step-by-step (category → brand → model → color → size → auto name → photo)
  PENTING: form katalog TIDAK menyertakan field harga modal (supplier_cost) sama sekali
- Table: tampilkan foto katalog thumbnail, QR Code preview kecil, nama item, total stok, status aktif
  PENTING: kolom harga modal tidak ada di tabel katalog
- Action: "Cetak QR Code" — tersedia di row action dan bulk action
- Filter: kategori, merk, status aktif
- Search: nama item, SKU/barcode

Create QR Code print page (Blade template):
- URL: /admin/catalogs/{id}/print-qrcode
- Layout cetak A4, grid 4 kolom × 10 baris = 40 label per halaman
- Setiap label berisi: QR Code image (200x200px) + nama item (font kecil) + SKU
- CSS: @media print { no header/footer, no margins }
- Bisa print single item atau bulk (multiple items sekaligus via checkbox)

Create PasswordVerificationService:
- verifyPassword(userId, plainPassword): bool — verifikasi menggunakan Hash::check
- generateCostViewToken(userId): string — JWT atau signed string, TTL 15 menit
- validateCostViewToken(token, userId): bool

Create API endpoint:
- POST /api/verify-password
  Middleware: auth:sanctum + role:owner
  Request: { password: string }
  Response sukses: { cost_view_token: string, expires_at: datetime }
  Response gagal: 422 { message: "Password tidak sesuai" }

Create Filament StockResource (halaman Stok):
- Tampil default: nama item, SKU, lokasi, qty available, qty damaged, qty lost
  TIDAK ADA kolom harga modal secara default
- Tombol "Lihat Harga Modal" hanya muncul jika user = Owner
- Klik tombol → Filament modal dialog muncul → input password → submit
- Jika benar: refresh tabel dengan kolom tambahan (Harga Modal, Total Modal)
- Jika salah: tampil notifikasi error

Write test: PasswordVerificationTest, CostViewTokenTest.
```

---

## MILESTONE 3 — Inventory Engine

### M3-T1: StockBalanceService
```
Create StockBalanceService with methods:
- getBalance(itemId, locationId, status): int
- getBalanceByLocation(locationId): Collection
- getBalanceByItem(itemId): Collection
- increase(itemId, locationId, status, qty, DB_transaction): void
- decrease(itemId, locationId, status, qty, DB_transaction): void — THROW exception if qty insufficient
- move(itemId, fromLocationId, toLocationId, fromStatus, toStatus, qty): void
- validateEnoughStock(itemId, locationId, status, qty): bool
- upsert(itemId, locationId, status, qtyChange): void — create if not exists, update if exists

All mutating methods must be called inside DB::transaction() by the caller.
Write unit test: StockBalanceServiceTest — increase, decrease, decrease insufficient stock throws exception.
```

### M3-T2: StockMovementService
```
Create StockMovementService with methods:
- createMovement(data array): StockMovement
- generateMovementNumber(): string → format SM-YYYYMMDD-XXXXX
- linkToReference(movementId, referenceType, referenceId): void

Movement must record: movement_type, item_id, from/to location, from/to status, qty, reference_type, reference_id, created_by.
Write unit test: StockMovementServiceTest — movement number unique, all fields saved correctly.
```

### M3-T3: StockInService + API
```
Create StockInService:
- validateBarcodes(barcodes[]): throw if any barcode not found in items catalog
- createTransaction(data): StockInTransaction
  - For each item: increase warehouse available, increase warehouse damaged
  - Create stock_movements for each line (STOCK_IN_AVAILABLE, STOCK_IN_DAMAGED)
  - Update items.latest_supplier_cost, latest_base_selling_price
  - Attach photo with timestamp
  - All inside DB::transaction()

Create API:
- POST /api/stock-in (create transaction with multiple items)
- GET /api/stock-in (paginated list)
- GET /api/stock-in/{id} (detail with items)

Create StockInRequest validation:
- items[].barcode must exist in items table
- items[].qty_received = items[].qty_available + items[].qty_damaged
- items[].qty_received > 0

Write feature test: StockInTest — stock increases correctly, movement created, invalid barcode rejected, qty mismatch rejected.
```

### M3-T4: TransferService + API
```
Create TransferService:
- createTransfer(data): TransferTransaction (status: draft)
- completeTransfer(transfer): void
  - Validate source has enough available stock
  - For each item: decrease source available, increase destination available
  - Create TRANSFER_AVAILABLE movement
  - Save price snapshots + bazar_selling_price
  - All inside DB::transaction()
- cancelTransfer(transfer): void (only if status: draft)

Create API:
- POST /api/transfers
- GET /api/transfers
- GET /api/transfers/{id}
- POST /api/transfers/{id}/complete
- POST /api/transfers/{id}/cancel

Write feature test: TransferTest — stock moves correctly, insufficient stock rejected, snapshot price saved.
```

### M3-T5: StockOpnameService + API
```
Create StockOpnameService:
- createOpname(locationId, date, items[], photoId, userId): StockOpnameTransaction (status: draft)
- readSystemStock(itemId, locationId): int
- completeOpname(opname): void
  - For each item:
    - If physical > system: increase available (STOCK_OPNAME_PLUS movement)
    - If physical < system: decrease available + increase lost (STOCK_OPNAME_LOST movement)
    - If damaged_qty > 0: decrease available + increase damaged (AVAILABLE_TO_DAMAGED movement)
  - Update status to completed
  - All inside DB::transaction()

Create API:
- POST /api/stock-opnames
- GET /api/stock-opnames
- GET /api/stock-opnames/{id}
- POST /api/stock-opnames/{id}/complete

Create LocationCloseService:
- canClose(location): bool → check all stock_balances.qty = 0
- closeLocation(location, userId): void → update status to closed
- API: POST /api/locations/{id}/close (role: owner, admin, admin_gudang, pic_bazar only)

Write feature test: StockOpnameTest — adjustment plus/minus/damaged correct, cannot close location with remaining stock.
```

---

## MILESTONE 4 — Sales Engine

### M4-T1: PriceCalculationService
```
Create PriceCalculationService with methods:
- calculateBaseSellingPrice(cost, marginType, marginValue): decimal
- calculateBazarSellingPrice(basePrice, adjustType, adjustValue): decimal
- calculateItemDiscount(price, qty, discountType, discountValue): decimal
- calculateTransactionDiscount(subtotal, discountType, discountValue): decimal
- calculateGrossProfit(totalAfterDiscount, supplierCostSnapshot, qty): decimal

All calculations must be pure functions (no side effects, no DB calls).
Respect settings: enable_item_discount, enable_transaction_discount.
Write unit test: PriceCalculationServiceTest — all calculation scenarios including edge cases (zero discount, 100% discount, etc).
```

### M4-T2: SalesService + API
```
Create SalesService:
- validateActiveLocation(locationId): void — throw if location status != active
- validateEmployeeAssignment(employeeId, locationId): void — throw if not assigned
- validateStock(items[]): void — throw if any item qty > available at location
- createTransaction(data): SalesTransaction
  - Recalculate all prices on backend (never trust frontend totals)
  - For each item: decrease location available stock, create SALE movement
  - Save all price snapshots
  - Calculate gross_profit per item
  - Attach photo
  - All inside DB::transaction()

Create API:
- POST /api/sales
- GET /api/sales (filter by location, employee, date range)
- GET /api/sales/{id}

Create SalesRequest validation.
Write feature test: SalesTest — stock decreases, snapshot price correct, cannot sell more than available, gross profit calculated correctly.
```

---

## MILESTONE 5 — Mobile App

### M5-T1: Flutter Project Setup
```
Create Flutter project: aksana_mobile
Add dependencies to pubspec.yaml:
- dio: ^5.x (HTTP client)
- flutter_riverpod: ^2.x (state management)
- go_router: ^12.x (routing)
- mobile_scanner: ^5.x (barcode scanner)
- camera: ^0.11.x (photo capture)
- shared_preferences: ^2.x (token storage)
- intl: ^0.19.x (formatting)
- image_picker: ^1.x

Create folder structure:
lib/
  core/api/ (ApiClient with Dio, base URL, token interceptor)
  core/auth/ (AuthService, token storage)
  core/router/ (GoRouter config, routes)
  core/widgets/ (shared widgets)
  features/ (one folder per feature)

Create ApiClient: base URL from env, attach Bearer token, handle 401 logout.
```

### M5-T2: Flutter Login + Auth
```
Create LoginScreen:
- Email and password fields
- Login button → POST /api/login
- Store token in SharedPreferences
- Navigate to HomeScreen on success
- Show error message on failed login

Create AuthProvider (Riverpod): manage login state, token, user role.
Create AuthGuard in GoRouter: redirect to login if no token.
Create AccountScreen: show user name, role, logout button.
```

### M5-T3: Flutter HomeScreen + Scan
```
Create HomeScreen:
- Greeting with user name and role
- Low-stock badge (fetch GET /api/reports/low-stock-items, show count badge)
- Shortcut cards based on role:
  - Stock In (Admin Gudang)
  - Jual (PIC Bazar, Sales)
  - Stok Opname (all)
  - Return (PIC Bazar)

Create ScanScreen:
- Live camera view with mobile_scanner
- Decode barcode → GET /api/catalogs/by-barcode/{barcode}
- If found: show item card (foto, nama, stok) + action buttons based on role
- If not found: show error "Barcode tidak ditemukan. Buat katalog dulu di web admin."
```

### M5-T4: Flutter Stock In Flow
```
Create StockInScreen:
- Header: supplier name input + date picker
- Scan barcode button → open ScanScreen
- After scan: show item card (foto katalog, nama, barcode, harga modal referensi)
- Input fields: qty terima, qty lolos QC, qty rusak
- Validation: qty_terima must equal qty_lolos + qty_rusak
- Harga modal aktual + margin → auto-calculate harga jual dasar
- Photo capture button → show preview + timestamp overlay
- Item list (multiple items can be added in one transaction)
- Save button → POST /api/stock-in
- Show success/error state
```

### M5-T5: Flutter Sales Flow
```
Create SalesCartScreen:
- Location display (user's assigned location)
- Scan button → add item to cart
- Cart list: nama item, qty stepper, harga bazar, diskon per item
- Cart summary: subtotal, diskon transaksi, grand total
- Payment method selector: Cash | QRIS | Transfer
- If QRIS: show QRIS image from settings
- Photo capture (optional)
- Checkout button → POST /api/sales
- Show struk ringkas setelah sukses (no-print version for MVP)
```

### M5-T6: Flutter Stock Opname + Return
```
Create StockOpnameScreen:
- Location picker
- Scan barcode → show item + stok sistem (from /api/stocks)
- Input: qty fisik available, qty damaged ditemukan
- System auto-calculate qty lost (sistem - fisik - damaged)
- Photo capture + timestamp
- List items yang sudah di-scan
- Complete button → POST /api/stock-opnames/{id}/complete

Create ReturnStockScreen:
- Show current bazar stock (available items)
- For each item: input qty kembali available, qty damaged
- Photo capture
- Save → POST /api/transfers (return ke gudang pusat)

Create close bazar button in location detail:
- Show only for role: owner, admin, admin_gudang, pic_bazar
- Tap "Tutup Bazar" → confirmation dialog
- Tap "Tutup" again → POST /api/locations/{id}/close
- Disable jika masih ada stok (tampilkan pesan)
```

---

## MILESTONE 6 — Reports

### M6-T1: Report API — Stock dan Modal
```
Create ReportService with methods:
- dashboardSummary(userId): total stok gudang, total bazar aktif, omzet hari ini, produk low-stock count
- warehouseStock(): items dengan qty per status di gudang pusat
- locationStock(locationId): items dengan qty per status di lokasi
- totalCapital(): SUM(stock_balances.qty * items.latest_supplier_cost) per lokasi
- lowStockItems(userId): items where available qty <= 1 (threshold tetap 1, tidak dari settings), filtered by role

Create API endpoints:
- GET /api/reports/dashboard-summary
- GET /api/reports/warehouse-stock (paginated, filter by category/brand)
- GET /api/reports/location-stock (filter by location_id)
- GET /api/reports/total-capital
- GET /api/reports/low-stock-items
```

### M6-T2: Report API — Sales dan Profit
```
Add to ReportService:
- grossProfit(dateFrom, dateTo, locationId?): total net sales - total COGS
- bestSellingProducts(dateFrom, dateTo, limit): ranked by SUM(qty) from sales_items
- salesByLocation(dateFrom, dateTo): grouped by location
- salesByEmployee(dateFrom, dateTo): grouped by employee
- productSummary(itemId): stok gudang, stok bazar, total terjual, total modal, total penjualan, gross profit

Create API endpoints:
- GET /api/reports/gross-profit
- GET /api/reports/best-selling-products
- GET /api/reports/sales-by-location
- GET /api/reports/sales-by-employee
- GET /api/reports/product-summary

Write test: ReportTest — gross profit formula correct, best-seller ranking correct.
```

### M6-T3: Filament Reports + Dashboard
```
Create Filament dashboard widgets:
- StatsOverview: total stok gudang, total modal, omzet hari ini, gross profit bulan ini
- LowStockAlert: tabel item dengan stok menipis + badge count di sidebar

Create Filament pages for reports:
- WarehouseStockReport (table + export)
- SalesReport (filter by date, location, employee)
- GrossProfitReport
- BestSellingReport (sortable table)

Add stock movement audit trail table in Filament (read-only, all mutations visible).
```

---

## MILESTONE 7 — Pilot Ready

### M7-T1: Security Hardening
```
Add missing security measures:
- Rate limiting: login (5/min), api (60/min per token)
- Request size limit: max 10MB per request
- Photo validation: mime type whitelist, max 5MB
- Policy classes for each resource (ensure role-based access enforced at model level)
- Sanctum token expiry: 30 days
- Add CORS configuration for web admin domain

Run: php artisan test --coverage
Target: 80% coverage on Service classes.
```

### M7-T2: Performance
```
Add database indexes:
- stock_balances: index(item_id, location_id, stock_status)
- stock_movements: index(item_id, created_at), index(reference_type, reference_id)
- sales_transactions: index(location_id, transaction_date)
- sales_items: index(item_id)

Add pagination to all list endpoints (default 20 per page).
Add eager loading to prevent N+1 queries on report endpoints.
Add Redis caching for dashboard summary (TTL: 5 minutes).
Test API response time < 500ms for list endpoints.
```

### M7-T3: VPS Deployment
```
Deploy to Hostinger VPS:
1. Server setup: Ubuntu, Nginx, PHP 8.2-FPM, PostgreSQL 15, Redis, Supervisor, Certbot SSL
2. Deploy Laravel: git clone, composer install --no-dev, php artisan migrate, storage:link, cache:config/route/view
3. Setup Supervisor for queue:work
4. Setup cron: * * * * * php artisan schedule:run
5. Setup SSL: certbot --nginx
6. Setup daily DB backup script
7. Test all API endpoints on production
8. Build Flutter APK release, install on test devices
9. Run full user acceptance test with real users
```

### M7-T4: UAT Checklist
```
Test scenarios wajib sebelum go-live:

KATALOG:
[ ] Buat item katalog baru dari awal (pilih kategori → merk → model → warna → ukuran)
[ ] Nama item ter-generate otomatis
[ ] Barcode ter-generate dan bisa dicetak

BARANG MASUK:
[ ] Scan barcode item yang sudah di katalog → detail muncul
[ ] Scan barcode yang tidak ada → ditolak
[ ] Input qty terima ≠ lolos + rusak → ditolak
[ ] Stok gudang bertambah setelah save

TRANSFER:
[ ] Kirim barang dari gudang ke bazar → stok gudang berkurang, stok bazar bertambah
[ ] Transfer melebihi stok → ditolak

PENJUALAN:
[ ] Scan item di bazar → harga bazar muncul
[ ] Jual 1 item → stok bazar berkurang
[ ] Jual melebihi stok → ditolak
[ ] Diskon per item dan per transaksi dihitung benar
[ ] QRIS image muncul saat pilih payment QRIS

STOK OPNAME:
[ ] Scan item → stok sistem muncul
[ ] Input fisik lebih → stok naik
[ ] Input fisik kurang → stok turun, lost bertambah
[ ] Input damaged → available turun, damaged naik

RETURN & TUTUP BAZAR:
[ ] Return sisa bazar → stok kembali ke gudang
[ ] Tutup bazar dengan stok sisa → ditolak
[ ] Tutup bazar setelah semua return → berhasil
[ ] Sales tidak bisa tombol tutup bazar

LAPORAN:
[ ] Dashboard summary akurat
[ ] Gross profit = total penjualan - total modal barang terjual
[ ] Low-stock badge muncul jika ada item ≤ threshold
```

---

## CATATAN UNTUK VIBE CODING

- **Satu task = satu sesi.** Jangan gabungkan M1-T1 + M1-T2 dalam satu sesi.
- **Selalu minta Claude tulis test** bersamaan dengan implementasi.
- **Review output sebelum lanjut.** Jalankan `php artisan test` setelah setiap milestone.
- **Commit setelah setiap task selesai** dan test hijau.
- **Jika Claude melebar**, tempel kembali rules dari CLAUDE.md ke sesi baru.
- **Dokumentasikan perubahan** jika ada keputusan baru yang tidak ada di CLAUDE.md.
