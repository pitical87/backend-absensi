# Rencana Migrasi: Absensi RSUD Merauke — CodeIgniter 4 → Laravel

## Ringkasan

| Aspek | Pendekatan |
|-------|-----------|
| **Database** | Gunakan database existing (`absensi_rsud_merauke`), Eloquent Model mapping ke tabel yang sudah ada |
| **Frontend** | Blade + Tailwind CSS (migrasi bertahap dari CSS vanilla ke Tailwind) |
| **Auth** | Dilewati dulu, tetap pakai custom session-based seperti existing |
| **Notifikasi** | Email + WhatsApp (Fonnte/WhatsApp API) untuk approval izin |
| **Prioritas** | Kecepatan deliver — fungsional sama, arsitektur Laravel |

---

## Tahapan Pekerjaan

### Tahap 1 — Setup Laravel Foundation

1. Buat project Laravel 11 baru
2. Copy `public/assets/` (css, js, img) → `public/assets/`
3. Copy `writable/uploads/` → `storage/app/public/` + buat symlink
4. Konfigurasi `.env`:
   - Database: `absensi_rsud_merauke` (MySQL)
   - `APP_URL`
   - `APP_TIMEZONE=Asia/Jayapura`
5. Install & konfigurasi Tailwind CSS via `npm` + Vite
6. Buat migration Laravel yang mencerminkan struktur tabel existing (data tetap aman)
7. Export seeder data jika diperlukan

### Tahap 2 — Models & Database Layer

Buat Eloquent Models untuk **15 tabel** beserta relationships:

| Model | Table | Relationships |
|-------|-------|---------------|
| `User` | `users` | belongsTo: unitKerja, subUnit, profesi, shift, jabatan; hasMany: absensi, izin |
| `Absensi` | `absensi` | belongsTo: user, shift; hasMany: logLokasi |
| `Izin` | `pengajuan_izin` | belongsTo: user; hasMany: izinPersetujuan |
| `IzinPersetujuan` | `izin_persetujuan` | belongsTo: pengajuanIzin, user |
| `Shift` | `shift` | hasMany: user, absensi, jadwalShift |
| `UnitKerja` | `unit_kerja` | hasMany: subUnit, user |
| `SubUnit` | `sub_unit` | belongsTo: unitKerja |
| `Profesi` | `profesi` | hasMany: user |
| `Jabatan` | `jabatan` | belongsTo: induk (self); hasMany: anak (self), user |
| `JadwalShift` | `jadwal_shift` | belongsTo: user, shift |
| `HariLibur` | `hari_libur` | — |
| `LogLokasi` | `log_lokasi` | belongsTo: user, absensi |
| `AktivitasLog` | `aktivitas_log` | belongsTo: user |
| `LoginAttempt` | `login_attempts` | — |
| `RekapBulanan` | `rekap_bulanan` | belongsTo: user |
| `Pengaturan` | `pengaturan` | key-value model dengan cached accessor |

### Tahap 3 — Helpers & Custom Libraries → Services

Port **5 library CI4** menjadi service class Laravel:

| CI4 Library | Laravel Service |
|-------------|----------------|
| `Libraries/Struktur.php` | `App\Services\StrukturService` |
| `Libraries/AlurIzin.php` | `App\Services\AlurIzinService` |
| `Libraries/Rekap.php` | `App\Services\RekapService` |
| `Libraries/CutiLib.php` | `App\Services\CutiService` |
| `Libraries/Anomali.php` | `App\Services\AnomaliService` |

Port **25+ fungsi helper** dari `absensi_helper.php`:
- Fungsi formatting tanggal Bahasa Indonesia → Helper atau `Carbon` macro
- Fungsi badge/label → Blade Component
- Inline SVG icons → Blade Component `@svg('nama', 18)`
- Fungsi `pengaturan()` → Model accessor + cache
- Fungsi `hitung_jarak()` → Helper
- Fungsi geofencing → AnomaliService

### Tahap 4 — Middleware (Filters → Middleware)

| CI4 Filter | Laravel Middleware |
|------------|-------------------|
| `AuthFilter` | `CheckAuth` — cek session uid, redirect ke /login |
| `AdminFilter` | `CheckAdmin` — cek session role === 'admin' |
| `ApiKeyFilter` | `CheckApiKey` — validasi X-API-KEY header |

### Tahap 5 — Controller Migration

Migrasi **~20 controllers** dengan pola konversi:

CI4                                    Laravel
───                                    ───────
$this->request->getPost('x')           $request->input('x')
$this->db->table('x')->...            DB::table('x')->... / Model::...
view('path', $data)                   view('path', $data)
$this->response->setJSON($d)          response()->json($d)
$this->response->setStatusCode(400)   response()->json([], 400)
session('key')                        session('key')
session()->set('key', 'val')          session(['key' => 'val'])
redirect()->to('/url')                redirect('/url') / redirect()->route('name')
$this->validate($rules)               $request->validate($rules)
service('renderer')                   view() langsung

Urutan migrasi:
1. `Auth` — login, register, logout, brute-force protection
2. `BaseController` → `BaseController` (penggunaAktif, json response trait)
3. `Dashboard` (pegawai)
4. `Absen` — GPS, selfie, shift handling, anomaly check
5. `Izin` — CRUD pengajuan, dokumen, tanda tangan digital
6. `Persetujuan` — workflow approval multi-tier
7. `Struktur` — tree viewer
8. `Verifikasi` — public verification
9. `Foto` — serve uploads
10. `Install` — first-run setup (migration + seed)
11. **Admin module**:
    - `Admin\Dashboard`
    - `Admin\Pegawai` — CRUD user management
    - `Admin\Kehadiran` — daily attendance + map
    - `Admin\Izin` — admin approval + takeover
    - `Admin\Unit` — unit kerja & sub-unit
    - `Admin\Shift` — shift management
    - `Admin\Libur` — holiday calendar
    - `Admin\Pengaturan` — settings, API key, DB backup
    - `Admin\Aktivitas` — activity log
    - `Admin\Struktur` — org structure management
    - `Admin\Rekap` — monthly recap, print, Excel export
12. `Api\V1` — SIMRS integration API

### Tahap 6 — Routes

CI4 routes.php                  Laravel
───                             ───────
routes/get('/', 'Auth::beranda')  Route::get('/', Auth::class, 'beranda')
routes->group('', 'filter')     Route::middleware('auth')->group(...)
routes->group('admin', ...)       Route::prefix('admin')->middleware('admin')->group(...)
routes->group('api/v1', ...)      Route::prefix('api/v1')->middleware('api.key')->group(...)
                                  → routes/web.php + routes/api.php

Daftar route lengkap ada di `app/Config/Routes.php` — total ~50+ endpoint.

### Tahap 7 — Views Migration ke Blade + Tailwind

**Layout:**
| CI4 Layout | Blade Layout |
|------------|-------------|
| `layout/pegawai.php` | `layouts/pegawai.blade.php` |
| `layout/admin.php` | `layouts/admin.blade.php` |
| `layout/otentikasi.php` | `layouts/auth.blade.php` |

**Konversi syntax:**
CI4                                 Blade
───                                 ─────
$this->extend('layout/pegawai')     @extends('layouts.pegawai')
$this->section('isi')               @section('content')
$this->endSection()                 @endsection
<?= esc($var) ?>                    {{ $var }}
<?= csrf_field() ?>                 @csrf
<?= $this->include('partial/...') ?> @include('partials...')

**CSS:**
- CSS vanilla yang sudah ada tetap dipakai di awal
- Bertahap diganti dengan Tailwind utility classes
- CSS variables (`:root`) dikonversi ke Tailwind config

**Components:**
- `@svg('nama', 18)` — Blade component untuk inline SVG icons (25 icon)
- `@badgeStatus($status, $menit)` — attendance status badge
- `@badgeIzin($status)` — leave status badge

**Views yang perlu dimigrasi:**
- `auth/login.blade.php`, `auth/register.blade.php`
- `pegawai/dashboard.blade.php`, `pegawai/izin.blade.php`
- `pegawai/struktur.blade.php`, `pegawai/persetujuan.blade.php`
- `admin/dashboard.blade.php`, `admin/pegawai_index.blade.php`, `admin/pegawai_form.blade.php`
- `admin/kehadiran.blade.php`, `admin/izin.blade.php`
- `admin/unit.blade.php`, `admin/shift.blade.php`, `admin/libur.blade.php`
- `admin/pengaturan.blade.php`, `admin/struktur.blade.php`
- `admin/rekap.blade.php`, `admin/aktivitas.blade.php`
- `admin/laporan_cetak.blade.php`, `admin/excel_rekap.blade.php`, `admin/excel_detail.blade.php`
- `publik/verifikasi.blade.php`
- `install/index.blade.php`
- `partial/pohon.blade.php`

### Tahap 8 — Notification System

**Email:**
- Konfigurasi SMTP di `.env`
- Buat `App\Mail\PengajuanIzinMail`
- Trigger: pengajuan baru → approver, status berubah → pemohon

**WhatsApp:**
- Integrasi dengan [Fonnte](https://fonnte.com) API
- Buat `App\Services\WhatsAppService`
- Kirim notifikasi ke nomor HP approver & pemohon
- Template pesan: pengajuan baru, perlu persetujuan, disetujui/ditolak

**Trigger notification:**
- `IzinController::ajukan()` → notif ke approver tahap 1
- `PersetujuanController::proses()` → notif ke approver next stage / pemohon
- `Admin\IzinController::proses()` → notif ke pemohon

### Tahap 9 — Excel Export & Database Backup

**Rekap Excel:**
- Opsi 1: Gunakan `maatwebsite/laravel-excel` (recommended)
- Opsi 2: Port HTML-table based XLS (seperti existing)

**Cetak/Print:**
- Gunakan `dompdf` atau tetap pakai print CSS seperti existing

**Database Backup:**
- Port PHP-based backup logic dari existing `Admin\Pengaturan::backup()`
- Atau gunakan command `mysqldump` via `Illuminate\Support\Facades\Process`

### Tahap 10 — Testing & Deploy

1. Uji coba semua route dan fitur
2. Pastikan GPS validation & selfie capture berfungsi
3. Pastikan file upload (selfie, lampiran) bisa diakses
4. Uji approval workflow lengkap (4 tahap)
5. Uji API V1 dengan API key
6. Uji export rekap (print & Excel)
7. Deploy ke production server

---

## Arsitektur Laravel

app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── AbsenController.php
│   │   ├── IzinController.php
│   │   ├── PersetujuanController.php
│   │   ├── StrukturController.php
│   │   ├── VerifikasiController.php
│   │   ├── FotoController.php
│   │   ├── InstallController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── PegawaiController.php
│   │       ├── KehadiranController.php
│   │       ├── IzinController.php
│   │       ├── UnitController.php
│   │       ├── ShiftController.php
│   │       ├── LiburController.php
│   │       ├── PengaturanController.php
│   │       ├── AktivitasController.php
│   │       ├── StrukturController.php
│   │       └── RekapController.php
│   ├── Middleware/
│   │   ├── CheckAuth.php
│   │   ├── CheckAdmin.php
│   │   └── CheckApiKey.php
│   └── Requests/
│       └── (FormRequest validation classes)
├── Models/
│   ├── User.php
│   ├── Absensi.php
│   ├── Izin.php
│   ├── IzinPersetujuan.php
│   ├── Shift.php
│   ├── UnitKerja.php
│   ├── SubUnit.php
│   ├── Profesi.php
│   ├── Jabatan.php
│   ├── JadwalShift.php
│   ├── HariLibur.php
│   ├── LogLokasi.php
│   ├── AktivitasLog.php
│   ├── LoginAttempt.php
│   ├── RekapBulanan.php
│   └── Pengaturan.php
├── Services/
│   ├── StrukturService.php
│   ├── AlurIzinService.php
│   ├── RekapService.php
│   ├── CutiService.php
│   ├── AnomaliService.php
│   └── WhatsAppService.php
├── Mail/
│   └── PengajuanIzinMail.php
├── Notifications/
│   └── PengajuanIzinNotification.php
├── View/
│   └── Components/
│       ├── SvgIcon.php
│       ├── BadgeStatus.php
│       └── BadgeIzin.php
└── Helpers/
    └── absensi.php

---

## Perubahan CI4 → Laravel

| CI4 | Laravel |
|-----|---------|
| `system/` + `app/` | `app/` + `vendor/` |
| `writable/uploads/` | `storage/app/public/` |
| `Config/Routes.php` | `routes/web.php` + `routes/api.php` |
| `Config/Database.php` | `.env` + `config/database.php` |
| `Config/Filters.php` | `app/Http/Middleware/` |
| `Filters/` | `Http/Middleware/` |
| `Helpers/` | `Helpers/` (auto-loaded) |
| `Libraries/` | `Services/` |
| `Views/` (.php) | `resources/views/` (.blade.php) |
| CSRF via `csrf_field()` | `@csrf` |
| `$this->validate()` | `$request->validate()` |
| CI4 Model | Eloquent Model |
| Query Builder `$this->db->table()->...` | Eloquent ORM / `DB::table()->...` |
| `session()->set()` | `session()->put()` |
| `$this->response->setJSON()` | `response()->json()` |
| CI4 helpers (form, url, html) | Laravel helpers / Facades |

---

## Catatan Penting

1. **Data existing aman** — tidak ada perubahan struktur database
2. **Frontend JS tetap vanilla** — hanya CSS yang dimigrasi ke Tailwind
3. **Auth ditunda** — custom session auth tetap dipakai sampai ada keputusan
4. **File upload** — selfie/lampiran izin dipindah ke `storage/app/public/` dengan symlink
5. **PHP versi** — Laravel 11 membutuhkan PHP ^8.2, sudah sesuai
6. **Progres bertahap** — setiap controller bisa dimigrasi independen selama masih dalam sesi yang sama

---

## Timeline Estimasi

| Tahap | Estimasi | Deskripsi |
|-------|----------|-----------|
| 1 | 1 sesi | Setup Laravel + Tailwind + assets |
| 2 | 1 sesi | Models + Relationships |
| 3 | 1 sesi | Services + Helpers migration |
| 4 | 1 sesi | Middleware |
| 5 | 2-3 sesi | All controllers |
| 6 | 1 sesi | Routes + verify |
| 7 | 2-3 sesi | Views → Blade + Tailwind |
| 8 | 1 sesi | Notifications (Email + WA) |
| 9 | 1 sesi | Excel export + backup |
| 10 | 1 sesi | Testing + deploy |
| **Total** | **12-14 sesi** | |