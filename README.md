# Sistem Absensi Pegawai - RSUD Merauke

Sistem manajemen absensi digital untuk **RSUD Merauke** (Rumah Sakit Umum Daerah Merauke, Papua). Dibangun dengan **Laravel 13.8** dan **Tailwind CSS 4.3**, menyediakan fitur pencatatan kehadiran berbasis GPS + selfie, pengajuan izin multi-level approval, manajemen shift, rekap kehadiran bulanan, serta integrasi API dengan SIMRS.

Dimigrasi dari CodeIgniter 4 ke Laravel (dokumentasi migrasi: [`MIGRASI_LARAVEL.md`](MIGRASI_LARAVEL.md)).

---

## Fitur Utama

### Pegawai
- Absen masuk/keluar dengan validasi GPS geofencing + selfie
- Pilihan shift harian
- Pengajuan izin/sakit/cuti/dinas luar dengan upload lampiran
- Persetujuan izin bertingkat (multi-level approval)
- Dashboard kehadiran pribadi
- Lihat struktur organisasi
- Riwayat kehadiran 7 hari terakhir + statistik bulanan

### Admin
- Dashboard dengan grafik kehadiran bulanan
- CRUD pegawai, unit kerja, sub-unit, profesi, jabatan
- Manajemen shift dan jadwal shift per pegawai
- Kalender hari libur
- Lihat kehadiran harian dengan peta lokasi absen
- Proses pengajuan izin + ambil alih approval
- Rekap kehadiran bulanan (cetak + export Excel)
- Pengaturan sistem (lokasi, radius, toleransi, dll.)
- Generate backup database
- Log aktivitas sistem

### API
- **Mobile API** - autentikasi token untuk aplikasi mobile
- **SIMRS Integration API** - autentikasi API key untuk integrasi dengan sistem informasi rumah sakit

---

## Persyaratan

| Komponen | Versi |
|----------|-------|
| PHP | >= 8.3 |
| Laravel | 13.8 |
| Node.js | >= 18 |
| Database | SQLite (default) / MySQL / MariaDB |

---

## Instalasi

### 1. Clone Repository

```bash
git clone <url-repo>
cd backend-absensi
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Frontend Dependencies

```bash
npm install
```

### 4. Konfigurasi Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai kebutuhan:

```env
APP_NAME="Absensi RSUD Merauke"
APP_URL=http://localhost:8000

# SQLite (default, tanpa konfigurasi tambahan)
DB_CONNECTION=sqlite

# Atau MySQL
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=absensi_rsud_merauke
# DB_USERNAME=root
# DB_PASSWORD=
```

### 5. Jalankan Migrasi & Seeder

```bash
php artisan migrate
php artisan db:seed
```

### 6. Buat Storage Symlink

```bash
php artisan storage:link
```

### 7. Jalankan Development Server

```bash
# Backend (terminal 1)
php artisan serve

# Frontend (terminal 2)
npm run dev
```

### 8. Instalasi via Web (Alternatif)

Buka `/install` di browser untuk wizard instalasi yang akan menjalankan migrasi dan membuat akun admin pertama.

---

## Struktur Proyek

```
backend-absensi/
├── app/
│   ├── Helpers/
│   │   └── absensi.php                    # 30+ fungsi helper global
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php         # Login/Register/Logout
│   │   │   ├── DashboardController.php    # Dashboard pegawai
│   │   │   ├── AbsenController.php        # Absen masuk/keluar
│   │   │   ├── IzinController.php         # Pengajuan izin
│   │   │   ├── PersetujuanController.php  # Persetujuan izin
│   │   │   ├── StrukturController.php     # Struktur organisasi
│   │   │   ├── VerifikasiController.php   # Verifikasi dokumen publik
│   │   │   ├── FotoController.php         # Serve foto selfie/lampiran
│   │   │   ├── InstallController.php      # Wizard instalasi
│   │   │   ├── Api/
│   │   │   │   ├── MobileController.php   # API mobile (token auth)
│   │   │   │   └── V1Controller.php       # API integrasi SIMRS
│   │   │   └── Admin/
│   │   │       ├── DashboardController.php
│   │   │       ├── PegawaiController.php
│   │   │       ├── KehadiranController.php
│   │   │       ├── IzinController.php
│   │   │       ├── UnitController.php
│   │   │       ├── ShiftController.php
│   │   │       ├── JadwalController.php
│   │   │       ├── LiburController.php
│   │   │       ├── StrukturController.php
│   │   │       ├── RekapController.php
│   │   │       ├── PengaturanController.php
│   │   │       └── AktivitasController.php
│   │   └── Middleware/
│   │       ├── CheckAuth.php              # Autentikasi session web
│   │       ├── CheckAdmin.php             # Cek role admin
│   │       ├── CheckApiKey.php            # Validasi X-API-KEY
│   │       └── CheckMobileAuth.php        # Autentikasi token mobile
│   ├── Mail/
│   │   └── PengajuanIzinMail.php
│   ├── Models/                            # 17 Eloquent models
│   ├── Notifications/
│   │   └── PengajuanIzinNotification.php
│   └── Services/                          # 10 service classes
│       ├── AbsenService.php               # Logika absen inti
│       ├── AlurIzinService.php            # Workflow approval multi-level
│       ├── AnomaliService.php             # Deteksi anomali GPS
│       ├── BintangService.php             # Sistem rating bintang
│       ├── CutiService.php                # Manajemen kuota cuti
│       ├── DatabaseBackupService.php      # Backup database SQL
│       ├── RekapExportService.php         # Export rekap bulanan
│       ├── RekapService.php               # Kalkulasi kehadiran
│       ├── StrukturService.php            # Logika tree struktur org
│       └── WhatsAppService.php            # Integrasi Fonnte WhatsApp
├── database/
│   ├── migrations/                        # 25 migration files
│   └── seeders/                           # 6 seeders
├── resources/views/                       # 31 Blade templates
├── routes/
│   ├── web.php                            # 17 route web
│   ├── api.php                            # 24 route API
│   ├── admin.php                          # 20 route admin
│   └── console.php
└── storage/app/public/
    └── selfie/                            # Foto selfie absen
```

---

## Database

### Model & Tabel

| Model | Tabel | Keterangan |
|-------|-------|------------|
| `User` | `users` | Pegawai & admin |
| `Absensi` | `absensi` | Catatan kehadiran harian |
| `Izin` | `pengajuan_izin` | Pengajuan izin/sakit/cuti |
| `IzinPersetujuan` | `izin_persetujuan` | Riwayat persetujuan per tahap |
| `Shift` | `shift` | Master shift kerja |
| `UnitKerja` | `unit_kerja` | Unit kerja |
| `SubUnit` | `sub_unit` | Sub unit kerja |
| `Profesi` | `profesi` | Master profesi |
| `Jabatan` | `jabatan` | Jabatan (self-referencing) |
| `JadwalShift` | `jadwal_shift` | Jadwal shift per pegawai |
| `HariLibur` | `hari_libur` | Kalender hari libur |
| `LogLokasi` | `log_lokasi` | Log lokasi GPS saat absen |
| `AktivitasLog` | `aktivitas_log` | Audit log aktivitas |
| `LoginAttempt` | `login_attempts` | Log percobaan login |
| `RekapBulanan` | `rekap_bulanan` | Rekap kehadiran bulanan |
| `Pengaturan` | `pengaturan` | Key-value pengaturan sistem |
| `ApiToken` | `api_tokens` | Token autentikasi mobile |

---

## Autentikasi

Sistem ini menggunakan **3 mekanisme autentikasi kustom** (tanpa Sanctum/Passport):

### 1. Web (Session-based)
- Middleware: `CheckAuth` (alias `auth`)
- Session: `uid`, `role`, `nama`, `posisi`
- Brute-force protection: maks 5 percobaan gagal per 15 menit

### 2. Mobile API (Token Cookie)
- Middleware: `CheckMobileAuth` (alias `mobile.auth`)
- Token 64 karakter disimpan di tabel `api_tokens`, di-set sebagai HttpOnly cookie `auth_token`
- Masa berlaku token: 7 hari

### 3. SIMRS Integration API (API Key)
- Middleware: `CheckApiKey` (alias `api.key`)
- Header: `X-API-KEY`
- Key disimpan di tabel `pengaturan`, dapat di-regenerate oleh admin

---

## Route

### Web Routes (`routes/web.php`)

| Method | URL | Keterangan |
|--------|-----|------------|
| GET | `/` | Halaman beranda |
| GET | `/install` | Wizard instalasi |
| POST | `/install` | Buat admin pertama |
| GET | `/login` | Halaman login |
| POST | `/login` | Proses login |
| GET | `/register` | Halaman registrasi |
| POST | `/register` | Proses registrasi |
| GET | `/logout` | Logout |
| GET | `/dashboard` | Dashboard pegawai |
| POST | `/absen` | Proses absen masuk/keluar |
| POST | `/pilih-shift` | Pilih shift |
| GET | `/struktur` | Struktur organisasi |
| GET | `/izin` | Daftar pengajuan izin |
| POST | `/izin` | Ajukan izin |
| POST | `/izin/batal/{id}` | Batalkan izin |
| GET | `/izin/dokumen/{id}` | Lihat dokumen izin |
| POST | `/izin/tanda-tangan/{id}` | Tanda tangan digital (Direktur) |
| GET | `/persetujuan` | Daftar persetujuan |
| POST | `/persetujuan/proses` | Setujui/tolak izin |
| GET | `/foto/{id}/{tipe}` | Serve foto |
| GET | `/lampiran-izin/{id}` | Serve lampiran izin |
| GET | `/verifikasi/{kode}` | Verifikasi dokumen (publik) |

### Admin Routes (`routes/admin.php`)

| Method | URL | Keterangan |
|--------|-----|------------|
| GET | `/admin` | Dashboard admin |
| GET | `/admin/pegawai` | Daftar pegawai |
| GET | `/admin/pegawai/form/{id?}` | Form tambah/edit pegawai |
| POST | `/admin/pegawai/simpan` | Simpan pegawai |
| POST | `/admin/pegawai/status` | Aktifkan/nonaktifkan pegawai |
| POST | `/admin/pegawai/hapus` | Hapus pegawai |
| GET | `/admin/unit` | Manajemen unit kerja |
| POST | `/admin/unit/aksi` | CRUD unit kerja |
| GET | `/admin/struktur` | Manajemen struktur org |
| POST | `/admin/struktur/aksi` | CRUD struktur org |
| GET | `/admin/shift` | Manajemen shift |
| POST | `/admin/shift/aksi` | CRUD shift |
| GET | `/admin/jadwal` | Manajemen jadwal shift |
| POST | `/admin/jadwal/aksi` | CRUD jadwal shift |
| GET | `/admin/kehadiran` | Kehadiran harian + peta |
| GET | `/admin/izin` | Daftar izin |
| POST | `/admin/izin/proses` | Proses izin admin |
| POST | `/admin/izin/ambil-alih` | Ambil alih persetujuan |
| GET | `/admin/libur` | Kalender hari libur |
| POST | `/admin/libur/aksi` | CRUD hari libur |
| GET | `/admin/rekap` | Rekap bulanan |
| POST | `/admin/rekap/generate` | Generate rekap |
| GET | `/admin/rekap/cetak` | Cetak rekap |
| GET | `/admin/rekap/excel` | Export Excel |
| GET | `/admin/pengaturan` | Pengaturan sistem |
| POST | `/admin/pengaturan` | Simpan pengaturan |
| POST | `/admin/pengaturan/api-key` | Regenerate API key |
| GET | `/admin/pengaturan/backup` | Download backup DB |
| GET | `/admin/aktivitas` | Log aktivitas |

### Mobile API Routes (`routes/api.php`)

| Method | URL | Auth | Keterangan |
|--------|-----|------|------------|
| POST | `/api/mobile/login` | - | Login mobile |
| GET | `/api/mobile/register/master` | - | Data master registrasi |
| POST | `/api/mobile/register` | - | Registrasi mobile |
| GET | `/api/mobile/me` | Token | Profil pengguna |
| POST | `/api/mobile/logout` | Token | Logout |
| POST | `/api/mobile/absen` | Token | Absen masuk/keluar |
| GET | `/api/mobile/status` | Token | Status absen hari ini |
| GET | `/api/mobile/riwayat` | Token | Riwayat 7 hari |
| GET | `/api/mobile/statistik` | Token | Statistik bulanan |
| GET | `/api/mobile/performa/bulan` | Token | Rating bintang bulan lalu |
| GET | `/api/mobile/jadwal` | Token | Jadwal shift |
| GET | `/api/mobile/izin` | Token | Daftar izin |
| POST | `/api/mobile/izin` | Token | Ajukan izin |
| DELETE | `/api/mobile/izin/{id}` | Token | Batalkan izin |
| GET | `/api/mobile/izin/today` | Token | Izin aktif hari ini |
| GET | `/api/mobile/izin/total` | Token | Jumlah izin pending |
| GET | `/api/mobile/izin/detail` | Token | Detail izin pending |
| POST | `/api/mobile/izin/proses` | Token | Proses persetujuan |
| GET | `/api/mobile/izin/riwayat-persetujuan` | Token | Riwayat persetujuan |

### SIMRS Integration API (`routes/api.php`)

| Method | URL | Auth | Keterangan |
|--------|-----|------|------------|
| GET | `/api/api/v1/ping` | API Key | Health check |
| GET | `/api/api/v1/pegawai` | API Key | Daftar pegawai |
| GET | `/api/api/v1/pegawai/{id}` | API Key | Detail pegawai |
| GET | `/api/api/v1/absensi` | API Key | Data absensi (rentang tanggal) |
| GET | `/api/api/v1/rekap` | API Key | Rekap bulanan |
| GET | `/api/api/v1/izin` | API Key | Data izin |

---

## Fitur Lainnya

### Validasi GPS Geofencing
- Koordinat default: **-8.4991120, 140.4049840** (RSUD Merauke)
- Radius default: **100 meter**
- Deteksi anomali GPS:
  - Akurasi GPS terlalu presisi (< 3 meter)
  - Koordinat identik di beberapa absen
  - Kecepatan tempuh tidak masuk akal (> 150 km/h)

### Sistem Rating Bintang (0-5)
| Keterlambatan (menit) | Bintang |
|----------------------|---------|
| 0 | 5 |
| 5 | 4 |
| 10 | 3 |
| 15 | 2 |
| 30 | 1 |
| > 30 | 0 |

### Workflow Approval Izin (4 Tahap)
1. Koordinator/Kepala Unit
2. Kepala Seksi/Sub Bagian
3. Kepala Bidang/Bagian
4. HRD

Tahap otomatis dilewati jika tidak ada pejabat di posisi tersebut.

### Notifikasi
- **Email**: via SMTP (Laravel Mail)
- **WhatsApp**: via [Fonnte API](https://fonnte.com) untuk notifikasi pengajuan/persetujuan izin

### Verifikasi Publik
Dokumen izin yang telah disetujui mendapatkan `kode_verifikasi` unik yang dapat diverifikasi publik di `/verifikasi/{kode}` tanpa login.

### Backup Database
Generate file SQL dump secara langsung dari PHP tanpa依赖 `mysqldump`.

---

## Pengaturan Sistem

Pengaturan disimpan di tabel `pengaturan` (key-value), dapat diubah melalui halaman admin `/admin/pengaturan`:

| Kunci | Default | Keterangan |
|-------|---------|------------|
| `lokasi_lat` | -8.4991120 | Latitude lokasi absen |
| `lokasi_lng` | 140.4049840 | Longitude lokasi absen |
| `radius_meter` | 100 | Radius geofencing (meter) |
| `toleransi_menit` | 5 | Toleransi keterlambatan (menit) |
| `izinkan_pilih_shift` | 1 | Izinkan pegawai pilih shift |
| `wajib_selfie` | 1 | Wajib selfie saat absen |
| `minggu_libur` | 0 | Minggu hari libur |
| `nama_instansi` | RSUD Merauke | Nama instansi |
| `target_jam_kerja_bulanan` | 160 | Target jam kerja/bulan |

---

## Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Backend | Laravel 13.8 (PHP 8.3) |
| Frontend | Blade Templates + Tailwind CSS 4.3 |
| Build Tool | Vite 8.0 |
| Database | SQLite / MySQL / MariaDB |
| Autentikasi | Custom (Session + Token + API Key) |
| Notifikasi | Email (SMTP) + WhatsApp (Fonnte API) |
| Timezone | Asia/Jayapura (WIT) |

---

## Catatan Migrasi

Proyek ini dimigrasi dari **CodeIgniter 4** ke **Laravel**. Dokumentasi lengkap migrasi ada di [`MIGRASI_LARAVEL.md`](MIGRASI_LARAVEL.md), meliputi:
- Strategi database (reuse database existing)
- 10 tahap migrasi dengan estimasi timeline
- Pemetaan controller CI4 ke Laravel
- Konversi view ke Blade + Tailwind

---

## Lisensi

Private - RSUD Merauke
