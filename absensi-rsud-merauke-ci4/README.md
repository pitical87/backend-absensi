# Sistem Absensi Pegawai RSUD Merauke — v2 (CodeIgniter 4)

Aplikasi web absensi pegawai berbasis **CodeIgniter 4 + MySQL** dengan **validasi lokasi GPS**,
**foto selfie**, **deteksi anomali GPS**, **manajemen izin/cuti**, dan **API integrasi SIMRS**.
Dirancang untuk server lokal (LAN) rumah sakit menggunakan XAMPP — **tanpa Composer, tanpa
internet saat pemasangan**. Responsif untuk komputer maupun HP.

---

## Fitur

**Pegawai**
- Pendaftaran mandiri lengkap (nama, TTL, jenis kelamin, agama, email, no. HP,
  tempat kerja + sub unit, profesi).
- Dasbor: shift aktif (Pagi/Sore/Malam — berlaku sampai diubah), tombol **ABSEN DATANG /
  PULANG** dengan alur *foto selfie → GPS → kirim*.
- Validasi radius server-side (Haversine); percobaan di luar radius **ditolak dan tercatat**.
- Pesan sesuai ketentuan: *"Terima kasih sudah datang Tepat Waktu"*, *"Anda terlambat datang
  sebanyak XX menit"*, *"Terima kasih atas dedikasi Anda hari ini"*.
- Shift malam lintas hari ditangani otomatis.
- **Pengajuan Izin / Sakit / Cuti / Dinas Luar** dengan lampiran (surat sakit/tugas) dan
  pantauan status persetujuan.
- Rekap bulanan adil: hari libur dan izin yang disetujui **tidak dihitung Alpa**; grafik batang
  harian + tabel rinci.

**Admin**
- Dashboard: statistik hari ini (hadir/terlambat/izin/belum), peringatan pengajuan menunggu
  dan anomali GPS, grafik 30 hari.
- Data Pegawai, Unit Kerja & Sub Unit, Pengaturan Shift (+ penetapan per pegawai).
- **Persetujuan Izin** dengan catatan admin; **Kalender Hari Libur** (+ opsi Minggu libur).
- Data Kehadiran per tanggal: **foto selfie**, **catatan anomali GPS**, tabel **percobaan absen
  ditolak**, peta posisi (Leaflet + fallback offline).
- Rekap Bulanan (hari efektif, hadir, tepat, telat, alpa, izin, sakit, cuti, dinas, %),
  arsip rekap, **Cetak PDF** berkop, **Export Excel** (rekap & detail).
- Pengaturan: titik GPS (tombol lokasi perangkat / klik peta), radius, toleransi,
  wajib selfie, **Backup Database sekali klik**, **Kunci API SIMRS**.
- **Log Aktivitas** (audit trail) semua tindakan penting.

**Struktur Organisasi** *(baru — v2.1)*
- Menu admin **Struktur Organisasi**: bagan hierarki RSUD (Direktur → Kabag/Kabid →
  Kasubag/Kasi) lengkap dengan nama pemegang jabatan; admin dapat menambah/mengubah/
  menghapus jabatan pada struktur.
- Field **NIP**, **Jabatan** (Direktur, Kepala Bidang, Kepala Bagian, Kepala Seksi,
  Kepala Sub Bagian, Staf/Pelaksana) dan **Nama Jabatan** (dropdown menyesuaikan kategori)
  pada pendaftaran & data pegawai; satu jabatan struktural hanya dipegang satu pegawai aktif.
- Dasbor pegawai menampilkan NIP, Jabatan, dan Unit Kerja organisasi
  (cth. Kasi Keperawatan → Bidang Pelayanan) di samping tempat tugas & shift.
- Bagan dapat dilihat seluruh pegawai melalui tombol **Struktur** di dasbor.
- **Laporan berfilter**: rekap, Cetak PDF, dan Export Excel dapat disaring per Jabatan,
  Nama Jabatan, Bidang/Bagian (termasuk seluruh seksi/subbag di bawahnya), Unit Kerja,
  dan Profesi — kolom Jabatan ikut tercetak.

**Posisi & Alur Persetujuan Izin/Cuti** *(baru — v2.2)*
- Field baru **Posisi** pada data pegawai (independen dari Jabatan struktural):
  Staf, Koordinator/Kepala Unit/Ruang/Instalasi, Kepala Seksi/Sub Bagian, Kepala
  Bidang/Bagian, HRD, Direktur. Untuk tiga posisi terakhir yang bersifat struktural,
  posisi ini disamakan dengan field Jabatan yang sudah ada (tidak input dobel);
  untuk Staf/Koordinator disediakan field **Seksi/Sub Bagian Pembina** guna menentukan
  jalur persetujuannya.
- **Izin** dan **Cuti** kini berjalan melalui alur persetujuan berjenjang otomatis
  sesuai posisi pemohon: Koordinator/Kepala Unit → Kepala Seksi/Sub Bagian → Kepala
  Bidang/Bagian → HRD. Pemohon yang posisinya sudah berada di tengah/ujung alur
  (mis. seorang Kabid mengajukan izin) otomatis memulai dari tahap berikutnya;
  tahap tanpa pejabat terdaftar dilewati otomatis agar alur tidak pernah macet.
  Setiap pejabat memutus lewat menu **Persetujuan** pada akun pegawai mereka sendiri
  (bukan menu admin) — admin tetap dapat **mengambil alih** tahap manapun dari
  menu Persetujuan Izin bila pejabat terkait belum terdaftar/berhalangan.
- Formulir pengajuan Izin/Cuti kini memuat **Jenis Cuti** (khusus Cuti), **Alamat
  Selama Izin/Cuti**, dan **lama otomatis dalam hari kerja** (hari Minggu & hari
  libur tidak dihitung). **Cuti hanya dapat diajukan pegawai berstatus PNS**,
  dengan 6 jenis: Cuti Tahunan, Cuti Sakit, Cuti Melahirkan, Cuti Karena Alasan
  Penting, Cuti Besar, dan Cuti di Luar Tanggungan Negara. Izin biasa (non-PNS
  atau PNS) tersedia terpisah dari Sakit/Dinas Luar yang tetap satu-tahap seperti
  sebelumnya.
- **Hak Cuti Tahunan** (12 hari kerja/tahun) ditampilkan ke pegawai PNS di halaman
  Izin/Cuti: hak tahun berjalan, terpakai, dan sisa — dihitung dari Izin & Cuti
  Tahunan yang disetujui penuh pada tahun berjalan. Cuti Sakit/Melahirkan/Alasan
  Penting/Besar/CLTN memiliki ketentuan tersendiri dan **tidak** memotong jatah ini
  (di luar cakupan penghitungan otomatis modul ini).
- Setelah disetujui penuh oleh HRD, sistem menerbitkan **nomor surat** dan **kode
  verifikasi**, lalu menyediakan **dua berkas siap cetak**: Formulir Permohonan
  (dengan riwayat tanda tangan tiap tahap) dan Surat Keterangan resmi RSUD.
  Direktur (atau admin) dapat membubuhkan **tanda tangan elektronik** pada
  dokumen, atau mencetak dan menandatangani secara manual — keduanya sah.
  Keabsahan dokumen dapat diperiksa siapa saja tanpa login di halaman
  **Verifikasi** menggunakan kode pada berkas.

**Keamanan**
- Pembatas percobaan masuk: 5 kegagalan / 15 menit per email atau IP → ditunda sementara.
- CSRF di seluruh form & AJAX, prepared statement (Query Builder), password ter-hash,
  berkas foto/lampiran disimpan di luar folder publik dan disajikan dengan kontrol akses.

---

## Kebutuhan Server

- **XAMPP** dengan **PHP ≥ 8.1** (ekstensi `intl` dan `mysqli` — bawaan XAMPP, pastikan
  `extension=intl` tidak diberi tanda `;` pada `php.ini`) dan **MySQL/MariaDB**.
- Peramban modern di perangkat pegawai (Chrome/Firefox/Edge) dengan GPS & kamera.
- Internet **hanya** untuk peta admin (Leaflet/OpenStreetMap). Absensi berjalan penuh di LAN.

---

## Langkah Pemasangan (XAMPP)

1. Salin folder `absensi-rsud-merauke-ci4` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. (Opsional) Sesuaikan koneksi database pada `app/Config/Database.php` — bawaan sudah cocok
   untuk XAMPP standar (root tanpa password, database `absensi_rsud_merauke`).
4. Buka `http://localhost/absensi-rsud-merauke-ci4/install`
   → database, seluruh tabel, dan data master dibuat otomatis → **buat akun Admin pertama**.
5. Masuk sebagai admin → **Pengaturan** → tetapkan **titik koordinat RSUD** (wajib —
   bawaan hanyalah pusat kota Merauke) dan radius.
6. Ganti `public/assets/img/logo.svg` dengan logo resmi RSUD Merauke.
7. (Disarankan) Isi menu **Hari Libur** dengan tanggal merah tahun berjalan.
8. Pegawai mendaftar sendiri melalui tombol **Daftar** pada halaman masuk.

### Menetapkan Posisi pegawai (langkah wajib setelah upgrade)

Setelah pembaruan ke v2.2, seluruh pegawai lama otomatis berposisi **Staf** (atau
otomatis diselaraskan ke posisi struktural yang sesuai bila mereka sudah memegang
Jabatan Direktur/Kabid/Kabag/Kasi/Kasubag). Agar alur persetujuan izin/cuti berjalan
sampai tuntas, admin perlu melengkapi dari menu **Data Pegawai**:
1. Tetapkan **Posisi** yang benar untuk Koordinator/Kepala Unit, HRD, dan Direktur
   (posisi ini tidak otomatis terisi dari data lama).
2. Untuk pegawai berposisi **Staf**/**Koordinator**, tetapkan **Seksi/Sub Bagian
   Pembina** agar pengajuan mereka tahu harus diteruskan ke Kasi/Kasubag yang mana.
3. Tandai pegawai berstatus **PNS** agar dapat mengajukan Cuti.

Tanpa langkah ini, pengajuan tetap berjalan (tahap tanpa pejabat dilewati otomatis
sampai ke HRD), namun tidak melalui jenjang Koordinator/Kasi/Kabid yang semestinya.

### Memperbarui instalasi lama (upgrade)

Sudah memasang versi sebelumnya? **Data tidak hilang.** Cukup:

1. Timpa seluruh berkas aplikasi dengan versi baru (folder `writable/` boleh ikut
   ditimpa — foto & lampiran ada di dalamnya, jadi jangan dihapus).
2. Buka `http://localhost/absensi-rsud-merauke-ci4/install` **sekali** — sistem otomatis
   menambahkan tabel `jabatan` beserta kolom baru (`nip`, `jabatan_kategori`, `jabatan_id`)
   tanpa menyentuh data absensi/pegawai yang ada. Pegawai lama otomatis berstatus
   Staf/Pelaksana sampai admin menetapkan jabatan strukturalnya.

Akses dari HP dalam LAN: `http://IP-SERVER/absensi-rsud-merauke-ci4`
(mis. `http://192.168.1.10/absensi-rsud-merauke-ci4`). Baca bagian HTTPS di bawah.

> Folder `writable/` harus dapat ditulis oleh Apache (bawaan XAMPP Windows sudah bisa;
> di Linux: `chown -R www-data:www-data writable`).

---

## PENTING: HTTPS agar GPS & Kamera Berfungsi di LAN

Peramban modern **memblokir Geolocation API dan kamera pada halaman non-HTTPS**, kecuali
diakses lewat `http://localhost`. Artinya absensi dari HP pegawai (lewat IP server)
memerlukan **HTTPS** walau hanya di jaringan lokal. Dua cara lazim:

### Cara A — Sertifikat self-signed pada Apache/XAMPP (disarankan)

1. Buat sertifikat (folder `C:\xampp\apache`): jalankan `makecert.bat`, atau dengan OpenSSL:
   ```
   openssl req -x509 -nodes -days 3650 -newkey rsa:2048 ^
     -keyout conf\ssl.key\server.key -out conf\ssl.crt\server.crt ^
     -subj "/CN=IP-SERVER-ANDA"
   ```
2. Pastikan modul SSL aktif pada `conf\httpd.conf`
   (`LoadModule ssl_module modules/mod_ssl.so` dan `Include conf/extra/httpd-ssl.conf`
   tidak diberi tanda `#`).
3. Restart Apache, akses `https://IP-SERVER/absensi-rsud-merauke-ci4`.
4. Peringatan sertifikat pada kunjungan pertama adalah wajar untuk self-signed — pilih
   **Advanced → Proceed**. Alat **mkcert** dapat dipakai agar peringatan hilang.

### Cara B — Pengecualian Chrome (untuk perangkat terkelola / uji coba)

Buka `chrome://flags/#unsafely-treat-insecure-origin-as-secure`, isi `http://IP-SERVER`,
set **Enabled**, relaunch. GPS & kamera diizinkan tanpa HTTPS pada perangkat itu.

> Pengujian di komputer server sendiri cukup `http://localhost/...`.

---

## API Integrasi SIMRS

Seluruh endpoint berada di bawah `/api/v1/` dan dilindungi header **`X-API-KEY`**
(nilai kunci ada di menu admin **Pengaturan**, dapat dibuat ulang kapan saja).
Respons berformat JSON `{ "sukses": true, "jumlah": N, "data": [...] }`.

| Endpoint | Keterangan |
|---|---|
| `GET /api/v1/ping` | Uji koneksi & versi |
| `GET /api/v1/pegawai` | Daftar pegawai + NIP, jabatan, unit, sub unit, profesi, shift |
| `GET /api/v1/absensi?dari=YYYY-MM-DD&sampai=YYYY-MM-DD[&user_id=N]` | Absensi harian (maks 92 hari per permintaan), termasuk koordinat & flag anomali |
| `GET /api/v1/rekap?bulan=N&tahun=NNNN` | Rekap bulanan lengkap per pegawai |
| `GET /api/v1/izin?status=Disetujui&dari=…&sampai=…` | Data izin/sakit/cuti/dinas luar |

Contoh dari server SIMRS:

```bash
curl -H "X-API-KEY: KUNCI_ANDA" \
  "http://IP-SERVER/absensi-rsud-merauke-ci4/api/v1/rekap?bulan=7&tahun=2026"
```

Contoh PHP di sisi SIMRS:

```php
$ch = curl_init('http://IP-SERVER/absensi-rsud-merauke-ci4/api/v1/absensi?dari=2026-07-01&sampai=2026-07-31');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-KEY: KUNCI_ANDA']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$data = json_decode(curl_exec($ch), true);
```

Pola integrasi yang disarankan: SIMRS menarik `rekap` sekali sebulan untuk kebutuhan
remunerasi/kepegawaian, dan `absensi` harian bila perlu data real-time.

---

## Struktur Proyek (ringkas)

```
absensi-rsud-merauke-ci4/
├── .htaccess                  ← meneruskan permintaan ke public/
├── app/
│   ├── Config/                ← App, Database, Routes, Filters, Security
│   ├── Controllers/           ← Auth, Install, Dashboard, Absen, Izin, Foto
│   │   ├── Admin/             ← Dashboard, Pegawai, Unit, Shift, Kehadiran,
│   │   │                        Izin, Libur, Rekap, Pengaturan, Aktivitas
│   │   └── Api/V1.php         ← endpoint SIMRS
│   ├── Database/
│   │   ├── Migrations/        ← skema 14 tabel
│   │   └── Seeds/             ← data master
│   ├── Filters/               ← AuthFilter, AdminFilter, ApiKeyFilter
│   ├── Helpers/absensi_helper.php
│   ├── Libraries/             ← Rekap.php, Anomali.php
│   ├── Models/                ← 14 model
│   └── Views/                 ← layout/, auth/, install/, pegawai/, admin/
├── public/                    ← index.php, assets (css/js/logo)
├── system/                    ← inti CodeIgniter 4 (jangan diubah)
└── writable/uploads/          ← selfie & lampiran izin (di luar akses publik)
```

## Tabel Database

`users`, `jabatan`, `izin_persetujuan`, `unit_kerja`, `sub_unit`, `profesi`, `shift`, `jadwal_shift`, `absensi`
(+ kolom foto & anomali), `log_lokasi` (termasuk percobaan ditolak), `pengajuan_izin`,
`hari_libur`, `login_attempts`, `aktivitas_log`, `rekap_bulanan`, `pengaturan`.

## Catatan Teknis & Batasan Jujur

- Zona waktu bawaan **Asia/Jayapura (WIT)** — ubah pada `app/Config/App.php` bila perlu.
- **Hari efektif** = hari berjalan − libur − izin/sakit/cuti disetujui; Kehadiran % =
  (Hadir + Dinas Luar) / hari efektif. Pegawai yang masuk pada hari libur tetap tercatat hadir.
- **Deteksi anomali GPS bersifat indikatif**, bukan bukti mutlak: aplikasi web tidak dapat
  membaca flag *mock location* Android (hanya aplikasi native yang bisa). Sistem menandai pola
  janggal (koordinat identik berulang, akurasi tidak wajar, perpindahan mustahil) lalu
  menyerahkan penilaian ke admin — absensi tidak otomatis ditolak agar pegawai jujur tidak
  dirugikan.
- Cetak PDF memakai dialog cetak peramban (*Save as PDF*); Export Excel menghasilkan `.xls`
  yang terbuka langsung di Excel/LibreOffice — keduanya tanpa pustaka tambahan.
- Backup dari menu admin bersifat manual sekali-klik; untuk backup otomatis, jadwalkan
  Task Scheduler Windows memanggil `mysqldump` secara berkala.
- Pemasangan aman dijalankan berulang: tabel dan data master tidak digandakan, data tidak
  terhapus.
