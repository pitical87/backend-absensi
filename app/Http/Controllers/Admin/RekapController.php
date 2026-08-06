<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RekapService;
use App\Services\StrukturService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapController extends Controller
{
    private function ambilParam(Request $request): array
    {
        $sumber = $request->isMethod('POST') ? 'input' : 'query';
        $jab    = (string) $request->$sumber('jab');

        return [
            'bulan' => min(12, max(1, (int) ($request->$sumber('bulan') ?: now()->format('n')))),
            'tahun' => min(2100, max(2024, (int) ($request->$sumber('tahun') ?: now()->format('Y')))),
            'unit'  => (int) $request->$sumber('unit'),
            'jab'   => in_array($jab, kategori_jabatan_list(), true) ? $jab : '',
            'njab'  => (int) $request->$sumber('njab'),
            'org'   => (int) $request->$sumber('org'),
            'prof'  => (int) $request->$sumber('prof'),
        ];
    }

    private function qsDari(array $f): string
    {
        return http_build_query(array_filter([
            'bulan' => $f['bulan'], 'tahun' => $f['tahun'],
            'unit'  => $f['unit'] ?: null, 'jab' => $f['jab'] ?: null,
            'njab'  => $f['njab'] ?: null, 'org' => $f['org'] ?: null,
            'prof'  => $f['prof'] ?: null,
        ]));
    }

    private function labelFilter(array $f): string
    {
        $bagian = [];
        if ($f['unit']) {
            $u = DB::table('unit_kerja')->where('id', $f['unit'])->first();
            if ($u) $bagian[] = 'Unit ' . $u->nama;
        }
        if ($f['jab']) $bagian[] = 'Jabatan ' . $f['jab'];
        if ($f['njab']) {
            $j = DB::table('jabatan')->where('id', $f['njab'])->first();
            if ($j) $bagian[] = $j->nama;
        }
        if ($f['org']) {
            $j = DB::table('jabatan')->where('id', $f['org'])->first();
            if ($j) $bagian[] = $j->unit_label ?: $j->nama;
        }
        if ($f['prof']) {
            $p = DB::table('profesi')->where('id', $f['prof'])->first();
            if ($p) $bagian[] = 'Profesi ' . $p->nama;
        }
        return $bagian ? implode(' · ', $bagian) : 'Seluruh Pegawai';
    }

    private function daftarPegawai(array $f): array
    {
        $b = DB::table('users as u')
            ->select('u.id', 'u.nama_lengkap', 'u.nip', 'u.jabatan_kategori',
                     'uk.nama AS unit_nama', 'su.nama AS sub_nama', 'p.nama AS profesi_nama',
                     'j.nama AS jabatan_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id')
            ->where('u.role', 'pegawai')->where('u.status', 'aktif');

        if ($f['unit']) $b->where('u.unit_kerja_id', $f['unit']);
        if ($f['jab'])  $b->where('u.jabatan_kategori', $f['jab']);
        if ($f['njab']) $b->where('u.jabatan_id', $f['njab']);
        if ($f['org'])  $b->whereIn('u.jabatan_id', app(StrukturService::class)->keturunan($f['org']));
        if ($f['prof']) $b->where('u.profesi_id', $f['prof']);

        return $b->orderBy('uk.id')->orderBy('u.nama_lengkap')->get()->all();
    }

    private function hitungSemua(array $pegawai, int $bulan, int $tahun): array
    {
        $lib = app(RekapService::class);
        $per = [];
        foreach ($pegawai as $p) {
            $per[(int) $p->id] = $lib->hitung((int) $p->id, $bulan, $tahun);
        }
        return $per;
    }

    public function index(Request $request)
    {
        $f       = $this->ambilParam($request);
        $pegawai = $this->daftarPegawai($f);
        $lib     = app(StrukturService::class);

        return view('admin.rekap', [
            'judulHalaman' => 'Rekap & Laporan',
            'menuAktif'    => 'rekap',
            'f'            => $f,
            'bulan'        => $f['bulan'],
            'tahun'        => $f['tahun'],
            'fUnit'        => $f['unit'],
            'pegawai'      => $pegawai,
            'rekapPer'     => $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']),
            'unitList'     => DB::table('unit_kerja')->orderBy('id')->get()->all(),
            'profList'     => DB::table('profesi')->orderBy('id')->get()->all(),
            'jabPilihan'   => $lib->pilihan(),
            'orgList'      => $lib->unitOrganisasi(),
            'kategoriJab'  => kategori_jabatan_list(),
            'qs'           => $this->qsDari($f),
        ]);
    }

    public function generate(Request $request)
    {
        $f        = $this->ambilParam($request);
        $pegawai  = $this->daftarPegawai($f);
        $rekapPer = $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']);
        [$bulan, $tahun] = [$f['bulan'], $f['tahun']];

        foreach ($rekapPer as $uid => $r) {
            $data = [
                'user_id'            => $uid,
                'bulan'              => $bulan,
                'tahun'              => $tahun,
                'total_hari_efektif' => $r['hari_efektif'],
                'total_hadir'        => $r['hadir'],
                'total_tepat_waktu'  => $r['tepat'],
                'total_terlambat'    => $r['terlambat'],
                'total_alpa'         => $r['alpa'],
                'total_izin'         => $r['izin'],
                'total_sakit'        => $r['sakit'],
                'total_cuti'         => $r['cuti'],
                'total_dinas_luar'   => $r['dinas_luar'],
                'total_libur'        => $r['libur'],
                'total_menit_kerja'  => $r['total_menit'],
                'persentase'         => $r['persen'],
                'bintang_rata_rata'  => $r['bintang_bulanan'],
                'generated_at'       => now(),
            ];
            $ada = DB::table('rekap_bulanan')
                ->where(['user_id' => $uid, 'bulan' => $bulan, 'tahun' => $tahun])
                ->count() > 0;
            if ($ada) {
                DB::table('rekap_bulanan')
                    ->where(['user_id' => $uid, 'bulan' => $bulan, 'tahun' => $tahun])
                    ->update($data);
            } else {
                DB::table('rekap_bulanan')->insert($data);
            }
        }
        $blnNama = BULAN_ID[$bulan] ?? $bulan;
        catat_aktivitas('Generate Rekap', $blnNama . ' ' . $tahun . ' — '
            . count($rekapPer) . ' pegawai');

        return redirect('admin/rekap?' . $this->qsDari($f))
            ->with('flash_sukses', 'Rekap ' . $blnNama . " {$tahun} untuk "
                . count($rekapPer) . ' pegawai berhasil disimpan sebagai arsip.');
    }

    public function cetak(Request $request)
    {
        $f       = $this->ambilParam($request);
        $pegawai = $this->daftarPegawai($f);
        $label   = $this->labelFilter($f);
        $blnNama = BULAN_ID[$f['bulan']] ?? $f['bulan'];
        catat_aktivitas('Cetak Laporan', $blnNama . ' ' . $f['tahun'] . ' (' . $label . ')');

        return view('admin.laporan_cetak', [
            'bulan'    => $f['bulan'],
            'tahun'    => $f['tahun'],
            'namaUnit' => $label,
            'pegawai'  => $pegawai,
            'rekapPer' => $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']),
        ]);
    }

    public function excel(Request $request)
    {
        $f    = $this->ambilParam($request);
        [$bulan, $tahun] = [$f['bulan'], $f['tahun']];
        $mode = $request->get('mode') === 'detail' ? 'detail' : 'rekap';

        $blnNama = BULAN_ID[$bulan] ?? $bulan;
        $nama = 'Absensi_' . ucfirst($mode) . '_' . $blnNama . '_' . $tahun . '.xls';

        catat_aktivitas('Export Excel', ucfirst($mode) . ' ' . $blnNama . ' ' . $tahun
            . ' (' . $this->labelFilter($f) . ')');

        if ($mode === 'rekap') {
            $pegawai = $this->daftarPegawai($f);
            $isi = view('admin.excel_rekap', [
                'bulan' => $bulan, 'tahun' => $tahun,
                'pegawai' => $pegawai,
                'rekapPer' => $this->hitungSemua($pegawai, $bulan, $tahun),
            ])->render();
        } else {
            $b = DB::table('absensi as a')
                ->select('a.*', 'u.nama_lengkap', 'uk.nama AS unit_nama', 'su.nama AS sub_nama',
                         's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang')
                ->join('users as u', 'u.id', '=', 'a.user_id')
                ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
                ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
                ->leftJoin('shift as s', 's.id', '=', 'a.shift_id')
                ->whereMonth('a.tanggal', $bulan)->whereYear('a.tanggal', $tahun);
            if ($f['unit']) $b->where('u.unit_kerja_id', $f['unit']);
            if ($f['jab'])  $b->where('u.jabatan_kategori', $f['jab']);
            if ($f['njab']) $b->where('u.jabatan_id', $f['njab']);
            if ($f['org'])  $b->whereIn('u.jabatan_id', app(StrukturService::class)->keturunan($f['org']));
            if ($f['prof']) $b->where('u.profesi_id', $f['prof']);
            $isi = view('admin.excel_detail', [
                'bulan' => $bulan, 'tahun' => $tahun,
                'rows'  => $b->orderBy('a.tanggal')->orderBy('u.nama_lengkap')->get()->all(),
            ])->render();
        }

        return response("\xEF\xBB\xBF" . $isi, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nama . '"',
        ]);
    }
}
