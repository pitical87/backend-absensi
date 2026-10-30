<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenLembur;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Logbook;
use App\Models\PengajuanLembur;
use App\Models\Profesi;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EksekutifController extends Controller
{
    private function ambilParam(Request $request): array
    {
        $mode = in_array($request->get('mode'), ['tren', 'bulan', 'minggu', 'unit'], true) ? $request->get('mode') : 'tren';
        $tahun = min(2100, max(2024, (int) ($request->get('tahun') ?: now()->format('Y'))));
        $bulan = min(12, max(1, (int) ($request->get('bulan') ?: now()->format('n'))));
        $mingguMaks = Carbon::create($tahun, 12, 28)->isoWeek();
        $minggu = min($mingguMaks, max(1, (int) ($request->get('minggu') ?: now()->isoWeek())));

        $dari = $request->get('dari') ?: now()->startOfMonth()->toDateString();
        $sampai = $request->get('sampai') ?: now()->toDateString();

        if ($mode === 'bulan') {
            $dari = Carbon::create($tahun, $bulan, 1)->startOfMonth()->toDateString();
            $sampai = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();
        } elseif ($mode === 'minggu') {
            $mulai = Carbon::now()->setISODate($tahun, $minggu, 1)->startOfDay();
            $dari = $mulai->toDateString();
            $sampai = $mulai->copy()->addDays(6)->toDateString();
        }

        if ($sampai < $dari) {
            $sampai = $dari;
        }

        return [
            'mode' => $mode,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'minggu' => $minggu,
            'dari' => $dari,
            'sampai' => $sampai,
            'unit' => (int) $request->get('unit'),
            'sub' => (int) $request->get('sub'),
            'jab' => in_array((string) $request->get('jab'), kategori_jabatan_list(), true) ? (string) $request->get('jab') : '',
            'prof' => (int) $request->get('prof'),
        ];
    }

    private function labelPeriode(array $f): string
    {
        return match ($f['mode']) {
            'bulan' => BULAN_ID[$f['bulan']].' '.$f['tahun'],
            'minggu' => 'Minggu ke-'.$f['minggu'].' ('.tgl_id($f['dari'], false).' s/d '.tgl_id($f['sampai'], false).')',
            'unit' => $f['dari'].' s/d '.$f['sampai'],
            default => 'Tren '.$f['tahun'],
        };
    }

    private function labelFilter(array $f): string
    {
        $unit = $f['unit'] ? UnitKerja::find($f['unit'])?->nama : null;
        $sub = $f['sub'] ? SubUnit::with('unitKerja:id,nama')->find($f['sub']) : null;
        $prof = $f['prof'] ? Profesi::find($f['prof'])?->nama : null;

        $subLabel = $sub ? $sub->nama : 'Semua Sub Unit';
        if ($sub && $sub->unitKerja) {
            $subLabel .= ' ('.$sub->unitKerja->nama.')';
        }

        return implode(' · ', [
            'Unit: '.($unit ?: 'Semua Unit'),
            'Sub Unit: '.$subLabel,
            'Jabatan: '.($f['jab'] ?: 'Semua Jabatan'),
            'Profesi: '.($prof ?: 'Semua Profesi'),
        ]);
    }

    private function daftarMinggu(int $tahun): array
    {
        $hasil = [];
        $jumlah = Carbon::create($tahun, 12, 28)->isoWeek();
        for ($m = 1; $m <= $jumlah; $m++) {
            $awal = Carbon::now()->setISODate($tahun, $m, 1)->startOfDay();
            $akhir = $awal->copy()->addDays(6);
            $hasil[] = [
                'minggu' => $m,
                'label' => 'Minggu '.$m.' ('.tgl_id($awal->toDateString(), false).' – '.tgl_id($akhir->toDateString(), false).')',
            ];
        }

        return $hasil;
    }

    /** rentang bulan utk mode tren selama satu tahun (Jan–Des tahun terpilih). */
    private function rentangTahun(int $tahun): array
    {
        $mulai = [];
        foreach (range(1, 12) as $m) {
            $mulai[] = sprintf('%04d-%02d-01', $tahun, $m);
        }

        return $mulai;
    }

    private function userWhere(array $f)
    {
        $b = User::query()->where('role', 'pegawai')->where('status', 'aktif');
        if ($f['unit']) {
            $b->where('unit_kerja_id', $f['unit']);
        }
        if ($f['sub']) {
            $b->where('sub_unit_id', $f['sub']);
        }
        if ($f['jab']) {
            $b->where('jabatan_kategori', $f['jab']);
        }
        if ($f['prof']) {
            $b->where('profesi_id', $f['prof']);
        }

        return $b;
    }

    /** agregat absensi dalam rentang [dari, sampai] utk user id tertentu (opsional). */
    private function agregatAbsensi(string $dari, string $sampai, ?array $userId = null): array
    {
        $q = Absensi::query()
            ->selectRaw(
                'COUNT(*) AS hadir,
                SUM(CASE WHEN status_masuk = \'Tepat Waktu\' THEN 1 ELSE 0 END) AS tepat,
                SUM(CASE WHEN status_masuk = \'Terlambat\' THEN 1 ELSE 0 END) AS terlambat,
                COALESCE(SUM(menit_terlambat), 0) AS menit_terlambat,
                SUM(CASE WHEN flag_anomali = 1 THEN 1 ELSE 0 END) AS anomali'
            )
            ->whereBetween('tanggal', [$dari, $sampai]);
        if (is_array($userId)) {
            $q->whereIn('user_id', $userId);
        }
        $r = $q->first();

        return [
            'hadir' => (int) ($r->hadir ?? 0),
            'tepat' => (int) ($r->tepat ?? 0),
            'terlambat' => (int) ($r->terlambat ?? 0),
            'menit_terlambat' => (int) ($r->menit_terlambat ?? 0),
            'anomali' => (int) ($r->anomali ?? 0),
        ];
    }

    private function agregatIzin(string $dari, string $sampai, ?array $userId = null): array
    {
        $q = Izin::query()
            ->selectRaw(
                'COUNT(*) AS total,
                SUM(CASE WHEN status = \'Disetujui\' THEN 1 ELSE 0 END) AS disetujui,
                SUM(CASE WHEN status = \'Menunggu\' THEN 1 ELSE 0 END) AS menunggu,
                COALESCE(SUM(CASE WHEN status = \'Disetujui\' THEN lama_hari ELSE 0 END), 0) AS total_hari,
                SUM(CASE WHEN jenis = \'Sakit\' THEN 1 ELSE 0 END) AS sakit,
                SUM(CASE WHEN jenis = \'Cuti\' THEN 1 ELSE 0 END) AS cuti,
                SUM(CASE WHEN jenis = \'Izin\' THEN 1 ELSE 0 END) AS izin,
                SUM(CASE WHEN jenis = \'Dinas Luar\' THEN 1 ELSE 0 END) AS dinas'
            )
            ->where('tanggal_mulai', '<=', $sampai)
            ->where('tanggal_selesai', '>=', $dari);
        if (is_array($userId)) {
            $q->whereIn('user_id', $userId);
        }
        $r = $q->first();

        return [
            'total' => (int) ($r->total ?? 0),
            'disetujui' => (int) ($r->disetujui ?? 0),
            'menunggu' => (int) ($r->menunggu ?? 0),
            'total_hari' => (int) ($r->total_hari ?? 0),
            'sakit' => (int) ($r->sakit ?? 0),
            'cuti' => (int) ($r->cuti ?? 0),
            'izin' => (int) ($r->izin ?? 0),
            'dinas' => (int) ($r->dinas ?? 0),
        ];
    }

    private function agregatLembur(string $dari, string $sampai, ?array $userId = null): array
    {
        $q = PengajuanLembur::query()
            ->selectRaw(
                'COUNT(*) AS total,
                SUM(CASE WHEN status = \'Disetujui\' THEN 1 ELSE 0 END) AS disetujui,
                COALESCE(SUM(CASE WHEN status = \'Disetujui\' THEN durasi_jam ELSE 0 END), 0) AS total_jam'
            )
            ->whereBetween('tanggal', [$dari, $sampai]);
        if (is_array($userId)) {
            $q->whereIn('user_id', $userId);
        }
        $r = $q->first();

        return [
            'total' => (int) ($r->total ?? 0),
            'disetujui' => (int) ($r->disetujui ?? 0),
            'total_jam' => (float) ($r->total_jam ?? 0),
        ];
    }

    private function agregatAbsenLembur(string $dari, string $sampai, ?array $userId = null): array
    {
        $q = AbsenLembur::query()
            ->selectRaw('COUNT(*) AS jumlah, COALESCE(SUM(durasi_menit), 0) AS total_menit')
            ->whereBetween('tanggal', [$dari, $sampai]);
        if (is_array($userId)) {
            $q->whereIn('user_id', $userId);
        }
        $r = $q->first();

        return [
            'jumlah' => (int) ($r->jumlah ?? 0),
            'total_menit' => (int) ($r->total_menit ?? 0),
        ];
    }

    private function agregatLogbook(string $dari, string $sampai, ?array $userId = null): array
    {
        $q = Logbook::query()
            ->selectRaw(
                'COUNT(*) AS jumlah,
                COUNT(DISTINCT tanggal) AS jumlah_hari,
                COALESCE(SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END), 0) AS terverifikasi'
            )
            ->whereBetween('tanggal', [$dari, $sampai]);
        if (is_array($userId)) {
            $q->whereIn('user_id', $userId);
        }
        $r = $q->first();

        return [
            'jumlah' => (int) ($r->jumlah ?? 0),
            'jumlah_hari' => (int) ($r->jumlah_hari ?? 0),
            'terverifikasi' => (int) ($r->terverifikasi ?? 0),
            'persen_verif' => $r->jumlah ? round(($r->terverifikasi ?? 0) * 100 / $r->jumlah, 1) : 0,
        ];
    }

    private function hitungPeriode(string $dari, string $sampai, array $f): array
    {
        $users = $this->userWhere($f)->pluck('id')->all();
        $adaFilter = (bool) ($f['unit'] || $f['sub'] || $f['jab'] || $f['prof']);
        $userId = $adaFilter ? $users : null;

        $absensi = $this->agregatAbsensi($dari, $sampai, $userId);
        $izin = $this->agregatIzin($dari, $sampai, $userId);
        $lembur = $this->agregatLembur($dari, $sampai, $userId);
        $absenLembur = $this->agregatAbsenLembur($dari, $sampai, $userId);
        $logbook = $this->agregatLogbook($dari, $sampai, $userId);

        $totalPegawai = count($users);

        return [
            'total_pegawai' => $totalPegawai,
            'absensi' => $absensi,
            'izin' => $izin,
            'lembur' => $lembur,
            'absen_lembur' => $absenLembur,
            'logbook' => $logbook,
        ];
    }

    private function hitungHarian(string $dari, string $sampai, array $f): array
    {
        $hasil = [];
        $tanggal = Carbon::parse($dari);
        $akhir = Carbon::parse($sampai);

        while ($tanggal->lte($akhir)) {
            $t = $tanggal->toDateString();
            $p = $this->hitungPeriode($t, $t, $f);
            $hasil[] = [
                'tanggal' => $t,
                'label' => $tanggal->format('d').' '.substr(BULAN_ID[(int) $tanggal->format('n')], 0, 3).' '.$tanggal->format('Y'),
                'hari' => HARI_ID[(int) $tanggal->format('w')],
                'hadir' => $p['absensi']['hadir'],
                'tepat' => $p['absensi']['tepat'],
                'terlambat' => $p['absensi']['terlambat'],
                'izin' => $p['izin']['disetujui'],
                'jam_lembur' => $p['lembur']['total_jam'],
                'logbook' => $p['logbook']['jumlah'],
            ];
            $tanggal->addDay();
        }

        return $hasil;
    }

    public function index(Request $request)
    {
        $f = $this->ambilParam($request);
        $unitList = UnitKerja::orderBy('id')->get()->all();
        $subUnitList = SubUnit::with('unitKerja:id,nama')->orderBy('unit_kerja_id')->orderBy('nama')->get()->all();

        $tren = [];
        $perUnit = [];
        $harian = [];
        $ringkasan = null;

        if ($f['mode'] === 'tren') {
            $tahun = $f['tahun'];
            $bulanMulai = $this->rentangTahun($tahun);
            $keseluruhan = ['absensi' => ['hadir' => 0, 'terlambat' => 0], 'izin' => ['disetujui' => 0], 'lembur' => ['total_jam' => 0], 'logbook' => ['jumlah' => 0]];
            foreach ($bulanMulai as $awal) {
                $akhir = date('Y-m-t', strtotime($awal));
                $p = $this->hitungPeriode($awal, $akhir, $f);
                $bulan = (int) date('n', strtotime($awal));
                $tren[] = [
                    'bulan' => $bulan,
                    'label' => substr(BULAN_ID[$bulan], 0, 3).' '.substr($tahun, 2),
                    'hadir' => $p['absensi']['hadir'],
                    'tepat' => $p['absensi']['tepat'],
                    'terlambat' => $p['absensi']['terlambat'],
                    'menit_terlambat' => $p['absensi']['menit_terlambat'],
                    'izin' => $p['izin']['disetujui'],
                    'jam_lembur' => $p['lembur']['total_jam'],
                    'logbook' => $p['logbook']['jumlah'],
                ];
                $keseluruhan['absensi']['hadir'] += $p['absensi']['hadir'];
                $keseluruhan['absensi']['terlambat'] += $p['absensi']['terlambat'];
                $keseluruhan['izin']['disetujui'] += $p['izin']['disetujui'];
                $keseluruhan['lembur']['total_jam'] += $p['lembur']['total_jam'];
                $keseluruhan['logbook']['jumlah'] += $p['logbook']['jumlah'];
            }
            $ringkasan = $keseluruhan;
            $ringkasan['total_pegawai'] = $this->hitungPeriode($bulanMulai[0], date('Y-m-t', strtotime($bulanMulai[11])), $f)['total_pegawai'];
        } elseif ($f['mode'] === 'unit') {
            // Perbandingan antar unit dalam rentang tanggal bebas.
            foreach ($unitList as $uk) {
                $sub = $f;
                $sub['unit'] = (int) $uk->id;
                $p = $this->hitungPeriode($f['dari'], $f['sampai'], $sub);
                $perUnit[] = [
                    'unit_id' => (int) $uk->id,
                    'unit_nama' => $uk->nama,
                    'total_pegawai' => $p['total_pegawai'],
                    'hadir' => $p['absensi']['hadir'],
                    'terlambat' => $p['absensi']['terlambat'],
                    'menit_terlambat' => $p['absensi']['menit_terlambat'],
                    'izin' => $p['izin']['disetujui'],
                    'jam_lembur' => $p['lembur']['total_jam'],
                    'logbook' => $p['logbook']['jumlah'],
                ];
            }
            $ringkasan = $this->hitungPeriode($f['dari'], $f['sampai'], $f);
        } else {
            // Rincian harian untuk periode bulanan/mingguan.
            $ringkasan = $this->hitungPeriode($f['dari'], $f['sampai'], $f);
            $harian = $this->hitungHarian($f['dari'], $f['sampai'], $f);
        }

        return view('admin.eksekutif.index', [
            'judulHalaman' => 'Dashboard Eksekutif',
            'menuAktif' => 'eksekutif',
            'f' => $f,
            'unitList' => $unitList,
            'subUnitList' => $subUnitList,
            'tren' => $tren,
            'perUnit' => $perUnit,
            'harian' => $harian,
            'ringkasan' => $ringkasan,
            'kategoriJab' => kategori_jabatan_list(),
            'labelPeriode' => $this->labelPeriode($f),
            'labelFilter' => $this->labelFilter($f),
            'mingguList' => $this->daftarMinggu($f['tahun']),
        ]);
    }

    public function cetak(Request $request)
    {
        $f = $this->ambilParam($request);
        $unitList = UnitKerja::orderBy('id')->get()->all();

        $tren = [];
        $perUnit = [];
        $harian = [];
        $ringkasan = null;

        if ($f['mode'] === 'tren') {
            $tahun = $f['tahun'];
            $tren = [];
            foreach ($this->rentangTahun($tahun) as $awal) {
                $akhir = date('Y-m-t', strtotime($awal));
                $p = $this->hitungPeriode($awal, $akhir, $f);
                $bulan = (int) date('n', strtotime($awal));
                $tren[] = [
                    'label' => BULAN_ID[$bulan].' '.$tahun,
                    'hadir' => $p['absensi']['hadir'],
                    'terlambat' => $p['absensi']['terlambat'],
                    'izin' => $p['izin']['disetujui'],
                    'jam_lembur' => $p['lembur']['total_jam'],
                    'logbook' => $p['logbook']['jumlah'],
                ];
            }
            $ringkasan = $this->hitungPeriode($tahun.'-01-01', $tahun.'-12-31', $f);
        } elseif ($f['mode'] === 'unit') {
            foreach ($unitList as $uk) {
                $sub = $f;
                $sub['unit'] = (int) $uk->id;
                $p = $this->hitungPeriode($f['dari'], $f['sampai'], $sub);
                $perUnit[] = [
                    'unit_nama' => $uk->nama,
                    'total_pegawai' => $p['total_pegawai'],
                    'hadir' => $p['absensi']['hadir'],
                    'terlambat' => $p['absensi']['terlambat'],
                    'izin' => $p['izin']['disetujui'],
                    'jam_lembur' => $p['lembur']['total_jam'],
                    'logbook' => $p['logbook']['jumlah'],
                ];
            }
            $ringkasan = $this->hitungPeriode($f['dari'], $f['sampai'], $f);
        } else {
            $ringkasan = $this->hitungPeriode($f['dari'], $f['sampai'], $f);
            $harian = $this->hitungHarian($f['dari'], $f['sampai'], $f);
        }

        catat_aktivitas('Cetak Dashboard Eksekutif', $this->labelPeriode($f));

        return view('admin.eksekutif.cetak', [
            'f' => $f,
            'unitList' => $unitList,
            'tren' => $tren,
            'perUnit' => $perUnit,
            'harian' => $harian,
            'ringkasan' => $ringkasan,
            'labelPeriode' => $this->labelPeriode($f),
            'labelFilter' => $this->labelFilter($f),
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }
}
