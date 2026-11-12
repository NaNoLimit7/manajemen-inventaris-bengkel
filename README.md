# Inventaris Bengkel

Aplikasi web manajemen inventaris untuk bengkel motor: mencatat barang masuk, barang keluar, penjualan, penyesuaian stok, peringatan stok/kedaluwarsa, pesanan pembelian, laporan, dan jejak audit — sebagai pengganti buku catatan stok manual.

Aplikasi dirancang untuk **satu pengguna (pemilik bengkel)** dengan gerbang akses satu kata sandi (HTTP Basic Auth), tanpa halaman login, registrasi, atau tabel pengguna. Tampilan memakai bahasa Indonesia, format Rupiah, dan zona waktu `Asia/Jakarta`.

---

## Daftar Isi

- [Status Proyek](#status-proyek)
- [Fitur Utama](#fitur-utama)
- [Teknologi](#teknologi)
- [Kebutuhan Sistem](#kebutuhan-sistem)
- [Instalasi Lokal](#instalasi-lokal)
- [Konfigurasi Environment](#konfigurasi-environment)
- [Menjalankan Aplikasi](#menjalankan-aplikasi)
- [Database dan Seeder](#database-dan-seeder)
- [Testing dan Kualitas Kode](#testing-dan-kualitas-kode)
- [Akun dan Akses](#akun-dan-akses)
- [Struktur Proyek](#struktur-proyek)
- [Alur Bisnis Penting](#alur-bisnis-penting)
- [Halaman dan Rute](#halaman-dan-rute)
- [Import CSV Barang](#import-csv-barang)
- [Ekspor CSV Laporan](#ekspor-csv-laporan)
- [Skema Database](#skema-database)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Dokumentasi Proyek](#dokumentasi-proyek)

---

## Status Proyek

| Milestone | Cakupan | Status |
|---|---|---|
| M1 | Fondasi, Basic Auth, master barang, import CSV, barang masuk/keluar, FEFO, riwayat | 17 Done, 1 Blocked (T-17 deploy awal) |
| M2 | Peringatan, pengaturan, penyesuaian, supplier, PO, pelanggan, penjualan, pembatalan | 18 Done |
| M2-fix | Perbaikan hasil verifikasi M2 | 5 Done |
| M3 | Laporan, kartu stok, ekspor CSV, audit, cetak PO, perapian ponsel | 13 Done, 1 Blocked (T-55 backup/deploy akhir) |

Seluruh fitur dan verifikasi lokal sudah selesai. Pekerjaan tersisa hanya ranah deployment (T-17 dan T-55) yang ditunda. Detail verifikasi ada di [`.agents/REVIEW.md`](.agents/REVIEW.md).

---

## Fitur Utama

### Akses
- Seluruh halaman dilindungi **HTTP Basic Auth** dari environment.
- Tanpa halaman login/registrasi dan tanpa tabel pengguna.
- Di environment `local`, akses boleh dibuka tanpa kredensial bila `BASIC_AUTH_USER`/`BASIC_AUTH_PASSWORD` kosong.
- Di production, kredensial kosong membuat aplikasi menolak akses (fail closed).

### Master Barang
- CRUD barang: kode otomatis/manual, nama, kategori, merek, satuan, harga beli terakhir, harga jual, stok minimum, lokasi rak, dan penanda kedaluwarsa.
- Tiga kategori bawaan: suku cadang habis pakai, servis rutin, dan pengganti utama.
- Pencarian nama/kode dan filter kategori.
- Barang yang sudah punya riwayat stok tidak dihapus, hanya dinonaktifkan.
- Penanda kedaluwarsa tidak boleh diubah setelah ada riwayat stok.
- Import CSV dengan pratinjau, validasi per baris, dan stok awal otomatis.
- Jejak audit otomatis untuk create/update/delete barang.

### Barang Masuk
- Header: supplier (opsional), tanggal, nomor faktur, catatan.
- Baris: barang, jumlah, harga beli, tanggal kedaluwarsa (wajib untuk barang berkedaluwarsa).
- Stok masuk per **batch** sesuai tanggal kedaluwarsa.
- Harga beli terakhir barang diperbarui otomatis.

### Barang Keluar dan Penjualan
- Pengambilan stok **FEFO** (batch kedaluwarsa terdekat lebih dulu; batch tanpa kedaluwarsa terakhir).
- Tujuan keluar: dijual, dipakai servis, pemakaian bengkel, rusak/hilang.
- Penjualan menyimpan harga jual per unit dan pelanggan opsional.
- Pelanggan dapat ditambahkan langsung dari form penjualan.
- Stok tidak boleh negatif; sistem menampilkan sisa stok jika gagal.

### Penyesuaian Stok
- Hitung fisik per batch dengan alasan wajib.
- Selisih nol tidak membuat pergerakan stok.
- Setiap penyesuaian tercatat di audit (`stock.adjusted`).

### Pembatalan Transaksi
- Tidak pernah menghapus data; dibuat dokumen **koreksi/pembalik** (`type = reversal`).
- Satu dokumen hanya bisa dibatalkan sekali.
- Alasan pembatalan wajib dan tercatat di audit (`stock.reversed`).
- Penerimaan dari PO ikut menyesuaikan `quantity_received` dan status PO.

### Peringatan
- Barang aktif dengan stok di bawah minimum.
- Batch mendekati kedaluwarsa (default 30 hari, dapat diubah di Pengaturan).
- Batch yang sudah kedaluwarsa.
- Lencana jumlah peringatan di navigasi semua halaman.

### Supplier, Pelanggan, dan Pesanan Pembelian
- CRUD supplier dan pelanggan; pelanggan memiliki riwayat serta total pembelian.
- PO menampilkan saran barang menipis dengan jumlah saran yang bisa diubah.
- Nomor PO otomatis `PO-YYYYMM-NNNN` dengan retry saat benturan unique.
- Alur status: `draft → ordered → partially_received → completed` atau `cancelled`.
- Penerimaan PO otomatis membuat transaksi barang masuk.
- Teks PO dapat disalin ke clipboard atau dicetak dari halaman detail.

### Laporan dan Audit
- Laporan stok dan nilai persediaan (stok × harga beli terakhir).
- Kartu stok per barang dengan saldo berjalan serta filter tanggal/jenis dokumen.
- Laporan penjualan harian/bulanan: total, jumlah transaksi, per barang, barang terlaris, dan per pelanggan.
- Laporan peringatan stok/kedaluwarsa.
- Riwayat audit dengan filter event, tipe data, dan rentang tanggal; detail `before`/`after` dapat dibuka.
- Semua laporan dapat diekspor ke CSV (BOM UTF-8, aman untuk Excel).
- Tampilan responsif untuk ponsel 390 px dan desktop.

---

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13 |
| Frontend | Inertia.js 3, Vue 3, Vite 8 |
| Styling | Tailwind CSS 4 |
| Database | MySQL 8+ / MariaDB 10.6+ |
| Testing | PHPUnit 12 |
| Formatter | Laravel Pint |
| Lingkungan lokal | Laragon |

Perhitungan uang dan jumlah memakai `bcmath`, bukan float. Semua operasi stok berjalan di dalam `DB::transaction`.

---

## Kebutuhan Sistem

- PHP **8.3+** dengan ekstensi `bcmath`, `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`, `tokenizer`, `xml`
- Composer 2
- Node.js 20.19+ atau 22.12+ dan npm
- MySQL 8+ atau MariaDB 10.6+
- Laragon (disarankan di Windows) atau stack PHP/MySQL lain

---

## Instalasi Lokal

### 1. Clone dan install dependensi

```bash
git clone <url-repositori> manajemen-inventaris-bengkel
cd manajemen-inventaris-bengkel

composer install
npm install
```

### 2. Siapkan environment

```bash
copy .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database di `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bengkel
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Buat database dan jalankan migrasi

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS bengkel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
php artisan db:seed --class=ProductionSeeder
```

### 4. Build frontend

```bash
npm run build
```

### 5. Jalankan aplikasi

```bash
php artisan serve
```

Buka `http://127.0.0.1:8000`.

### Alternatif cepat

```bash
composer run setup
php artisan db:seed --class=ProductionSeeder
```

`composer run setup` melakukan install dependensi, menyalin `.env`, generate key, migrasi, install npm, dan build. Seeder tetap dijalankan terpisah.

---

## Konfigurasi Environment

Variabel penting:

| Variabel | Keterangan |
|---|---|
| `APP_NAME` | Nama aplikasi yang tampil di antarmuka |
| `APP_KEY` | Kunci enkripsi Laravel (`php artisan key:generate`) |
| `APP_ENV` | `local` saat pengembangan, `production` saat deploy |
| `APP_DEBUG` | `true` hanya untuk lokal; production wajib `false` |
| `APP_URL` | URL aplikasi |
| `APP_TIMEZONE` | Gunakan `Asia/Jakarta` |
| `APP_LOCALE` | Gunakan `id` |
| `BASIC_AUTH_USER` | Username Basic Auth |
| `BASIC_AUTH_PASSWORD` | Kata sandi Basic Auth |
| `DB_*` | Koneksi MySQL/MariaDB |
| `SESSION_SECURE_COOKIE` | `true` di production (HTTPS) |

Catatan Basic Auth:
- Isi `BASIC_AUTH_USER` dan `BASIC_AUTH_PASSWORD` untuk mengaktifkan gerbang akses.
- Di `local`, kredensial kosong membuka akses tanpa login.
- Di production, kredensial kosong menghasilkan respons **503** (fail closed).
- Jangan commit nilai kredensial asli.

Template production tersedia di [`.env.production.example`](.env.production.example).

---

## Menjalankan Aplikasi

### Mode development lengkap (disarankan)

```bash
composer run dev
```

Menjalankan server Laravel, queue listener, dan Vite secara bersamaan. Jika tidak memakai perintah ini, jalankan di terminal terpisah:

```bash
php artisan serve
npm run dev
```

### Build production

```bash
npm run build
```

Jika perubahan frontend tidak tampil atau muncul error `Unable to locate file in Vite manifest`, jalankan `npm run build` atau pastikan `npm run dev` aktif.

---

## Database dan Seeder

- Database lokal default: `bengkel`
- Database test: `bengkel_test` (diatur di `phpunit.xml`)

```bash
# Migrasi
php artisan migrate

# Reset seluruh data lokal (hati-hati)
php artisan migrate:fresh --seed --seeder=ProductionSeeder

# Seeder produksi: kategori bawaan + expiry_warning_days=30
php artisan db:seed --class=ProductionSeeder
```

`ProductionSeeder` membuat:
- Kategori `consumable` (Suku cadang habis pakai)
- Kategori `routine` (Suku cadang servis rutin)
- Kategori `major` (Suku cadang pengganti utama)
- Setting `expiry_warning_days = 30`

---

## Testing dan Kualitas Kode

Pastikan database test tersedia:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS bengkel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Jika kredensial MySQL berbeda dari `root` tanpa password, sesuaikan `DB_USERNAME` dan `DB_PASSWORD` pada `phpunit.xml`.

Menjalankan seluruh test:

```bash
php artisan test --compact
```

Menjalankan test tertentu:

```bash
php artisan test --compact tests/Feature/StockReportTest.php
php artisan test --compact --filter=test_stock_report_calculates_stock_and_inventory_value
```

Format kode PHP:

```bash
vendor/bin/pint --dirty --format agent
```

Build frontend sebelum verifikasi akhir:

```bash
npm run build
```

Status terakhir (13 November 2026): **231 test lulus, 1472 assertion**, build berhasil, Pint bersih, smoke test 390 px dan 1280 px tanpa overflow maupun error JavaScript.

---

## Akun dan Akses

Aplikasi **tidak memiliki halaman login** dan **tidak memakai tabel pengguna**.

Akses memakai HTTP Basic Auth:

- Browser akan meminta username dan kata sandi saat membuka aplikasi.
- Tanpa kredensial yang benar: respons `401 Unauthorized`.
- Kredensial berasal dari `.env` (`BASIC_AUTH_USER`, `BASIC_AUTH_PASSWORD`).
- Untuk production, wajib memakai HTTPS agar kredensial tidak terkirim dalam teks biasa.

Health check tersedia di `/up`.

---

## Struktur Proyek

```text
app/
├── Exceptions/              # Exception domain (mis. stok tidak cukup)
├── Http/
│   ├── Controllers/         # Controller per modul
│   ├── Middleware/          # BasicAuthGate, HandleInertiaRequests
│   └── Requests/            # Validasi FormRequest
├── Models/                  # Item, StockBatch, StockDocument, dst.
├── Services/
│   ├── AlertService.php
│   ├── AuditService.php
│   ├── ItemCodeService.php
│   ├── ItemImportService.php
│   ├── PurchaseOrderService.php
│   ├── ReportService.php
│   └── StockService.php
└── Support/
    └── Auditable.php        # Trait audit master data

database/
├── factories/               # Factory untuk test
├── migrations/              # Skema database
└── seeders/                 # ProductionSeeder

resources/js/
├── Components/              # DataTable, ReportTabs, MoneyInput, dst.
├── Layouts/AppLayout.vue
├── Pages/
│   ├── Dashboard.vue
│   ├── Items/
│   ├── Stock/
│   ├── Suppliers/
│   ├── Customers/
│   ├── PurchaseOrders/
│   └── Reports/             # Stock, StockCard, Sales, Alerts, Audit
└── utils/formatters.js

routes/web.php               # Seluruh rute web
tests/Feature/               # Test integrasi per fitur
tests/Unit/                  # Test unit model
.agents/                     # PRD, Tech Spec, task list, laporan review
```

---

## Alur Bisnis Penting

### Stok dan Batch
- Stok saat ini = `SUM(stock_batches.quantity_remaining)`.
- Barang berkedaluwarsa memakai satu batch per tanggal kedaluwarsa.
- Barang tanpa kedaluwarsa memakai satu batch tanpa tanggal.
- `stock_movements` bersifat **immutable**: tidak boleh diubah atau dihapus.
- Stok tidak boleh negatif (dijaga service dan constraint database).

### FEFO
Barang keluar mengambil batch dengan `expiry_date` terdekat lebih dulu, batch tanpa kedaluwarsa paling akhir, lalu `received_on` terlama.

### Pembatalan
Pembatalan membuat dokumen `type = reversal` dengan pergerakan berkebalikan pada batch yang sama. Dokumen asal tidak dihapus dan tidak dapat dibatalkan dua kali.

### Penyesuaian
`delta = counted_quantity − batch.quantity_remaining`. Hanya baris dengan selisih bukan nol yang membuat pergerakan stok.

### Pesanan Pembelian
- Saran jumlah = `max(min_stock − stok, 0)`.
- Nomor PO memakai format `PO-YYYYMM-NNNN` dan retry maksimal tiga kali jika benturan unique.
- Penerimaan tidak boleh melebihi sisa pesanan.
- PO selesai/terima sebagian tidak dapat dibatalkan.

### Audit
Tercatat otomatis untuk:
- `item.created`, `item.updated`, `item.deleted`
- `supplier.created/updated/deleted`
- `customer.created/updated/deleted`
- `stock.adjusted` (dokumen penyesuaian)
- `stock.reversed` (dokumen pembalik)

Audit menyimpan data `before` dan `after` serta ikut rollback bila transaksi gagal.

### Uang dan Jumlah
Seluruh perhitungan memakai `bcmath`. Nilai persediaan = stok × `items.purchase_price` (harga beli terakhir).

---

## Halaman dan Rute

### Halaman utama

| Method | Path | Halaman |
|---|---|---|
| GET | `/` | Dashboard |
| GET/POST/PUT/DELETE | `/items`, `/items/{item}` | Master barang |
| GET/POST | `/items/import` | Import CSV barang |
| GET/POST | `/stock/in` | Barang masuk |
| GET/POST | `/stock/out` | Barang keluar / penjualan |
| GET/POST | `/stock/adjustments` | Penyesuaian stok |
| GET | `/stock/documents` | Riwayat transaksi |
| GET | `/stock/documents/{id}` | Detail transaksi |
| POST | `/stock/documents/{id}/reverse` | Pembatalan transaksi |
| GET/POST/PUT/DELETE | `/suppliers`, `/suppliers/{supplier}` | Supplier |
| GET/POST/PUT/DELETE | `/customers`, `/customers/{customer}` | Pelanggan |
| GET/POST/PUT/DELETE | `/purchase-orders`, `/purchase-orders/{purchaseOrder}` | Pesanan pembelian |
| GET/POST | `/purchase-orders/{id}/receive` | Penerimaan PO |
| POST | `/purchase-orders/{id}/order` | Tandai PO dipesan |
| POST | `/purchase-orders/{id}/cancel` | Batalkan PO |
| GET/PUT | `/settings` | Pengaturan peringatan kedaluwarsa |

### Laporan

| Method | Path | Halaman |
|---|---|---|
| GET | `/reports/stock` | Laporan stok dan nilai persediaan |
| GET | `/reports/stock-card/{item}` | Kartu stok per barang |
| GET | `/reports/sales` | Laporan penjualan per periode |
| GET | `/reports/alerts` | Laporan peringatan |
| GET | `/reports/audit` | Riwayat audit |

### Ekspor CSV

| Path | Isi |
|---|---|
| `/reports/stock.csv` | Laporan stok |
| `/reports/stock-card/{item}.csv` | Kartu stok per barang |
| `/reports/sales.csv` | Laporan penjualan (periode + pengelompokan) |
| `/reports/alerts.csv` | Tiga kategori peringatan |

Semua ekspor memakai `response()->streamDownload`, BOM UTF-8, header bahasa Indonesia, dan nama file bertanggal.

---

## Import CSV Barang

Kolom template (unduh dari halaman Import):

```text
code,name,category,brand,unit,purchase_price,sale_price,min_stock,shelf_location,has_expiry,initial_stock,expiry_date
```

Contoh:

```csv
code,name,category,brand,unit,purchase_price,sale_price,min_stock,shelf_location,has_expiry,initial_stock,expiry_date
BRG-OLI,Oli Mesin,consumable,Yamaha,botol,45000,55000,4,RAK-01,tidak,10,
BRG-FILTER,Filter Udara,major,Yamaha,pcs,40000,60000,2,RAK-03,ya,5,2027-01-01
```

Aturan:
- Pemisah koma atau titik koma; batas ukuran 2 MB.
- `category` menerima slug (`consumable`, `routine`, `major`) atau nama kategori.
- `has_expiry` menerima `ya/tidak/1/0/true/false`.
- `expiry_date` wajib jika `has_expiry` aktif.
- `code` kosong dibuat otomatis (`BRG-XXXXX`).
- Code duplikat di file maupun database ditolak.
- `initial_stock > 0` membuat satu dokumen barang masuk dengan catatan “Stok awal (import)”.
- Jika ada satu baris tidak valid, **tidak ada data yang disimpan**.
- Alur: unggah → pratinjau → konfirmasi import.

---

## Ekspor CSV Laporan

- Laporan stok: kode, nama, kategori, satuan, stok, minimum, harga beli terakhir, nilai persediaan, status.
- Kartu stok: tanggal, jenis, referensi, catatan, masuk, keluar, saldo, harga.
- Penjualan: ringkasan total, seri per periode, per barang, dan per pelanggan.
- Peringatan: stok menipis, batch mendekati kedaluwarsa, batch sudah kedaluwarsa.

Nilai sel yang diawali `=`, `+`, `-`, atau `@` di-escape agar tidak dieksekusi sebagai formula saat dibuka di Excel.

---

## Skema Database

| Tabel | Fungsi |
|---|---|
| `categories` | Tiga kategori barang bawaan |
| `items` | Master barang, harga, stok minimum, status aktif |
| `stock_batches` | Saldo per batch/kedaluwarsa |
| `stock_documents` | Header transaksi: masuk, keluar, penyesuaian, koreksi |
| `stock_movements` | Pergerakan stok immutable per batch |
| `settings` | Pengaturan aplikasi (`expiry_warning_days`) |
| `suppliers` | Master supplier |
| `customers` | Master pelanggan |
| `purchase_orders` | Header PO dan status |
| `purchase_order_items` | Baris PO, jumlah dipesan/diterima |
| `audit_logs` | Jejak audit polymorphic (`before`, `after`, `created_at`) |

Constraint penting:
- `items.code` unique.
- `stock_batches` unique per `(item_id, expiry_key)`.
- `stock_movements` quantity tidak boleh nol.
- `stock_batches.quantity_remaining >= 0`.
- `stock_documents.reverses_document_id` unique (mencegah pembatalan ganda).
- `purchase_orders.number` unique.
- `purchase_order_items` unique per `(purchase_order_id, item_id)`.

---

## Deployment

Deployment belum diselesaikan dan sengaja ditunda:

- **T-17** — deploy awal ke PaaS: `Blocked` (penyedia belum ditetapkan).
- **T-55** — backup dan deploy akhir: `Blocked`, bergantung pada T-17.

Checklist production yang sudah disiapkan:

1. Salin nilai dari [`.env.production.example`](.env.production.example) ke environment PaaS.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
3. Isi `BASIC_AUTH_USER` dan `BASIC_AUTH_PASSWORD`; jangan biarkan kosong.
4. Set `SESSION_SECURE_COOKIE=true` dan pastikan HTTPS aktif.
5. Siapkan MySQL terkelola dan isi `DB_*`.
6. Jalankan `php artisan migrate --force`.
7. Jalankan `php artisan db:seed --class=ProductionSeeder --force`.
8. Verifikasi Basic Auth: `401` tanpa kredensial, `200` dengan kredensial.
9. Verifikasi health check `/up`.
10. Aktifkan backup MySQL penyedia, periksa masa simpan, dan lakukan satu kali uji restore.
11. Gunakan ekspor CSV sebagai lapisan cadangan kedua.

Setelah deploy, lakukan smoke test alur nyata: barang masuk → barang keluar/penjualan → laporan → pembatalan.

---

## Troubleshooting

### Muncul popup username/password berulang atau respons 401
Isi `BASIC_AUTH_USER` dan `BASIC_AUTH_PASSWORD` di `.env`, lalu bersihkan cache konfigurasi:

```bash
php artisan config:clear
```

### Error `Unable to locate file in Vite manifest`
Frontend belum dibangun:

```bash
npm run build
# atau untuk development
npm run dev
```

### Error tabel `audit_logs` tidak ditemukan
Migrasi belum dijalankan:

```bash
php artisan migrate
```

### Test gagal karena database tidak ditemukan
Buat database test sesuai `phpunit.xml`:

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS bengkel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Perubahan kode tidak terbaca
Bersihkan cache Laravel:

```bash
php artisan optimize:clear
```

### Stok tidak cukup saat barang keluar
Periksa batch dengan `quantity_remaining > 0` dan tanggal kedaluwarsa. Sistem memakai FEFO dan menolak stok negatif.

---

## Dokumentasi Proyek

| Dokumen | Isi |
|---|---|
| [`.agents/1-PRD.md`](.agents/1-PRD.md) | Visi, persona, user stories, functional/non-functional requirements |
| [`.agents/2-TECH-SPEC.md`](.agents/2-TECH-SPEC.md) | Arsitektur, skema database, rute, alur bisnis, strategi test |
| [`.agents/3-TASKS.md`](.agents/3-TASKS.md) | Indeks milestone dan status task |
| [`.agents/3-TASKS/`](.agents/3-TASKS) | Detail task per milestone (M1, M2, M2-fix, M3) |
| [`.agents/REVIEW.md`](.agents/REVIEW.md) | Laporan verifikasi dan temuan per milestone |

---

## Catatan Ruang Lingkup v1

Di luar ruang lingkup: barcode/QR, work order servis, nota/struk pelanggan, stok opname terstruktur, multi-cabang, mode offline, notifikasi WhatsApp/email, integrasi POS/akuntansi, akun karyawan, retur terpisah, aplikasi Android, dan foto barang.

Aplikasi ini bersifat internal untuk satu bengkel dan satu pengguna.
