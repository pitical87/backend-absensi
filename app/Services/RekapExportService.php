<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\RekapBulanan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RekapExportService
{
    public function generateRekapBulanan(array $filter): void
    {
        $bulan = (int) $filter['bulan'];
        $tahun = (int) $filter['tahun'];

        $users = User::where('role', 'pegawai')->where('status', 'aktif')
            ->when($filter['unit'] ?? null, fn ($q, $u) => $q->where('unit_kerja_id', $u))
            ->when($filter['jab'] ?? null, fn ($q, $j) => $q->where('jabatan_kategori', $j))
            ->when($filter['njab'] ?? null, fn ($q, $nj) => $q->where('jabatan_id', $nj))
            ->when($filter['prof'] ?? null, fn ($q, $p) => $q->where('profesi_id', $p))
            ->get();

        $rekapService = new RekapService();

        foreach ($users as $u) {
            $r = $rekapService->hitung($u->id, $bulan, $tahun);
            RekapBulanan::updateOrCreate(
                ['user_id' => $u->id, 'bulan' => $bulan, 'tahun' => $tahun],
                [
                    'total_hari_efektif' => $r['hari_efektif'],
                    'total_hadir' => $r['hadir'],
                    'total_tepat_waktu' => $r['tepat'],
                    'total_terlambat' => $r['terlambat'],
                    'total_alpa' => $r['alpa'],
                    'total_izin' => $r['izin'],
                    'total_sakit' => $r['sakit'],
                    'total_cuti' => $r['cuti'],
                    'total_dinas_luar' => $r['dinas_luar'],
                    'total_libur' => $r['libur'],
                    'total_menit_kerja' => $r['total_menit'],
                    'persentase' => $r['persen'],
                    'generated_at' => now(),
                ]
            );
        }
    }

    public function getRekapData(array $filter): array
    {
        $bulan = (int) $filter['bulan'];
        $tahun = (int) $filter['tahun'];

        $users = User::where('role', 'pegawai')->where('status', 'aktif')
            ->when($filter['unit'] ?? null, fn ($q, $u) => $q->where('unit_kerja_id', $u))
            ->when($filter['jab'] ?? null, fn ($q, $j) => $q->where('jabatan_kategori', $j))
            ->when($filter['njab'] ?? null, fn ($q, $nj) => $q->where('jabatan_id', $nj))
            ->when($filter['prof'] ?? null, fn ($q, $p) => $q->where('profesi_id', $p))
            ->get();

        $rekapService = new RekapService();
        $rekapPer = [];
        foreach ($users as $u) {
            $rekapPer[(int) $u->id] = $rekapService->hitung($u->id, $bulan, $tahun);
        }

        return ['users' => $users, 'rekapPer' => $rekapPer];
    }

    public function getDetailData(array $filter): \Illuminate\Support\Collection
    {
        $bulan = (int) $filter['bulan'];
        $tahun = (int) $filter['tahun'];

        $q = DB::table('absensi as a')
            ->selectRaw('a.*, u.nama_lengkap, uk.nama AS unit_nama, su.nama AS sub_nama,
                s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->join('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id', '=', 'u.sub_unit_id', 'left')
            ->join('shift as s', 's.id', '=', 'a.shift_id', 'left')
            ->whereRaw('MONTH(a.tanggal) = ?', [$bulan])
            ->whereRaw('YEAR(a.tanggal) = ?', [$tahun]);

        if ($filter['unit'] ?? null) $q->where('u.unit_kerja_id', $filter['unit']);
        if ($filter['jab'] ?? null) $q->where('u.jabatan_kategori', $filter['jab']);
        if ($filter['njab'] ?? null) $q->where('u.jabatan_id', $filter['njab']);
        if ($filter['prof'] ?? null) $q->where('u.profesi_id', $filter['prof']);

        if ($filter['org'] ?? null) {
            $strukturService = app(StrukturService::class);
            $keturunan = $strukturService->keturunan((int) $filter['org']);
            $q->whereIn('u.jabatan_id', $keturunan);
        }

        return $q->orderBy('a.tanggal')->orderBy('u.nama_lengkap')->get();
    }
}
