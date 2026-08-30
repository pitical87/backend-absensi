@extends('layouts.admin')

@section('content')

@php
$badgeMetode = ['GET' => 'badge-hijau', 'POST' => 'badge-biru', 'PUT' => 'badge-kuning', 'DELETE' => 'badge-merah'];

$grupApi = [
  [
    'id' => 'autentikasi', 'judul' => 'Autentikasi & Akun', 'ikon' => 'kunci',
    'endpoints' => [
      [
        'metode' => 'POST', 'jalur' => '/login', 'akses' => 'Publik',
        'deskripsi' => 'Masuk dengan email & password. Sukses mengembalikan data user beserta titik lokasi RSUD dan menyetel cookie httpOnly auth_token (berlaku 7 hari) yang dibawa otomatis pada semua request berikutnya.',
        'parameter' => [
          ['email', 'body', 'string', true, 'Email akun terdaftar (role selain admin).'],
          ['password', 'body', 'string', true, 'Password akun.'],
        ],
        'status' => '200 sukses · 401 kredensial salah · 403 akun nonaktif · 422 data kosong · 429 diblokir sementara',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "user": {
    "id": 2,
    "nama_lengkap": "Budi Santoso",
    "email": "budi@example.com",
    "role": "pegawai",
    "unit_kerja": { "id": 1, "nama": "Instalasi Rawat Jalan" },
    "sub_unit": null,
    "shift": { "id": 1, "kategori": "Pagi", "jam_masuk": "07:00", "jam_pulang": "14:00" }
  },
  "lokasi": { "lat": -8.499112, "lng": 140.404984, "radius": 100 }
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/register/master', 'akses' => 'Publik',
        'deskripsi' => 'Master data untuk form pendaftaran: unit kerja, sub unit per unit, profesi, pilihan jabatan, kategori jabatan, posisi, dan seksi pembina.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "unit": [{ "id": 1, "nama": "Instalasi Rawat Jalan", "punya_sub": false }],
  "sub": { "2": [{ "id": 5, "nama": "Farmasi" }] },
  "profesi": [{ "id": 1, "nama": "Dokter" }],
  "jabatan": { "...": "pilihan struktur organisasi" },
  "kategori_jabatan": ["..."],
  "posisi": ["Staf"],
  "seksi_pembina": []
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/register', 'akses' => 'Publik',
        'deskripsi' => 'Pendaftaran akun pegawai baru. Akun baru berstatus nonaktif sampai diaktifkan oleh admin.',
        'parameter' => [
          ['nama_lengkap', 'body', 'string', true, 'Nama lengkap.'],
          ['tanggal_lahir', 'body', 'date Y-m-d', false, 'Format YYYY-MM-DD.'],
          ['jenis_kelamin', 'body', 'string', true, 'Laki-Laki / Perempuan.'],
          ['agama', 'body', 'string', true, 'Katolik/Kristen/Islam/Hindu/Budha/Lainnya.'],
          ['email', 'body', 'string', true, 'Harus unik dan valid.'],
          ['no_hp / nip / tempat_lahir', 'body', 'string', false, 'Data tambahan.'],
          ['password · password2', 'body', 'string', true, 'Minimal 6 karakter, keduanya sama.'],
          ['unit_kerja_id', 'body', 'int', true, 'ID unit kerja.'],
          ['sub_unit_id', 'body', 'int', 'Kadang', 'Wajib bila unit punya_sub = true.'],
          ['profesi_id', 'body', 'int', true, 'ID profesi.'],
          ['jabatan_kategori · jabatan_id', 'body', 'mixed', true, 'Resolusi struktur organisasi.'],
          ['status_pegawai', 'body', 'string', false, 'PNS / Non-PNS (bila bukan PNS → Non-PNS).'],
          ['posisi', 'body', 'string', true, 'Posisi pegawai.'],
          ['seksi_pembina_id', 'body', 'int', 'Kadang', 'Untuk posisi Kepala Seksi/Sub Bagian.'],
        ],
        'status' => '201 sukses · 422 validasi gagal (pesan digabung dalam satu string)',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Pendaftaran berhasil. Silakan masuk dengan email dan password Anda."
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/me', 'akses' => 'Token',
        'deskripsi' => 'Profil user yang sedang lengkap dengan relasi unit/sub/profesi/shift/jabatan serta titik lokasi RSUD.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "user": { "...": "data user + relasi" },
  "lokasi": { "lat": -8.499112, "lng": 140.404984, "radius": 100 }
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logout', 'akses' => 'Token',
        'deskripsi' => 'Menghapus token sesi aktif dari database dan menghapus cookie auth_token.',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Berhasil logout" }
JSON,
      ],
    ],
  ],
  [
    'id' => 'absensi', 'judul' => 'Absensi & Kehadiran', 'ikon' => 'jam',
    'endpoints' => [
      [
        'metode' => 'POST', 'jalur' => '/absen', 'akses' => 'Token',
        'deskripsi' => 'Catat absen datang/pulang. Ditolak bila berada di luar radius RSUD; foto selfie base64 wajib bila pengaturan wajib_selfie aktif. Respons memuat blok keterlambatan (menit dibulatkan ke atas). Bintang: masuk lebih awal atau pulang melewati jam jadwal -> 5; tepat waktu -> 4; pelanggaran efektif setelah toleransi 10 menit -> <=5\' 4, <=10\' 3, <=15\' 2, <=30\' 1, >30\' 0. total_bintang = rata-rata bintang masuk & pulang. <strong>Dokter:</strong> Tidak wajib punya shift, boleh absen multi-sesi (masuk→pulang→masuk→pulang per hari). Status, bintang, dan keterlambatan bernilai null untuk dokter.',
        'parameter' => [
          ['tipe', 'body', 'string', true, 'datang atau pulang.'],
          ['lat · lng', 'body', 'float', true, 'Koordinat GPS perangkat.'],
          ['akurasi', 'body', 'float', false, 'Akurasi GPS dalam meter.'],
          ['foto', 'body', 'string base64', 'Kadang', 'Selfie base64 — wajib jika wajib_selfie = 1.'],
        ],
        'status' => '200 (lihat field sukses) · 422 data tidak lengkap',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "jenis": "telat",
  "pesan": "Anda terlambat datang sebanyak 13 menit",
  "keterangan": "Absen datang tercatat pukul 08.12 · jarak 5 m dari titik RSUD.",
  "status": "Terlambat",
  "menit": 13,
  "bintang": 2,
  "keterlambatan": { "menit_telat": 13, "bintang_masuk": 4 },
  "jam": "08.12"
}

Absen pulang — blok keterlambatan berisi sisi pulang + total:
{
  "sukses": true,
  "jenis": "awal",
  "pesan": "Anda pulang lebih awal sebanyak 10 menit",
  "status": "Lebih Awal",
  "menit": 10,
  "keterlambatan": { "menit_pulang_awal": 10, "bintang_pulang": 4, "total_bintang": 4 },
  "jam": "13.50"
}

Dokter — status, bintang, keterlambatan bernilai null; multi-sesi diizinkan:
{
  "sukses": true,
  "jenis": "sukses",
  "pesan": "Terima kasih sudah hadir hari ini",
  "keterangan": "Absen datang tercatat pukul 08.30 · jarak 15 m dari titik RSUD.",
  "status": null,
  "menit": 0,
  "bintang": null,
  "keterlambatan": null,
  "jam": "08.30"
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/status', 'akses' => 'Token',
        'deskripsi' => 'Status absen hari ini (masuk/pulang) termasuk keterlambatan, menit pulang awal, dan bintang (tepat 4 · lebih awal masuk / pulang lewat jam 5). Untuk dokter, field status dan bintang bernilai null. Catatan: untuk user multi-sesi (dokter), endpoint ini mengembalikan 1 record saja (query pertama).',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "absen_masuk": { "waktu": "07:52", "status": "Tepat Waktu", "menit_terlambat": 0, "bintang": 4 },
  "absen_pulang": { "waktu": "14:03", "status": "Tepat Waktu", "menit_awal": 0, "bintang": 4 },
  "bintang_harian": 4
}

Dokter — status dan bintang bernilai null:
{
  "sukses": true,
  "absen_masuk": { "waktu": "08.30", "status": null, "menit_terlambat": 0, "bintang": null },
  "absen_pulang": { "waktu": "16.45", "status": null, "menit_awal": 0, "bintang": null },
  "bintang_harian": null
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/riwayat', 'akses' => 'Token',
        'deskripsi' => 'Riwayat absensi 7 hari terakhir milik user. Untuk dokter, status dan bintang bernilai null.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "riwayat": [{
    "tanggal": "2026-08-23", "hari": "Minggu", "tanggal_label": "23 Aug 2026",
    "jam_masuk": "07:52", "jam_pulang": "14:03",
    "status": "Tepat Waktu", "status_pulang": "Tepat Waktu",
    "menit_terlambat": 0, "menit_awal_pulang": 0,
    "bintang_masuk": 1, "bintang_pulang": 1, "bintang_harian": 2
  }]
}

Dokter — status, bintang bernilai null:
{
  "sukses": true,
  "riwayat": [{
    "tanggal": "2026-08-23", "hari": "Senin", "tanggal_label": "23 Agustus 2026",
    "jam_masuk": "08:30", "jam_pulang": "16:45",
    "status": null, "status_pulang": null,
    "menit_terlambat": 0, "menit_awal_pulang": 0,
    "bintang_masuk": null, "bintang_pulang": null, "bintang_harian": null
  }]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/statistik', 'akses' => 'Token',
        'deskripsi' => 'Ringkasan kehadiran bulan berjalan untuk kartu statistik aplikasi.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "kehadiran": { "persen": 92.5, "hadir": 18, "target": 20 },
  "jam_kerja": { "total_jam": 142.5, "target_jam": 160 },
  "ketepatan": { "tepat_masuk": 95.0, "tepat_pulang": 90.0 },
  "bintang_bulanan": 4.6
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/performa/bulan?bulan=&tahun=', 'akses' => 'Token',
        'deskripsi' => 'Bintang performa bulanan + pesan motivasi. Default bulan lalu; bulan depan pada tahun berjalan digeser ke bulan sekarang.',
        'parameter' => [
          ['bulan', 'query', 'int 1-12', false, 'Default bulan lalu.'],
          ['tahun', 'query', 'int', false, 'Default tahun lalu.'],
        ],
        'respons' => <<<'JSON'
{
  "sukses": true, "bulan": 7, "tahun": 2026, "nama_bulan": "Juli",
  "bintang": 4.8, "pesan": "Kinera sangat baik, pertahankan!"
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/rekap?bulan=&tahun=', 'akses' => 'Token',
        'deskripsi' => 'Rekap absensi pribadi per bulan + tahun: ringkasan kehadiran/keterlambatan/jam kerja/bintang beserta detail harian (termasuk izin, libur, alpa). Untuk dokter: bintang_bulanan bernilai null, per_tanggal memuat jumlah_sesi, status bernilai "Hadir" tanpa keterangan keterlambatan.',
        'parameter' => [
          ['bulan', 'query', 'int 1-12', false, 'Default bulan berjalan.'],
          ['tahun', 'query', 'int', false, 'Default tahun berjalan.'],
        ],
        'status' => '200 sukses · 422 parameter tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "periode": { "bulan": 8, "tahun": 2026, "label": "Agustus 2026", "hari_dalam_bulan": 31, "hari_berjalan": 23, "hari_efektif": 19 },
  "ringkasan": {
    "hadir": 16, "tepat_masuk": 14, "terlambat": 2, "total_menit_telat": 26,
    "tepat_pulang": 15, "pulang_awal": 1, "total_menit_pulang_awal": 12,
    "izin": 0, "sakit": 1, "cuti": 0, "dinas_luar": 1, "libur": 5, "alpa": 0,
    "anomali": 0, "persen_kehadiran": 94.1, "total_jam_kerja": 121.3,
    "bintang_bulanan": 4.2
  },
  "detail": [{
    "tanggal": "2026-08-03", "hari": "Senin", "status": "Tepat Waktu", "keterangan": null,
    "jam_masuk": "07:55", "jam_pulang": "14:02",
    "menit_telat": 0, "menit_pulang_awal": 0, "total_jam_kerja": 6.1,
    "bintang_masuk": 4, "bintang_pulang": 4, "bintang_harian": 4
  }]
}

Dokter — bintang_bulanan null, per_tanggal ada jumlah_sesi:
{
  "sukses": true,
  "is_dokter": true,
  "periode": { "bulan": 8, "tahun": 2026, "label": "Agustus 2026", "hari_dalam_bulan": 31, "hari_berjalan": 23, "hari_efektif": 19 },
  "ringkasan": {
    "hadir": 16, "tepat_masuk": 0, "terlambat": 0, "total_menit_telat": 0,
    "tepat_pulang": 0, "pulang_awal": 0, "total_menit_pulang_awal": 0,
    "izin": 0, "sakit": 0, "cuti": 0, "dinas_luar": 0, "libur": 5, "alpa": 0,
    "anomali": 0, "persen_kehadiran": 100.0, "total_jam_kerja": null,
    "bintang_bulanan": null
  },
  "detail": [{
    "tanggal": "2026-08-03", "hari": "Senin", "status": "Hadir", "keterangan": null,
    "jam_masuk": "08:30", "jam_pulang": "16:45",
    "menit_telat": 0, "menit_pulang_awal": 0, "total_jam_kerja": null,
    "bintang_masuk": null, "bintang_pulang": null, "bintang_harian": null,
    "jumlah_sesi": 2
  }]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/keterlambatan?bulan=&tahun=', 'akses' => 'Token',
        'deskripsi' => 'Rekap keterlambatan pribadi per bulan + tahun: ringkasan menit telat/pulang awal dan rata-rata bintang beserta detail per hari dari catatan absensi.',
        'parameter' => [
          ['bulan', 'query', 'int 1-12', false, 'Default bulan berjalan.'],
          ['tahun', 'query', 'int', false, 'Default tahun berjalan.'],
        ],
        'status' => '200 sukses · 422 parameter tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "periode": { "bulan": 8, "tahun": 2026, "label": "Agustus 2026" },
  "ringkasan": {
    "tercatat": 16, "terlambat": 2, "total_menit_telat": 26,
    "rata_menit_telat": 13.0, "terlama_menit_telat": 15,
    "pulang_awal": 1, "total_menit_pulang_awal": 12,
    "rata_bintang_masuk": 4.1, "rata_bintang_pulang": 4.2, "rata_bintang_total": 4.2
  },
  "detail": [{
    "tanggal": "2026-08-10", "hari": "Senin", "jam_masuk": "08:12", "jam_pulang": "13:50",
    "menit_telat": 13,     "bintang_masuk": 4, "menit_pulang_awal": 10,
    "bintang_pulang": 4, "total_bintang": 4
  }]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/pegawai-teladan?bulan=&tahun=', 'akses' => 'Token',
        'deskripsi' => 'Peringkat pegawai teladan bulan tertentu berdasarkan akumulasi total bintang absensi (maksimal 10 teratas).',
        'parameter' => [
          ['bulan', 'query', 'int 1-12', false, 'Default bulan berjalan.'],
          ['tahun', 'query', 'int', false, 'Default tahun berjalan.'],
        ],
        'status' => '200 sukses · 422 parameter tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "periode": { "bulan": 8, "tahun": 2026, "label": "Agustus 2026" },
  "daftar": [{
    "peringkat": 1, "pegawai_id": 12, "nama": "Budi Santoso", "unit": "Keperawatan",
    "hari_tercatat": 19, "total_bintang": 92.0, "rata_bintang": 4.84,
    "jumlah_telat": 0, "hari_bintang_lima": 7
  }]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/jadwal', 'akses' => 'Token',
        'deskripsi' => 'Shift efektif hari ini (dari jadwal shift bila ada, fallback shift profil) dan flag boleh memilih shift sendiri.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "shift": { "id": 2, "kategori": "Siang", "jam_masuk": "14:00", "jam_pulang": "21:00" },
  "izinkan_pilih": true
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/jadwal/hari-ini', 'akses' => 'Token',
        'deskripsi' => 'Jadwal shift hari ini dari jadwal shift yang dipasangkan (tanpa fallback profil).',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "data": [{ "tanggal": "2026-08-23", "hari": "Minggu", "shift": { "id": 11, "kategori": "Pagi", "jam_masuk": "08:00", "jam_pulang": "14:00" } }]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/jadwal/mingguan?mulai=', 'akses' => 'Token',
        'deskripsi' => 'Jadwal 7 hari. Default mulai Senin minggu berjalan; isi mulai=YYYY-MM-DD untuk minggu lain. Hari tanpa jadwal bernilai null.',
        'parameter' => [
          ['mulai', 'query', 'YYYY-MM-DD', false, 'Default Senin minggu ini.'],
        ],
        'status' => '200 sukses · 422 format tanggal tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "periode": { "mulai": "2026-08-17", "sampai": "2026-08-23" },
  "data": [
    { "tanggal": "2026-08-17", "hari": "Senin", "shift": { "id": 3, "kategori": "Pagi", "jam_masuk": "05:00", "jam_pulang": "12:00" } },
    { "tanggal": "2026-08-18", "hari": "Selasa", "shift": null }
  ]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/jadwal/bulanan?bulan=&tahun=', 'akses' => 'Token',
        'deskripsi' => 'Jadwal seluruh hari dalam satu bulan + tahun.',
        'parameter' => [
          ['bulan', 'query', 'int 1-12', false, 'Default bulan berjalan.'],
          ['tahun', 'query', 'int', false, 'Default tahun berjalan.'],
        ],
        'status' => '200 sukses · 422 parameter tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "periode": { "bulan": 8, "tahun": 2026, "label": "Agustus 2026" },
  "data": [{ "tanggal": "2026-08-01", "hari": "Sabtu", "shift": { "id": 12, "kategori": "Sore", "jam_masuk": "14:00", "jam_pulang": "20:00" } }]
}
JSON,
      ],
    ],
  ],
  [
    'id' => 'izin', 'judul' => 'Izin / Cuti', 'ikon' => 'surat',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/izin', 'akses' => 'Token',
        'deskripsi' => 'Riwayat pengajuan izin/cuti milik user (maks 50 terakhir) beserta jejak persetujuan tiap tahap.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "izin": [{
    "id": 12, "jenis": "Cuti", "jenis_cuti": "Cuti Tahunan",
    "tanggal_mulai": "2026-08-10", "tanggal_selesai": "2026-08-12",
    "lama_hari": 3, "keterangan": "Acara keluarga", "alamat_izin": "…",
    "lampiran": null, "status": "Menunggu", "tahap_aktif": 1,
    "nomor_surat": null, "ttd_digital": null, "created_at": "2026-08-05T02:11:00.000000Z",
    "persetujuan": [{ "tahap": 1, "posisi_tahap": "Kepala Unit", "status": "Menunggu", "oleh_nama": null, "waktu": null }]
  }]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/izin', 'akses' => 'Token',
        'deskripsi' => 'Ajukan Izin/Sakit/Cuti/Dinas Luar. Izin & Cuti berjenjang (mengikuti alur persetujuan), Sakit & Dinas Luar langsung ke admin. Cuti hanya PNS, memotong kuota cuti tahunan bila jenisnya Cuti Tahunan. Rentang maksimal 60 hari. Respons memuat keterangan pengajuan ke atasan langsung (dari menu Atasan Langsung).',
        'parameter' => [
          ['jenis_pengajuan', 'body', 'string', true, 'Izin / Sakit / Cuti / Dinas Luar.'],
          ['jenis_cuti', 'body', 'string', 'Kadang', 'Wajib untuk jenis Cuti.'],
          ['tanggal_mulai', 'body', 'date Y-m-d', true, 'Hari mulai.'],
          ['tanggal_selesai', 'body', 'date Y-m-d', false, 'Default sama dengan tanggal mulai.'],
          ['alamat', 'body', 'string', 'Kadang', 'Wajib untuk Izin dan Cuti.'],
          ['alasan', 'body', 'string', true, 'Keperluan/keterangan.'],
          ['lampiran', 'file', 'jpg/png/pdf', false, 'Maksimal 3 MB (multipart/form-data).'],
        ],
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Pengajuan Cuti terkirim dan menunggu persetujuan Kepala Unit. Pengajuan telah diajukan ke atasan langsung Anda: Firmansyah Diana.",
  "izin_id": 12,
  "atasan_langsung": ["Firmansyah Diana"],
  "keterangan_atasan": "Pengajuan telah diajukan ke atasan langsung Anda: Firmansyah Diana."
}
JSON,
      ],
      [
        'metode' => 'DELETE', 'jalur' => '/izin/{id}', 'akses' => 'Token',
        'deskripsi' => 'Batalkan pengajuan milik sendiri yang masih berstatus Menunggu.',
        'status' => '200 sukses · 404 tidak ditemukan/sudah diproses',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Pengajuan izin berhasil dibatalkan." }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/izin/today', 'akses' => 'Token',
        'deskripsi' => 'Cek apakah user punya izin berstatus Disetujui yang berlaku hari ini.',
        'respons' => <<<'JSON'
{ "sukses": true, "hasLeave": false, "izin": null }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/izin/total', 'akses' => 'Token',
        'deskripsi' => 'Jumlah pengajuan Menunggu yang berwenang diputus user (untuk badge notifikasi).',
        'respons' => <<<'JSON'
{ "sukses": true, "total": 3 }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/izin/detail', 'akses' => 'Token',
        'deskripsi' => 'Daftar pengajuan Menunggu yang bisa diputus user sesuai alur persetujuan (posisi/jabatan/seksi/unit).',
        'respons' => <<<'JSON'
{ "sukses": true, "izin": [ { "id": 15, "user": { "...": "pemohon" }, "tahap_aktif": 1 } ] }
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/izin/proses', 'akses' => 'Token',
        'deskripsi' => 'Putuskan pengajuan pada tahap aktif. Bila semua tahap selesai status jadi Disetujui (nomor surat + kode verifikasi dibuat otomatis).',
        'parameter' => [
          ['id', 'body', 'int', true, 'ID pengajuan.'],
          ['putusan', 'body', 'string', true, 'setuju atau tolak.'],
          ['catatan', 'body', 'string', false, 'Catatan putusan.'],
        ],
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Persetujuan tercatat, pengajuan diteruskan ke tahap berikutnya.", "hasil": "Menunggu Tahap Berikutnya" }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/izin/riwayat-persetujuan', 'akses' => 'Token',
        'deskripsi' => '30 riwayat putusan persetujuan yang pernah dibuat user.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "riwayat": [{
    "id": 9, "waktu": "2026-08-06T03:20:00.000000Z", "catatan": "OK", "status": "Disetujui",
    "pengajuan": { "id": 15, "jenis": "Cuti", "jenis_cuti": "Cuti Tahunan",
                   "tanggal_mulai": "2026-08-10", "tanggal_selesai": "2026-08-12",
                   "nama_pemohon": "Budi Santoso" }
  }]
}
JSON,
      ],
    ],
  ],
  [
    'id' => 'perubahan-jadwal', 'judul' => 'Perubahan Jadwal Shift', 'ikon' => 'kalender',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/perubahan-jadwal', 'akses' => 'Token',
        'deskripsi' => 'Jadwal shift 30 hari mendatang milik user beserta kelayakan pengajuan (batas waktu, status absensi, pengajuan aktif) dan 30 riwayat pengajuan sendiri.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "batas_jam": 1,
  "jadwal": [{
    "tanggal": "2026-08-27", "hari": "Kamis",
    "shift": { "id": 2, "kategori": "Pagi", "jam_masuk": "07.00", "jam_pulang": "14.00" },
    "bisa_ajukan": true, "alasan_blok": null,
    "batas_waktu": "2026-08-27T04:00:00.000000Z",
    "pengajuan_aktif": null
  }],
  "riwayat": [{ "id": 4, "tanggal": "2026-08-27", "shift_lama": "Pagi", "shift_baru": "Sore",
                "status": "Menunggu", "catatan_keputusan": null }]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/perubahan-jadwal', 'akses' => 'Token',
        'deskripsi' => 'Ajukan perubahan jadwal shift pada tanggal tertentu (harus punya jadwal, maksimal 30 hari ke depan). Ditolak otomatis bila melewati batas waktu (jam mulai shift lama dikurangi batas jam dari Pengaturan), sudah absen pada tanggal itu, atau ada pengajuan Menunggu/Disetujui untuk tanggal sama. Notifikasi dikirim ke atasan langsung.',
        'parameter' => [
          ['tanggal', 'body', 'date Y-m-d', true, 'Tanggal jadwal yang ingin diubah.'],
          ['shift_baru_id', 'body', 'int', true, 'ID shift tujuan (aktif, berbeda dari jadwal saat ini).'],
          ['alasan', 'body', 'string', true, 'Alasan pengajuan (maks 500 karakter).'],
        ],
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Pengajuan perubahan jadwal terkirim dan menunggu persetujuan atasan langsung.",
  "pengajuan_jadwal_id": 7
}
JSON,
      ],
      [
        'metode' => 'DELETE', 'jalur' => '/perubahan-jadwal/{id}', 'akses' => 'Token',
        'deskripsi' => 'Batalkan pengajuan ubah jadwal milik sendiri yang masih berstatus Menunggu.',
        'status' => '200 sukses · 404 tidak ditemukan/sudah diproses',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Pengajuan perubahan jadwal berhasil dibatalkan." }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/perubahan-jadwal/total', 'akses' => 'Token',
        'deskripsi' => 'Jumlah pengajuan ubah jadwal Menunggu yang berwenang diputus user selaku atasan langsung (untuk badge notifikasi).',
        'respons' => <<<'JSON'
{ "sukses": true, "total": 2 }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/perubahan-jadwal/menunggu', 'akses' => 'Token',
        'deskripsi' => 'Daftar pengajuan Menunggu dari bawahan langsung user.',
        'respons' => <<<'JSON'
{
  "sukses": true, "total": 1,
  "data": [{
    "id": 7,
    "pemohon": { "id": 12, "nama": "Firman", "unit": "Instalasi Gawat Darurat", "sub_unit": null },
    "tanggal": "2026-08-27",
    "shift_lama": "Pagi",
    "shift_baru": { "id": 3, "kategori": "Sore", "jam_masuk": "13.00", "jam_pulang": "20.00" },
    "alasan": "Ada keperluan keluarga di siang hari",
    "diajukan_pada": "2026-08-25T02:00:00.000000Z"
  }]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/perubahan-jadwal/proses', 'akses' => 'Token',
        'deskripsi' => 'Putuskan pengajuan sebagai atasan langsung. Setuju → jadwal_shift pegawai pada tanggal terkait langsung diganti ke shift baru; pemohon menerima notifikasi hasil.',
        'parameter' => [
          ['id', 'body', 'int', true, 'ID pengajuan ubah jadwal.'],
          ['putusan', 'body', 'string', true, 'setuju atau tolak.'],
          ['catatan', 'body', 'string', false, 'Catatan keputusan.'],
        ],
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Pengajuan disetujui — jadwal pemohon telah diganti.", "status": "Disetujui" }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/perubahan-jadwal/riwayat-persetujuan', 'akses' => 'Token',
        'deskripsi' => '30 riwayat putusan ubah jadwal yang pernah dibuat user sebagai atasan langsung.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "riwayat": [{ "id": 7, "waktu": "2026-08-25T03:10:00.000000Z", "status": "Disetujui",
                "pemohon": "Firman", "tanggal": "2026-08-27", "shift_lama": "Pagi", "shift_baru": "Sore" }]
}
JSON,
      ],
    ],
  ],
  [
    'id' => 'logbook', 'judul' => 'Logbook SIMRS', 'ikon' => 'log',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/logbook/simrs?dari=&sampai=', 'akses' => 'Token',
        'deskripsi' => 'Ambil data tindakan (rawat jalan + rawat inap) dan pemeriksaan lab dari SIMRS berdasarkan mapping akun user yang sedang login. Hasil gabungan sudah terurut tanggal lalu jam dan siap diisi ke form logbook.',
        'parameter' => [
          ['dari', 'query', 'date Y-m-d', true, 'Tanggal awal rentang.'],
          ['sampai', 'query', 'date Y-m-d', true, 'Tanggal akhir (≥ dari).'],
          ['jenis', 'query', 'string', false, 'Filter opsional: tindakan atau lab. Tanpa parameter = keduanya.'],
        ],
        'status' => '200 (field sukses menunjukkan hasil query) · 422 parameter tidak valid · sukses=false bila belum mapping / SIMRS tak terjangkau',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": null,
  "peringatan": [],
  "jenis": "gabungan",
  "total_tindakan": 2,
  "total_lab": 1,
  "data": [
    { "tanggal": "2026-08-20", "jam": "08:15", "isi": "Melakukan tindakan Cuci Darah", "pesan": "Pada jam 08:15 melakukan tindakan Cuci Darah" },
    { "tanggal": "2026-08-20", "jam": "10:30", "isi": "Melakukan Pemeriksaan lab Hematologi - Periksa Darah Lengkap ", "pesan": "Pada jam 10:30 melakukan Pemeriksaan lab Hematologi -  Periksa Darah Lengkap " },
    { "tanggal": "2026-08-21", "jam": "13:00", "isi": "Melakukan tindakan Injeksi", "pesan": "Pada jam 13:00 melakukan tindakan Injeksi" }
  ]
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/logbook/simrs/{jenis}?dari=&sampai=', 'akses' => 'Token',
        'deskripsi' => 'Sama dengan endpoint gabungan namun jenis ditentukan lewat path. Nilai {jenis}: tindakan atau lab.',
        'parameter' => [
          ['{jenis}', 'path', 'string', true, 'tindakan → hanya data tindakan; lab → hanya pemeriksaan lab.'],
          ['dari · sampai', 'query', 'date Y-m-d', true, 'Rentang tanggal.'],
        ],
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": null,
  "peringatan": [],
  "jenis": "lab",
  "total_tindakan": 0,
  "total_lab": 1,
  "data": [
    { "tanggal": "2026-08-20", "jam": "10:30", "isi": "Melakukan Pemeriksaan lab Hematologi - Periksa Darah Lengkap ", "pesan": "…" }
  ]
}
JSON,
      ],
    ],
  ],
  [
    'id' => 'logbook-saya', 'judul' => 'Logbook Saya', 'ikon' => 'log',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/logbook?q=&bulan=&tahun=&hal=', 'akses' => 'Token',
        'deskripsi' => 'Daftar entri logbook milik sendiri (terbaru dulu), dengan pencarian teks, filter bulan/tahun, dan paginasi 20 entri per halaman. Dipakai untuk menampilkan daftar sebelum edit/hapus.',
        'parameter' => [
          ['q', 'query', 'string', false, 'Kata kunci pencarian pada isi aktivitas.'],
          ['bulan', 'query', 'integer 1-12', false, 'Filter bulan tanggal entri.'],
          ['tahun', 'query', 'integer', false, 'Filter tahun tanggal entri.'],
          ['hal', 'query', 'integer', false, 'Nomor halaman (default 1).'],
        ],
        'status' => '200 · 422 parameter tidak valid',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "total": 2,
  "halaman": 1,
  "per": 20,
  "totalHal": 1,
  "data": [
    { "id": 837, "tanggal": "2026-08-20", "jam": "09:30", "isi": "Melakukan tindakan Cuci Darah", "is_verified": true, "verified_at": "2026-08-22 10:00" },
    { "id": 838, "tanggal": "2026-08-19", "jam": "13:00", "isi": "Melakukan tindakan Injeksi", "is_verified": false, "verified_at": null }
  ]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logbook/simpan', 'akses' => 'Token',
        'deskripsi' => 'Simpan satu entri logbook baru. Entri baru berstatus belum terverifikasi.',
        'parameter' => [
          ['tanggal', 'body JSON', 'date Y-m-d', true, 'Tanggal kegiatan.'],
          ['jam', 'body JSON', 'time HH:MM', true, 'Jam kegiatan.'],
          ['isi', 'body JSON', 'string ≤1000', true, 'Uraian aktivitas.'],
        ],
        'status' => '201 tersimpan · 422 validasi gagal',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "1 entri logbook tersimpan.", "id": 837 }
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logbook/simpan-bulk', 'akses' => 'Token',
        'deskripsi' => 'Simpan banyak entri logbook sekaligus dalam satu request (maksimal 100 entri). Semua atau tidak sama sekali: bila satu entri gagal validasi, tidak ada yang tersimpan.',
        'parameter' => [
          ['entri', 'body JSON', 'array objek', true, 'Array entri, tiap item berisi { tanggal, jam, isi }.'],
          ['entri.*.tanggal', 'body JSON', 'date Y-m-d', true, 'Tanggal kegiatan.'],
          ['entri.*.jam', 'body JSON', 'time HH:MM', true, 'Jam kegiatan.'],
          ['entri.*.isi', 'body JSON', 'string ≤1000', true, 'Uraian aktivitas.'],
        ],
        'status' => '201 tersimpan · 422 validasi gagal',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "2 entri logbook tersimpan.",
  "total": 2
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logbook/ubah', 'akses' => 'Token',
        'deskripsi' => 'Mengubah isi entri logbook sendiri. Hanya entri yang belum diverifikasi yang boleh diubah; entri milik orang lain akan ditolak.',
        'parameter' => [
          ['id', 'body JSON', 'integer', true, 'ID entri logbook.'],
          ['tanggal', 'body JSON', 'date Y-m-d', true, 'Tanggal kegiatan.'],
          ['jam', 'body JSON', 'time HH:MM', true, 'Jam kegiatan.'],
          ['isi', 'body JSON', 'string ≤1000', true, 'Uraian aktivitas.'],
        ],
        'status' => '200 diperbarui · 404 entri tidak ada / bukan milik Anda / sudah diverifikasi · 422 validasi',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Entri logbook diperbarui." }
JSON,
      ],
      [
        'metode' => 'DELETE', 'jalur' => '/logbook/{id}', 'akses' => 'Token',
        'deskripsi' => 'Menghapus entri logbook sendiri. Hanya entri belum terverifikasi yang boleh dihapus.',
        'parameter' => [
          ['{id}', 'path', 'integer', true, 'ID entri logbook yang dihapus.'],
        ],
        'status' => '200 terhapus · 404 entri tidak ada / bukan milik Anda / sudah diverifikasi',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "1 entri logbook dihapus." }
JSON,
      ],
    ],
  ],
  [
    'id' => 'template-logbook', 'judul' => 'Template Logbook', 'ikon' => 'surat',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/logbook/template', 'akses' => 'Token',
        'deskripsi' => 'Daftar template yang bisa dipakai user: template milik sendiri (type=user) dan template umum dari admin (type=all). milik_sendiri menandai boleh ubah/hapus.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "jumlah": 2,
  "data": [
    { "id": 7, "isi": "Melayani pemeriksaan pasien rawat jalan", "type": "all", "milik_sendiri": false, "dibuat": "2026-08-20 09:00" },
    { "id": 5, "isi": "Tindakan kateterisasi pada pasien kamar 3", "type": "user", "milik_sendiri": true, "dibuat": "2026-08-22 14:30" }
  ]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logbook/template', 'akses' => 'Token',
        'deskripsi' => 'Menambah template pribadi untuk mengisi logbook lebih cepat. Template selalu bertipe user dan hanya tampil untuk pemiliknya.',
        'parameter' => [
          ['isi', 'body JSON', 'string ≤1000', true, 'Teks template.'],
        ],
        'status' => '201 tersimpan · 422 validasi gagal',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Template logbook disimpan.", "id": 5 }
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/logbook/template/ubah', 'akses' => 'Token',
        'deskripsi' => 'Mengubah isi template pribadi milik sendiri.',
        'parameter' => [
          ['id', 'body JSON', 'integer', true, 'ID template.'],
          ['isi', 'body JSON', 'string ≤1000', true, 'Teks template baru.'],
        ],
        'status' => '200 diperbarui · 404 template tidak ada / bukan milik Anda · 422 validasi',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Template logbook diperbarui." }
JSON,
      ],
      [
        'metode' => 'DELETE', 'jalur' => '/logbook/template/{id}', 'akses' => 'Token',
        'deskripsi' => 'Menghapus template pribadi milik sendiri.',
        'parameter' => [
          ['{id}', 'path', 'integer', true, 'ID template yang dihapus.'],
        ],
        'status' => '200 terhapus · 404 template tidak ada / bukan milik Anda',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Template logbook dihapus." }
JSON,
      ],
    ],
  ],
  [
    'id' => 'lembur', 'judul' => 'Pengajuan & Absen Lembur', 'ikon' => 'jam',
    'endpoints' => [
      [
        'metode' => 'GET', 'jalur' => '/lembur', 'akses' => 'Token',
        'deskripsi' => 'Daftar pengajuan lembur milik user: 30 riwayat terbaru (termasuk status & data absen) plus daftar pengajuan berstatus Disetujui untuk keperluan absen masuk/pulang lembur. Menyertakan nilai pengaturan batas pengajuan, maksimal jam/hari, dan rentang hari pengajuan.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "batas_jam": 2,
  "maks_jam": 4,
  "hari_ke_depan": 7,
  "riwayat": [{
    "id": 5, "tanggal": "2026-08-28", "hari": "Jumat",
    "jam_mulai": "17:00", "jam_selesai": "20:00", "durasi_jam": 3.0,
    "keterangan": "Penyelesaian SK laporan bulanan",
    "status": "Disetujui", "catatan_keputusan": "OK",
    "created_at": "2026-08-28T08:00:00.000000Z",
    "absen": { "waktu_masuk": "2026-08-28T09:00:00.000000Z", "waktu_pulang": null,
               "status_masuk": "Tepat Waktu", "durasi_menit": null, "bintang": 4.0 }
  }],
  "disetujui": []
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/lembur', 'akses' => 'Token',
        'deskripsi' => 'Ajukan lembur untuk tanggal tertentu (hari ini s.d. 7 hari ke depan) dengan rentang jam mulai–selesai. Ditolak bila jam mulai ≥ selesai, durasi melebihi maks lembur/hari, bertabrakan dengan jadwal shift, atau ada pengajuan Menunggu/Disetujui yang overlap pada tanggal sama. Notifikasi dikirim ke atasan langsung.',
        'parameter' => [
          ['tanggal', 'body', 'date Y-m-d', true, 'Tanggal lembur (maks 7 hari ke depan).'],
          ['jam_mulai', 'body', 'time H:i', true, 'Jam mulai lembur (harus lebih awal dari jam_selesai).'],
          ['jam_selesai', 'body', 'time H:i', true, 'Jam selesai lembur.'],
          ['keterangan', 'body', 'string', true, 'Alasan/keperluan lembur (maks 1000 karakter).'],
        ],
        'status' => '201 tersimpan · 422 validasi/kelayakan gagal',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Pengajuan lembur terkirim dan menunggu persetujuan atasan langsung.",
  "pengajuan_lembur_id": 5
}
JSON,
      ],
      [
        'metode' => 'DELETE', 'jalur' => '/lembur/{id}', 'akses' => 'Token',
        'deskripsi' => 'Batalkan pengajuan lembur milik sendiri yang masih berstatus Menunggu.',
        'status' => '200 sukses · 404 tidak ditemukan/sudah diproses',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Pengajuan lembur berhasil dibatalkan." }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/lembur/total', 'akses' => 'Token',
        'deskripsi' => 'Jumlah pengajuan lembur Menunggu yang berwenang diputus user selaku atasan langsung (untuk badge notifikasi).',
        'respons' => <<<'JSON'
{ "sukses": true, "total": 2 }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/lembur/menunggu', 'akses' => 'Token',
        'deskripsi' => 'Daftar pengajuan lembur Menunggu dari bawahan langsung user.',
        'respons' => <<<'JSON'
{
  "sukses": true, "total": 1,
  "data": [{
    "id": 5,
    "pemohon": { "id": 12, "nama": "Firman", "unit": "Instalasi Gawat Darurat", "sub_unit": null },
    "tanggal": "2026-08-28",
    "jam_mulai": "17:00", "jam_selesai": "20:00", "durasi_jam": 3.0,
    "keterangan": "Penyelesaian SK laporan bulanan",
    "diajukan_pada": "2026-08-28T08:00:00.000000Z"
  }]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/lembur/proses', 'akses' => 'Token',
        'deskripsi' => 'Putuskan pengajuan lembur sebagai atasan langsung. Hanya pengajuan Menunggu yang bisa diputus; lembur baru dapat diabsensi setelah disetujui. Pemohon menerima notifikasi hasil.',
        'parameter' => [
          ['id', 'body', 'int', true, 'ID pengajuan lembur.'],
          ['putusan', 'body', 'string', true, 'setuju atau tolak.'],
          ['catatan', 'body', 'string', false, 'Catatan keputusan.'],
        ],
        'status' => '200 sukses · 403 bukan atasan / milik sendiri · 404 tidak ada/sudah diproses · 422 putusan tidak valid',
        'respons' => <<<'JSON'
{ "sukses": true, "pesan": "Pengajuan lembur disetujui.", "status": "Disetujui" }
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/lembur/riwayat-persetujuan', 'akses' => 'Token',
        'deskripsi' => '30 riwayat putusan lembur yang pernah dibuat user sebagai atasan langsung.',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "riwayat": [{ "id": 5, "waktu": "2026-08-28T09:00:00.000000Z", "status": "Disetujui",
                "pemohon": "Firman", "tanggal": "2026-08-28",
                "jam_mulai": "17:00", "jam_selesai": "20:00" }]
}
JSON,
      ],
      [
        'metode' => 'POST', 'jalur' => '/absen-lembur', 'akses' => 'Token',
        'deskripsi' => 'Absen masuk lembur untuk tanggal yang memiliki pengajuan disetujui yang belum diabsensi. GPS wajib dalam radius RSUD (radius dari Pengaturan); posisi di luar area ditolak. Bintang masuk dinilai terhadap jam mulai yang disetujui dengan toleransi dari Pengaturan. Foto selfie wajib bila pengaturan wajib_selfie aktif.',
        'parameter' => [
          ['tanggal', 'body', 'date Y-m-d', true, 'Tanggal lembur (default: hari ini).'],
          ['lat', 'body', 'float', true, 'Latitude posisi GPS.'],
          ['lng', 'body', 'float', true, 'Longitude posisi GPS.'],
          ['akurasi', 'body', 'float', false, 'Akurasi GPS dalam meter.'],
          ['foto', 'body', 'data-URL jpeg/png', false, 'Selfie masuk lembur (wajib bila setting selfie aktif).'],
        ],
        'status' => '200 masuk tercatat · 422 di luar area / tidak ada pengajuan layak / validasi',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Absen masuk lembur tercatat tepat waktu.",
  "data": { "absen_lembur_id": 9, "waktu": "17:01", "status": "Tepat Waktu", "menit_terlambat": 0, "bintang": 4 }
}
JSON,
      ],
      [
        'metode' => 'PUT', 'jalur' => '/absen-lembur/pulang', 'akses' => 'Token',
        'deskripsi' => 'Absen pulang lembur untuk record masuk yang masih terbuka pada tanggal terkait. Waktu pulang harus setelah waktu masuk. Durasi aktual (dalam menit) dihitung otomatis dan bintang pulang dinilai terhadap jam selesai yang disetujui.',
        'parameter' => [
          ['tanggal', 'body', 'date Y-m-d', true, 'Tanggal lembur (default: hari ini).'],
          ['lat', 'body', 'float', true, 'Latitude posisi GPS.'],
          ['lng', 'body', 'float', true, 'Longitude posisi GPS.'],
          ['akurasi', 'body', 'float', false, 'Akurasi GPS dalam meter.'],
          ['foto', 'body', 'data-URL jpeg/png', false, 'Selfie pulang lembur (wajib bila setting selfie aktif).'],
        ],
        'status' => '200 pulang tercatat · 422 di luar area / tidak ada record masuk / validasi',
        'respons' => <<<'JSON'
{
  "sukses": true,
  "pesan": "Absen pulang lembur tercatat. Total durasi lembur 3 jam 0 menit.",
  "data": { "durasi_menit": 180, "bintang_pulang": 4, "bintang_harian": 4.0 }
}
JSON,
      ],
      [
        'metode' => 'GET', 'jalur' => '/absen-lembur/status?tanggal=YYYY-MM-DD', 'akses' => 'Token',
        'deskripsi' => 'Status absen lembur user pada tanggal tertentu (kosong bila belum ada).',
        'parameter' => [
          ['tanggal', 'query', 'date Y-m-d', false, 'Tanggal yang ingin dicek (default: hari ini).'],
        ],
        'respons' => <<<'JSON'
{
  "sukses": true,
  "absen_masuk": "17:01", "absen_pulang": "20:02",
  "status_masuk": "Tepat Waktu", "durasi_menit": 181, "bintang": 4.0
}
JSON,
      ],
    ],
  ],
];
@endphp

<div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1 mb-4" id="baris-tab-dok">
  <button type="button" class="dok-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500" data-tab="umum">Umum</button>
  @foreach($grupApi as $g)
    <button type="button" class="dok-tab rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors cursor-pointer text-slate-500" data-tab="{{ $g['id'] }}">{{ $g['judul'] }}</button>
  @endforeach
</div>

<section class="kartu mb-5 dok-panel" id="panel-umum">
  <div class="kartu-kepala">
    <h2>{!! ikon('info') !!} Informasi Umum</h2>
    
  </div>
  <div class="p-4 text-sm flex flex-col gap-2">
    <p><strong>Base URL:</strong> <code class="px-1.5 py-0.5 rounded bg-slate-100 font-semibold">{{ url('api/mobile') }}</code></p>
    <p>Semua respons berformat JSON dengan field <code class="px-1 py-0.5 rounded bg-slate-100">sukses</code> (boolean). Endpoint berlabel <em>Token</em> membutuhkan cookie <code class="px-1 py-0.5 rounded bg-slate-100">auth_token</code> hasil login (httpOnly, berlaku 7 hari).</p>
    <p class="teks-redup">Bila token tidak valid, middleware mengembalikan <strong>401</strong> dengan salah satu pesan:
    <code class="text-xs">{ "sukses": false, "pesan": "Token tidak ditemukan" }</code> ·
    <code class="text-xs">{ "sukses": false, "pesan": "Token tidak valid atau kadaluarsa. Silahkan login kembali" }</code> ·
    <code class="text-xs">{ "sukses": false, "pesan": "Akun tidak aktif" }</code></p>
  </div>
</section>

@foreach($grupApi as $g)
  <section class="kartu mb-5 dok-panel hidden" id="panel-{{ $g['id'] }}">
    <div class="kartu-kepala">
      <h2>{!! ikon($g['ikon']) !!} {{ $g['judul'] }}</h2>
      <span class="badge badge-abu">{{ count($g['endpoints']) }} endpoint</span>
    </div>

    <div class="p-3 flex flex-col gap-3">
      @foreach($g['endpoints'] as $e)
        <article class="rounded-xl border border-slate-200 overflow-hidden bg-white">
          <header class="flex flex-wrap items-center gap-2 px-3 py-2 bg-slate-50 border-b border-slate-200">
            <span class="badge {{ $badgeMetode[$e['metode']] }} font-mono">{{ $e['metode'] }}</span>
            <code class="text-xs font-bold text-navy break-all">{{ $e['jalur'] }}</code>
            <span class="ms-auto teks-redup teks-kecil">{{ $e['akses'] }}</span>
          </header>

          <div class="p-3 text-sm flex flex-col gap-2">
            <p>{{ $e['deskripsi'] }}</p>

            @if(! empty($e['parameter']))
              <div class="overflow-x-auto">
                <table class="tabel teks-kecil">
                  <thead>
                    <tr><th>Nama</th><th>Posisi</th><th>Tipe</th><th class="tengah">Wajib</th><th>Keterangan</th></tr>
                  </thead>
                  <tbody>
                    @foreach($e['parameter'] as $p)
                      <tr>
                        <td><code class="font-semibold">{{ $p[0] }}</code></td>
                        <td>{{ $p[1] }}</td>
                        <td>{{ $p[2] }}</td>
                        <td class="tengah">{!! $p[3] === true ? '<span class="badge badge-merah">Ya</span>' : ($p[3] === false ? '<span class="badge badge-abu">Tidak</span>' : '<span class="badge badge-amber">'.$p[3].'</span>') !!}</td>
                        <td>{{ $p[4] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif

            @if(! empty($e['status']))
              <p class="teks-kecil"><strong>Kode status:</strong> {{ $e['status'] }}</p>
            @endif

            @if(! empty($e['respons']))
              <details>
                <summary class="cursor-pointer select-none inline-block px-3 py-1.5 teks-kecil font-semibold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Contoh Respons</summary>
                <pre class="mt-2 px-3 py-2.5 rounded-lg bg-slate-900 text-slate-100 text-[0.68rem] leading-relaxed overflow-x-auto m-0">{!! $e['respons'] !!}</pre>
              </details>
            @endif
          </div>
        </article>
      @endforeach
    </div>
  </section>
@endforeach

@endsection

@section('script')
<script>
(function () {
  const tombol = document.querySelectorAll('.dok-tab');
  const panel  = document.querySelectorAll('.dok-panel');

  function bukaTab(id) {
    if (! document.getElementById('panel-' + id)) id = 'umum';
    tombol.forEach(function (b) {
      const aktif = b.dataset.tab === id;
      b.classList.toggle('bg-white', aktif);
      b.classList.toggle('text-slate-900', aktif);
      b.classList.toggle('shadow-sm', aktif);
      b.classList.toggle('text-slate-500', !aktif);
    });
    panel.forEach(function (p) { p.classList.toggle('hidden', p.id !== 'panel-' + id); });
    if (window.history.replaceState) history.replaceState(null, '', '#' + id);
  }

  tombol.forEach(function (b) {
    b.addEventListener('click', function () { bukaTab(b.dataset.tab); });
  });

  const awal = (location.hash || '').replace('#', '');
  bukaTab(awal);
})();
</script>
@endsection
