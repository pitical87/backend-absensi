<?php

use App\Models\HariLibur;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

const HARI_ID = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

if (! function_exists('tgl_id')) {
    function tgl_id($tanggal, bool $dgnHari = true): string
    {
        $ts = is_numeric($tanggal) ? (int) $tanggal : strtotime((string) $tanggal);
        if (! $ts) return '—';
        $t = date('j', $ts) . ' ' . BULAN_ID[(int) date('n', $ts)] . ' ' . date('Y', $ts);
        return $dgnHari ? HARI_ID[(int) date('w', $ts)] . ', ' . $t : $t;
    }
}

if (! function_exists('jam_id')) {
    function jam_id($waktu): string
    {
        if (! $waktu) return '—';
        $ts = is_numeric($waktu) ? (int) $waktu : strtotime((string) $waktu);
        return $ts ? date('H.i', $ts) : '—';
    }
}

if (! function_exists('jam_singkat')) {
    function jam_singkat(string|DateTimeInterface|null $time): string
    {
        if ($time instanceof DateTimeInterface) return $time->format('H.i');
        if (! $time) return '—';
        return substr(str_replace(':', '.', $time), 0, 5);
    }
}

if (! function_exists('menit_ke_teks')) {
    function menit_ke_teks($menit): string
    {
        $menit = (int) $menit;
        if ($menit <= 0) return '0 menit';
        $j = intdiv($menit, 60);
        $m = $menit % 60;
        $s = [];
        if ($j > 0) $s[] = $j . ' jam';
        if ($m > 0) $s[] = $m . ' menit';
        return implode(' ', $s);
    }
}

if (! function_exists('label_shift')) {
    function label_shift(?object $s): string
    {
        if (! $s || empty($s->kategori)) return 'Belum diatur';
        return $s->kategori . ' (' . jam_singkat($s->jam_masuk) . ' - ' . jam_singkat($s->jam_pulang) . ')';
    }
}

if (! function_exists('hitung_jarak')) {
    function hitung_jarak(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

if (! function_exists('pengaturan')) {
    function pengaturan(string $kunci, mixed $default = null): mixed
    {
        return Pengaturan::ambil($kunci, $default);
    }
}

if (! function_exists('simpan_pengaturan')) {
    function simpan_pengaturan(string $kunci, mixed $nilai): void
    {
        Pengaturan::simpan($kunci, $nilai);
    }
}

if (! function_exists('catat_aktivitas')) {
    function catat_aktivitas(string $aksi, ?string $detail = null): void
    {
        try {
            DB::table('aktivitas_log')->insert([
                'user_id' => session('uid') ?: null,
                'aksi' => $aksi,
                'detail' => $detail,
                'ip' => Request::ip(),
                'waktu' => now(),
            ]);
        } catch (\Throwable $e) {
            // jangan pernah menggagalkan aksi utama karena log
        }
    }
}

if (! function_exists('tempat_tugas')) {
    function tempat_tugas(object $u): string
    {
        $t = $u->unit_nama ?? null;
        if (! $t) return 'Belum diatur';
        return $t . (! empty($u->sub_unit_nama) ? ' — ' . $u->sub_unit_nama : '');
    }
}

if (! function_exists('ikon')) {
    function ikon(string $nama, int $ukuran = 18): string
    {
        $paths = [
            'beranda' => '<path d="M4 11 12 4l8 7"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>',
            'pegawai' => '<circle cx="12" cy="8" r="3.4"/><path d="M5 19.5c.8-3.6 3.6-5.3 7-5.3s6.2 1.7 7 5.3"/>',
            'gedung' => '<path d="M4 20V6l8-3 8 3v14"/><path d="M2 20h20"/><path d="M9 9h.01M15 9h.01M9 13h.01M15 13h.01M11 20v-3h2v3"/>',
            'jam' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'peta' => '<path d="M12 21s-6.5-5.4-6.5-10a6.5 6.5 0 0 1 13 0c0 4.6-6.5 10-6.5 10Z"/><circle cx="12" cy="11" r="2.4"/>',
            'grafik' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
            'atur' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8"/>',
            'cetak' => '<path d="M7 8V3h10v5"/><path d="M5 8h14a2 2 0 0 1 2 2v6h-4"/><path d="M7 16H3v-6a2 2 0 0 1 2-2"/><path d="M7 13h10v8H7z"/>',
            'unduh' => '<path d="M12 4v11"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
            'keluar' => '<path d="M14 4h5v16h-5"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
            'kalender' => '<rect x="4" y="6" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v5M16 3v5"/>',
            'centang' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>',
            'silang' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
            'peringatan' => '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4M12 17.2v.3"/>',
            'masuk' => '<path d="M9 4H4v16h5"/><path d="m13 8 4 4-4 4"/><path d="M7 12h10"/>',
            'pulang' => '<path d="M14 4h5v16h-5"/><path d="m10 8-4 4 4 4"/><path d="M16 12H6"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 11v5"/>',
            'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'surat' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 12h6M9 16h6"/>',
            'kamera' => '<path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.4"/>',
            'perisai' => '<path d="M12 3 5 6v5c0 4.6 3 8.4 7 9.6 4-1.2 7-5 7-9.6V6l-7-3Z"/><path d="m9 12 2 2 4-4.5"/>',
            'log' => '<path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
            'struktur' => '<rect x="9" y="3" width="6" height="4.6" rx="1"/><rect x="3" y="16" width="5.4" height="4.6" rx="1"/><rect x="9.3" y="16" width="5.4" height="4.6" rx="1"/><rect x="15.6" y="16" width="5.4" height="4.6" rx="1"/><path d="M12 7.6V12M12 12H5.7v4M12 12v4M12 12h6.3v4"/>',
            'kunci' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/>',
            'hapus' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="m6 7 1 13h10l1-13"/><path d="M10 11v5M14 11v5"/>',
            'bintang' => '<path d="m12 3.5 2.5 5.2 5.7.8-4.1 4 1 5.7-5.1-2.7-5.1 2.7 1-5.7-4.1-4 5.7-.8L12 3.5Z"/>',
        ];
        $p = $paths[$nama] ?? $paths['info'];
        return '<svg class="ikon" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none"'
             . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
             . ' aria-hidden="true">' . $p . '</svg>';
    }
}

if (! function_exists('badge_status')) {
    function badge_status(?string $status, int $menitTerlambat = 0): string
    {
        return match (true) {
            $status === null || $status === 'Alpa'
                => '<span class="badge badge-merah">Tidak Hadir</span>',
            $status === 'Belum Pulang' => '<span class="badge badge-biru">Belum Pulang</span>',
            $status === 'Terlambat' => '<span class="badge badge-amber">Terlambat '
                                      . $menitTerlambat . ' mnt</span>',
            $status === 'Tepat Waktu' => '<span class="badge badge-hijau">Tepat Waktu</span>',
            $status === 'Libur' => '<span class="badge badge-abu">Libur</span>',
            $status === 'Izin' => '<span class="badge badge-ungu">Izin</span>',
            $status === 'Sakit' => '<span class="badge badge-ungu">Sakit</span>',
            $status === 'Cuti' => '<span class="badge badge-ungu">Cuti</span>',
            $status === 'Dinas Luar' => '<span class="badge badge-teal">Dinas Luar</span>',
            default => '<span class="badge badge-abu">' . e($status) . '</span>',
        };
    }
}

if (! function_exists('badge_izin')) {
    function badge_izin(string $status): string
    {
        return match ($status) {
            'Disetujui' => '<span class="badge badge-hijau">Disetujui</span>',
            'Ditolak' => '<span class="badge badge-merah">Ditolak</span>',
            default => '<span class="badge badge-amber">Menunggu</span>',
        };
    }
}

if (! function_exists('label_jabatan')) {
    function label_jabatan(object $u): string
    {
        if (! empty($u->jabatan_nama)) {
            return $u->jabatan_nama;
        }
        return $u->jabatan_kategori ?? 'Staf/Pelaksana';
    }
}

if (! function_exists('unit_organisasi')) {
    function unit_organisasi(object $u): string
    {
        return $u->jabatan_unit ?? $u->unit_nama ?? '—';
    }
}

if (! function_exists('kategori_jabatan_list')) {
    function kategori_jabatan_list(): array
    {
        return ['Direktur', 'Kepala Bidang', 'Kepala Bagian',
                'Kepala Seksi', 'Kepala Sub Bagian', 'Staf/Pelaksana'];
    }
}

if (! function_exists('posisi_list')) {
    function posisi_list(): array
    {
        return [
            'Staf',
            'Koordinator/Kepala Unit/Ruang/Instalasi',
            'Kepala Seksi/Sub Bagian',
            'Kepala Bidang/Bagian',
            'HRD',
            'Direktur',
        ];
    }
}

if (! function_exists('posisi_index')) {
    function posisi_index(string $posisi): int
    {
        $i = array_search($posisi, posisi_list(), true);
        return $i === false ? 0 : $i;
    }
}

if (! function_exists('jenis_cuti_list')) {
    function jenis_cuti_list(): array
    {
        return ['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan',
                'Cuti Karena Alasan Penting', 'Cuti Besar', 'Cuti di Luar Tanggungan Negara'];
    }
}

if (! function_exists('is_pns')) {
    function is_pns(object $u): bool
    {
        return ($u->status_pegawai ?? 'Non-PNS') === 'PNS';
    }
}

if (! function_exists('label_tahap_izin')) {
    function label_tahap_izin(int $tahap): string
    {
        return match ($tahap) {
            1 => 'Koordinator/Kepala Unit',
            2 => 'Kepala Seksi/Sub Bagian',
            3 => 'Kepala Bidang/Bagian',
            4 => 'HRD',
            default => '—',
        };
    }
}

if (! function_exists('badge_tahap')) {
    function badge_tahap(string $status): string
    {
        return match ($status) {
            'Disetujui' => '<span class="badge badge-hijau">Disetujui</span>',
            'Ditolak' => '<span class="badge badge-merah">Ditolak</span>',
            'Dilewati' => '<span class="badge badge-abu">Dilewati</span>',
            default => '<span class="badge badge-amber">Menunggu</span>',
        };
    }
}

if (! function_exists('hari_kerja_antara')) {
    function hari_kerja_antara(string $mulai, string $selesai, array $liburSet, bool $mingguLibur): int
    {
        $jml = 0;
        $t = strtotime($mulai);
        $ahr = strtotime($selesai);
        while ($t <= $ahr) {
            $tgl = date('Y-m-d', $t);
            $minggu = $mingguLibur && date('N', $t) == 7;
            if (! $minggu && ! isset($liburSet[$tgl])) {
                $jml++;
            }
            $t = strtotime('+1 day', $t);
        }
        return $jml;
    }
}

if (! function_exists('hari_libur_tetap')) {
    function hari_libur_tetap(int $tahun): array
    {
        return [
            sprintf('%d-01-01', $tahun) => 'Tahun Baru Masehi',
            sprintf('%d-05-01', $tahun) => 'Hari Buruh Internasional',
            sprintf('%d-06-01', $tahun) => 'Hari Lahir Pancasila',
            sprintf('%d-08-17', $tahun) => 'Proklamasi Kemerdekaan RI',
            sprintf('%d-12-25', $tahun) => 'Kelahiran Yesus Kristus (Natal)',
        ];
    }
}

if (! function_exists('pastikan_libur_tetap')) {
    function pastikan_libur_tetap(int $tahun): void
    {
        static $sudah = [];
        if (isset($sudah[$tahun])) {
            return;
        }
        $sudah[$tahun] = true;

        try {
            foreach (hari_libur_tetap($tahun) as $tgl => $ket) {
                HariLibur::updateOrCreate(['tanggal' => $tgl], ['keterangan' => $ket]);
            }
        } catch (\Throwable $e) {
            // diam saja
        }
    }
}
