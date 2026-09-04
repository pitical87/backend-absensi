<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Profesi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\StrukturService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapLemburController extends Controller
{
    private function ambilParam(Request $request): array
    {
        return [
            'bulan' => min(12, max(1, (int) ($request->get('bulan') ?: now()->format('n')))),
            'tahun' => min(2100, max(2024, (int) ($request->get('tahun') ?: now()->format('Y')))),
            'unit' => (int) $request->get('unit'),
            'jab' => in_array((string) $request->get('jab'), kategori_jabatan_list(), true) ? (string) $request->get('jab') : '',
            'njab' => (int) $request->get('njab'),
            'org' => (int) $request->get('org'),
            'prof' => (int) $request->get('prof'),
            'q' => trim((string) $request->get('q')),
        ];
    }

    private function buildQuery(array $f)
    {
        $b = User::query()
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'users.jabatan_id')
            ->where('users.role', 'pegawai')
            ->where('users.status', 'aktif');

        if ($f['unit']) {
            $b->where('users.unit_kerja_id', $f['unit']);
        }
        if ($f['jab']) {
            $b->where('users.jabatan_kategori', $f['jab']);
        }
        if ($f['njab']) {
            $b->where('users.jabatan_id', $f['njab']);
        }
        if ($f['org']) {
            $b->whereIn('users.jabatan_id', app(StrukturService::class)->keturunan($f['org']));
        }
        if ($f['prof']) {
            $b->where('users.profesi_id', $f['prof']);
        }
        if ($f['q'] !== '') {
            $b->where(function ($qry) use ($f) {
                $qry->where('users.nama_lengkap', 'like', "%{$f['q']}%")
                    ->orWhere('users.nip', 'like', "%{$f['q']}%");
            });
        }

        $b->select(
            'users.id',
            'users.nama_lengkap',
            'users.nip',
            DB::raw("COALESCE(uk.nama, '-') AS unit_nama"),
            DB::raw("COALESCE(su.nama, '') AS sub_nama"),
            DB::raw("COALESCE(p.nama, '') AS profesi_nama"),
            DB::raw("COALESCE(j.nama, '') AS jabatan_nama")
        );

        return $b;
    }

    private function hitungSemua(array $pegawai, int $bulan, int $tahun): array
    {
        $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = sprintf('%04d-%02d-%02d', $tahun, $bulan, (int) date('t', strtotime($awal)));

        // Agregat pengajuan lembur disetujui per pegawai.
        $pengajuan = DB::table('pengajuan_lembur as pl')
            ->where('pl.status', 'Disetujui')
            ->whereBetween('pl.tanggal', [$awal, $akhir])
            ->groupBy('pl.user_id')
            ->selectRaw('pl.user_id,
                COUNT(*) AS jumlah_pengajuan,
                COUNT(DISTINCT pl.tanggal) AS jumlah_hari,
                COALESCE(SUM(pl.durasi_jam), 0) AS total_jam')
            ->get()->keyBy('user_id');

        // Agregat absen lembur aktual per pegawai.
        $aktual = DB::table('absen_lembur as al')
            ->whereBetween('al.tanggal', [$awal, $akhir])
            ->groupBy('al.user_id')
            ->selectRaw('al.user_id,
                COUNT(*) AS jumlah_absen,
                COUNT(DISTINCT al.tanggal) AS jumlah_hari_aktual,
                COALESCE(SUM(al.durasi_menit), 0) AS total_menit_aktual')
            ->get()->keyBy('user_id');

        $per = [];
        foreach ($pegawai as $p) {
            $pu = $pengajuan->get((int) $p->id);
            $al = $aktual->get((int) $p->id);
            $per[(int) $p->id] = [
                'jumlah_pengajuan' => (int) ($pu->jumlah_pengajuan ?? 0),
                'jumlah_hari' => (int) ($pu->jumlah_hari ?? 0),
                'total_jam' => (float) ($pu->total_jam ?? 0),
                'jumlah_absen' => (int) ($al->jumlah_absen ?? 0),
                'jumlah_hari_aktual' => (int) ($al->jumlah_hari_aktual ?? 0),
                'total_menit_aktual' => (int) ($al->total_menit_aktual ?? 0),
            ];
        }

        return $per;
    }

    private function qsDari(array $f): string
    {
        return http_build_query(array_filter([
            'bulan' => $f['bulan'], 'tahun' => $f['tahun'],
            'unit' => $f['unit'] ?: null, 'jab' => $f['jab'] ?: null,
            'njab' => $f['njab'] ?: null, 'org' => $f['org'] ?: null,
            'prof' => $f['prof'] ?: null, 'q' => $f['q'] ?: null,
        ]));
    }

    public function index(Request $request)
    {
        $f = $this->ambilParam($request);
        $halaman = max(1, (int) $request->get('hal'));
        $per = 25;

        $b = $this->buildQuery($f);
        $total = $b->count();
        $pegawai = $b->orderBy('uk.id')->orderBy('users.nama_lengkap')
            ->skip(($halaman - 1) * $per)->take($per)
            ->get()->all();

        return view('admin.rekap_lembur.index', [
            'judulHalaman' => 'Rekap Lembur',
            'menuAktif' => 'rekap_lembur',
            'f' => $f,
            'bulan' => $f['bulan'],
            'tahun' => $f['tahun'],
            'fUnit' => $f['unit'],
            'pegawai' => $pegawai,
            'rekapPer' => $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']),
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'profList' => Profesi::orderBy('id')->get()->all(),
            'jabPilihan' => app(StrukturService::class)->pilihan(),
            'orgList' => app(StrukturService::class)->unitOrganisasi(),
            'kategoriJab' => kategori_jabatan_list(),
            'halaman' => $halaman,
            'totalHal' => max(1, (int) ceil($total / $per)),
            'qs' => $this->qsDari($f),
        ]);
    }

    public function cetak(Request $request)
    {
        $f = $this->ambilParam($request);
        $pegawai = $this->buildQuery($f)->orderBy('uk.id')->orderBy('users.nama_lengkap')->get()->all();
        $rekapPer = $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']);

        catat_aktivitas('Cetak Rekap Lembur', BULAN_ID[$f['bulan']].' '.$f['tahun']);

        return view('admin.rekap_lembur.cetak', [
            'bulan' => $f['bulan'],
            'tahun' => $f['tahun'],
            'f' => $f,
            'pegawai' => $pegawai,
            'rekapPer' => $rekapPer,
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }
}
