<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\RekapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class V1Controller extends Controller
{
    public function __construct()
    {
        $apiKey = pengaturan('api_key', '');
        if ($apiKey === '') {
            abort(503, 'API tidak aktif. Admin belum mengatur kunci API.');
        }
    }

    private function autentikasi(Request $request): bool
    {
        $key = $request->header('X-API-KEY') ?: $request->get('api_key');
        return $key && $key === pengaturan('api_key', '');
    }

    private function cekAuth(Request $request): void
    {
        if (! $this->autentikasi($request)) {
            abort(401, json_encode(['sukses' => false, 'pesan' => 'Kunci API tidak valid.']));
        }
    }

    public function ping(Request $request)
    {
        $this->cekAuth($request);
        return response()->json([
            'sukses'   => true,
            'aplikasi' => 'Sistem Absensi Pegawai RSUD Merauke',
            'versi'    => '2.1',
            'waktu'    => now()->format('c'),
        ]);
    }

    public function pegawai(Request $request)
    {
        $this->cekAuth($request);
        $data = User::select('users.id', 'users.nama_lengkap', 'users.nip', 'users.email', 'users.no_hp',
                     'users.jenis_kelamin', 'users.status', 'users.role',
                     'users.jabatan_kategori', 'j.nama AS jabatan',
                     'uk.nama AS unit_kerja', 'su.nama AS sub_unit', 'p.nama AS profesi',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->leftJoin('jadwal_shift as js', function ($join) { $join->on('users.id', '=', 'js.user_id')->where('js.tanggal_berlaku', now()->toDateString()); })->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'users.jabatan_id')
            ->where('users.role', 'pegawai')
            ->orderBy('users.nama_lengkap')->get()->all();

        return response()->json(['sukses' => true, 'jumlah' => count($data), 'data' => $data]);
    }

    public function getPegawai(Request $request, int $id)
    {
        $this->cekAuth($request);
        $data = User::select('users.id', 'users.nama_lengkap', 'users.nip', 'users.email', 'users.no_hp',
                     'users.jenis_kelamin', 'users.status', 'users.role',
                     'users.jabatan_kategori', 'j.nama AS jabatan',
                     'uk.nama AS unit_kerja', 'su.nama AS sub_unit', 'p.nama AS profesi',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->leftJoin('jadwal_shift as js', function ($join) { $join->on('users.id', '=', 'js.user_id')->where('js.tanggal_berlaku', now()->toDateString()); })->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'users.jabatan_id')
            ->where('users.role', 'pegawai')
            ->where('users.id', $id)
            ->first();

        if (! $data) {
            return response()->json(['sukses' => false, 'pesan' => 'Pegawai tidak ditemukan.'], 404);
        }
        return response()->json(['sukses' => true, 'data' => $data]);
    }

    public function absensi(Request $request)
    {
        $this->cekAuth($request);
        $dari   = $this->tanggalValid($request->get('dari'), now()->format('Y-m-d'));
        $sampai = $this->tanggalValid($request->get('sampai'), $dari);
        $userId = (int) $request->get('user_id');

        if ($sampai < $dari) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        if ((strtotime($sampai) - strtotime($dari)) / 86400 > 92) {
            return response()->json(['sukses' => false,
                'pesan' => 'Rentang maksimal 92 hari per permintaan.'], 422);
        }

        $b = Absensi::select('absensi.id', 'absensi.user_id', 'u.nama_lengkap', 'absensi.tanggal',
                     'absensi.waktu_masuk', 'absensi.waktu_pulang',
                     'absensi.status_masuk', 'absensi.menit_terlambat', 'absensi.total_menit_kerja',
                     'absensi.lat_masuk', 'absensi.lng_masuk', 'absensi.lat_pulang', 'absensi.lng_pulang',
                     'absensi.flag_anomali', 'absensi.catatan_anomali',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang')
            ->join('users as u', 'u.id', '=', 'absensi.user_id')
            ->leftJoin('jadwal_shift as js', function ($join) {
                $join->on('js.user_id', '=', 'absensi.user_id')
                    ->on(DB::raw('DATE(js.tanggal_berlaku)'), '=', DB::raw('DATE(absensi.tanggal)'));
            })
            ->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->whereDate('absensi.tanggal', '>=', $dari)->whereDate('absensi.tanggal', '<=', $sampai);
        if ($userId) {
            $b->where('absensi.user_id', $userId);
        }
        $data = $b->orderBy('absensi.tanggal')->orderBy('u.nama_lengkap')->get()->all();

        return response()->json([
            'sukses' => true,
            'dari'   => $dari,
            'sampai' => $sampai,
            'jumlah' => count($data),
            'data'   => $data,
        ]);
    }

    public function rekap(Request $request)
    {
        $this->cekAuth($request);
        $bulan = min(12, max(1, (int) ($request->get('bulan') ?: now()->format('n'))));
        $tahun = min(2100, max(2024, (int) ($request->get('tahun') ?: now()->format('Y'))));

        $pegawai = User::select('users.id', 'users.nama_lengkap', 'uk.nama AS unit_kerja', 'su.nama AS sub_unit',
                     'p.nama AS profesi')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->where('users.role', 'pegawai')->where('users.status', 'aktif')
            ->orderBy('users.nama_lengkap')->get()->all();

        $lib  = app(RekapService::class);
        $data = [];
        foreach ($pegawai as $p) {
            $r = $lib->hitung((int) $p->id, $bulan, $tahun);
            $data[] = (array) $p + [
                'hari_efektif'      => $r['hari_efektif'],
                'hadir'             => $r['hadir'],
                'tepat_waktu'       => $r['tepat'],
                'terlambat'         => $r['terlambat'],
                'menit_terlambat'   => $r['menit_terlambat'],
                'alpa'              => $r['alpa'],
                'izin'              => $r['izin'],
                'sakit'             => $r['sakit'],
                'cuti'              => $r['cuti'],
                'dinas_luar'        => $r['dinas_luar'],
                'libur'             => $r['libur'],
                'total_menit_kerja' => $r['total_menit'],
                'persentase'        => $r['persen'],
            ];
        }

        return response()->json([
            'sukses' => true,
            'bulan'  => $bulan,
            'tahun'  => $tahun,
            'jumlah' => count($data),
            'data'   => $data,
        ]);
    }

    public function izin(Request $request)
    {
        $this->cekAuth($request);
        $status = (string) ($request->get('status') ?: 'Disetujui');
        if (! in_array($status, ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'], true)) {
            $status = 'Disetujui';
        }
        $dari   = $this->tanggalValid($request->get('dari'), now()->format('Y-m-01'));
        $sampai = $this->tanggalValid($request->get('sampai'), now()->format('Y-m-t'));

        $b = Izin::select('pengajuan_izin.id', 'pengajuan_izin.user_id', 'u.nama_lengkap',
                     'pengajuan_izin.jenis', 'pengajuan_izin.tanggal_mulai', 'pengajuan_izin.tanggal_selesai',
                     'pengajuan_izin.keterangan', 'pengajuan_izin.status', 'pengajuan_izin.catatan_admin',
                     'pengajuan_izin.created_at', 'pengajuan_izin.processed_at')
            ->join('users as u', 'u.id', '=', 'pengajuan_izin.user_id')
            ->where('pengajuan_izin.tanggal_mulai', '<=', $sampai)
            ->where('pengajuan_izin.tanggal_selesai', '>=', $dari);
        if ($status !== 'Semua') {
            $b->where('pengajuan_izin.status', $status);
        }
        $data = $b->orderBy('pengajuan_izin.tanggal_mulai')->get()->all();

        return response()->json([
            'sukses' => true,
            'status' => $status,
            'dari'   => $dari,
            'sampai' => $sampai,
            'jumlah' => count($data),
            'data'   => $data,
        ]);
    }

    private function tanggalValid($nilai, string $bawaan): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $nilai) ? (string) $nilai : $bawaan;
    }
}
