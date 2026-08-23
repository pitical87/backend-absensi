<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BintangService;
use App\Services\RekapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapController extends Controller
{
    public function statistik(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $data = $rekap->hitung($user->id, (int) now()->month, (int) now()->year);

        return response()->json([
            'sukses' => true,
            'kehadiran' => [
                'persen' => $data['persen'],
                'hadir' => $data['hadir'] + $data['dinas_luar'],
                'target' => $data['hari_efektif'],
            ],
            'jam_kerja' => [
                'total_jam' => round($data['total_menit'] / 60, 1),
                'target_jam' => (float) pengaturan('target_jam_kerja_bulanan', 160),
            ],
            'ketepatan' => [
                'tepat_masuk' => $data['persen_tepat_masuk'],
                'tepat_pulang' => $data['persen_tepat_pulang'],
            ],
            'bintang_bulanan' => $data['bintang_bulanan'],
        ]);
    }

    public function performaBulan(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $bulan = min(12, max(1, (int) ($req->query('bulan') ?: now()->subMonth()->month)));
        $tahun = (int) ($req->query('tahun') ?: now()->subMonth()->year);

        if ($bulan > now()->month && $tahun === (int) now()->year) {
            $bulan = now()->month;
        }

        $data = $rekap->hitung($user->id, $bulan, $tahun);
        $bintang = $data['bintang_bulanan'];
        $servis = app(BintangService::class);

        return response()->json([
            'sukses' => true,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nama_bulan' => BULAN_ID[$bulan] ?? $bulan,
            'bintang' => $bintang,
            'pesan' => $bintang === null ? null : $servis->pesanBulanan((float) $bintang),
        ]);
    }

    public function rekapBulanan(Request $req, RekapService $rekap): JsonResponse
    {
        $user = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter bulan/tahun tidak valid.',
            ], 422);
        }

        $d = $rekap->hitung($user->id, $bulan, $tahun);

        $detail = [];
        foreach ($d['per_tanggal'] as $tgl => $baris) {
            $rec = $baris['rec'];
            $item = [
                'tanggal' => $tgl,
                'hari' => Carbon::parse($tgl)->locale('id')->translatedFormat('l'),
                'status' => $baris['status'],
                'keterangan' => $baris['keterangan'],
                'jam_masuk' => null,
                'jam_pulang' => null,
                'menit_telat' => 0,
                'menit_pulang_awal' => 0,
                'total_jam_kerja' => null,
                'bintang_masuk' => null,
                'bintang_pulang' => null,
                'bintang_harian' => null,
            ];
            if ($rec) {
                $item['jam_masuk'] = substr((string) $rec->waktu_masuk, 11, 5);
                $item['jam_pulang'] = $rec->waktu_pulang ? substr((string) $rec->waktu_pulang, 11, 5) : null;
                $item['menit_telat'] = (int) $rec->menit_terlambat;
                $item['menit_pulang_awal'] = (int) $rec->menit_awal_pulang;
                $item['total_jam_kerja'] = $rec->total_menit_kerja !== null
                    ? round((int) $rec->total_menit_kerja / 60, 1) : null;
                $item['bintang_masuk'] = $rec->bintang_masuk !== null ? (int) $rec->bintang_masuk : null;
                $item['bintang_pulang'] = $rec->bintang_pulang !== null ? (int) $rec->bintang_pulang : null;
                $item['bintang_harian'] = $rec->bintang_harian !== null ? (float) $rec->bintang_harian : null;
            }
            $detail[] = $item;
        }

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
                'hari_dalam_bulan' => $d['hari_dalam_bulan'],
                'hari_berjalan' => $d['hari_berjalan'],
                'hari_efektif' => $d['hari_efektif'],
            ],
            'ringkasan' => [
                'hadir' => $d['hadir'],
                'tepat_masuk' => $d['tepat'],
                'terlambat' => $d['terlambat'],
                'total_menit_telat' => $d['menit_terlambat'],
                'tepat_pulang' => $d['tepat_pulang'],
                'pulang_awal' => $d['pulang_awal'],
                'total_menit_pulang_awal' => $d['menit_pulang_awal'],
                'izin' => $d['izin'], 'sakit' => $d['sakit'], 'cuti' => $d['cuti'],
                'dinas_luar' => $d['dinas_luar'], 'libur' => $d['libur'], 'alpa' => $d['alpa'],
                'anomali' => $d['anomali'],
                'persen_kehadiran' => $d['persen'],
                'total_jam_kerja' => round($d['total_menit'] / 60, 1),
                'bintang_bulanan' => $d['bintang_bulanan'],
            ],
            'detail' => $detail,
        ]);
    }

    public function rekapKeterlambatan(Request $req): JsonResponse
    {
        $user  = $req->get('user');
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $rows = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->where('a.user_id', $user->id)
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->orderBy('a.tanggal')
            ->get([
                'a.tanggal',
                'a.waktu_masuk',
                'a.waktu_pulang',
                'k.menit_telat',
                'k.bintang_masuk',
                'k.menit_awal_pulang',
                'k.bintang_pulang',
                'k.total_bintang',
            ]);

        $detail = $rows->map(fn ($r) => [
            'tanggal'    => substr((string) $r->tanggal, 0, 10),
            'hari'       => Carbon::parse($r->tanggal)->locale('id')->translatedFormat('l'),
            'jam_masuk'  => $r->waktu_masuk ? substr((string) $r->waktu_masuk, 11, 5) : null,
            'jam_pulang' => $r->waktu_pulang ? substr((string) $r->waktu_pulang, 11, 5) : null,
            'menit_telat' => (int) $r->menit_telat,
            'bintang_masuk' => $r->bintang_masuk !== null ? (int) $r->bintang_masuk : null,
            'menit_pulang_awal' => (int) $r->menit_awal_pulang,
            'bintang_pulang' => $r->bintang_pulang !== null ? (int) $r->bintang_pulang : null,
            'total_bintang' => $r->total_bintang !== null ? (float) $r->total_bintang : null,
        ]);

        $terlambat = $detail->where('menit_telat', '>', 0);
        $pulangAwal = $detail->where('menit_pulang_awal', '>', 0);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'ringkasan' => [
                'tercatat'   => $detail->count(),
                'terlambat'  => $terlambat->count(),
                'total_menit_telat' => $detail->sum('menit_telat'),
                'rata_menit_telat'  => $terlambat->isNotEmpty() ? round($terlambat->avg('menit_telat'), 1) : 0.0,
                'terlama_menit_telat' => $terlambat->max('menit_telat') ?? 0,
                'pulang_awal' => $pulangAwal->count(),
                'total_menit_pulang_awal' => $detail->sum('menit_pulang_awal'),
                'rata_bintang_masuk'  => $this->rataKolom($detail, 'bintang_masuk'),
                'rata_bintang_pulang' => $this->rataKolom($detail, 'bintang_pulang'),
                'rata_bintang_total'  => $this->rataKolom($detail, 'total_bintang'),
            ],
            'detail' => $detail->values(),
        ]);
    }

    private function rataKolom($detail, string $kolom): ?float
    {
        $isi = $detail->filter(fn ($d) => $d[$kolom] !== null);

        return $isi->isNotEmpty() ? round($isi->avg($kolom), 1) : null;
    }

    public function pegawaiTeladan(Request $req): JsonResponse
    {
        $bulan = (int) ($req->query('bulan') ?: now()->month);
        $tahun = (int) ($req->query('tahun') ?: now()->year);

        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > (int) now()->year + 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter bulan/tahun tidak valid.'], 422);
        }

        $daftar = DB::table('keterlambatan as k')
            ->join('absensi as a', 'a.id', '=', 'k.absensi_id')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->whereYear('a.tanggal', $tahun)
            ->whereMonth('a.tanggal', $bulan)
            ->where('u.role', '!=', 'admin')
            ->groupBy('u.id', 'u.nama_lengkap', 'uk.nama', 'su.nama')
            ->orderByDesc(DB::raw('COALESCE(SUM(k.total_bintang), 0)'))
            ->orderByDesc(DB::raw('AVG(k.total_bintang)'))
            ->orderBy('u.nama_lengkap')
            ->limit(10)
            ->get([
                'u.id AS pegawai_id',
                'u.nama_lengkap',
                DB::raw('COALESCE(su.nama, uk.nama) AS unit'),
                DB::raw('COUNT(*) AS hari_tercatat'),
                DB::raw('ROUND(COALESCE(SUM(k.total_bintang), 0), 1) AS total_bintang'),
                DB::raw('ROUND(AVG(k.total_bintang), 2) AS rata_bintang'),
                DB::raw('SUM(CASE WHEN k.menit_telat > 0 THEN 1 ELSE 0 END) AS jumlah_telat'),
                DB::raw('SUM(CASE WHEN k.bintang_masuk = 5 OR k.bintang_pulang = 5 THEN 1 ELSE 0 END) AS hari_bintang_lima'),
            ])
            ->values()
            ->map(fn ($r, $i) => [
                'peringkat'   => $i + 1,
                'pegawai_id'  => (int) $r->pegawai_id,
                'nama'        => $r->nama_lengkap,
                'unit'        => $r->unit,
                'hari_tercatat' => (int) $r->hari_tercatat,
                'total_bintang' => (float) $r->total_bintang,
                'rata_bintang'  => (float) $r->rata_bintang,
                'jumlah_telat'  => (int) $r->jumlah_telat,
                'hari_bintang_lima' => (int) $r->hari_bintang_lima,
            ]);

        return response()->json([
            'sukses' => true,
            'periode' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'label' => (BULAN_ID[$bulan] ?? $bulan).' '.$tahun,
            ],
            'daftar' => $daftar,
        ]);
    }
}
