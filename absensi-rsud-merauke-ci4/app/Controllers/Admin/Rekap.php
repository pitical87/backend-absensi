<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\Rekap as RekapLib;
use App\Libraries\Struktur;

class Rekap extends BaseController
{
    /** Membaca & menyucikan seluruh parameter filter laporan. */
    private function ambilParam(): array
    {
        $sumber = $this->request->getMethod() === 'POST' ? 'getPost' : 'getGet';
        $jab    = (string) $this->request->$sumber('jab');

        return [
            'bulan' => min(12, max(1, (int) ($this->request->$sumber('bulan') ?: date('n')))),
            'tahun' => min(2100, max(2024, (int) ($this->request->$sumber('tahun') ?: date('Y')))),
            'unit'  => (int) $this->request->$sumber('unit'),
            'jab'   => in_array($jab, kategori_jabatan_list(), true) ? $jab : '',
            'njab'  => (int) $this->request->$sumber('njab'),
            'org'   => (int) $this->request->$sumber('org'),
            'prof'  => (int) $this->request->$sumber('prof'),
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

    /** Label ringkas filter aktif untuk kop laporan. */
    private function labelFilter(array $f): string
    {
        $bagian = [];
        if ($f['unit']) {
            $u = $this->db->table('unit_kerja')->where('id', $f['unit'])->get()->getRowArray();
            if ($u) $bagian[] = 'Unit ' . $u['nama'];
        }
        if ($f['jab']) $bagian[] = 'Jabatan ' . $f['jab'];
        if ($f['njab']) {
            $j = $this->db->table('jabatan')->where('id', $f['njab'])->get()->getRowArray();
            if ($j) $bagian[] = $j['nama'];
        }
        if ($f['org']) {
            $j = $this->db->table('jabatan')->where('id', $f['org'])->get()->getRowArray();
            if ($j) $bagian[] = $j['unit_label'] ?: $j['nama'];
        }
        if ($f['prof']) {
            $p = $this->db->table('profesi')->where('id', $f['prof'])->get()->getRowArray();
            if ($p) $bagian[] = 'Profesi ' . $p['nama'];
        }
        return $bagian ? implode(' · ', $bagian) : 'Seluruh Pegawai';
    }

    private function daftarPegawai(array $f): array
    {
        $b = $this->db->table('users as u')
            ->select('u.id, u.nama_lengkap, u.nip, u.jabatan_kategori,
                      uk.nama AS unit_nama, su.nama AS sub_nama, p.nama AS profesi_nama,
                      j.nama AS jabatan_nama')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->join('jabatan as j', 'j.id = u.jabatan_id', 'left')
            ->where('u.role', 'pegawai')->where('u.status', 'aktif');

        if ($f['unit']) $b->where('u.unit_kerja_id', $f['unit']);
        if ($f['jab'])  $b->where('u.jabatan_kategori', $f['jab']);
        if ($f['njab']) $b->where('u.jabatan_id', $f['njab']);
        if ($f['org'])  $b->whereIn('u.jabatan_id', (new Struktur())->keturunan($f['org']));
        if ($f['prof']) $b->where('u.profesi_id', $f['prof']);

        return $b->orderBy('uk.id')->orderBy('u.nama_lengkap')->get()->getResultArray();
    }

    private function hitungSemua(array $pegawai, int $bulan, int $tahun): array
    {
        $lib = new RekapLib();
        $per = [];
        foreach ($pegawai as $p) {
            $per[(int) $p['id']] = $lib->hitung((int) $p['id'], $bulan, $tahun);
        }
        return $per;
    }

    public function index()
    {
        $f       = $this->ambilParam();
        $pegawai = $this->daftarPegawai($f);
        $lib     = new Struktur();

        return view('admin/rekap', [
            'judulHalaman' => 'Rekap & Laporan',
            'menuAktif'    => 'rekap',
            'f'            => $f,
            'bulan'        => $f['bulan'],
            'tahun'        => $f['tahun'],
            'fUnit'        => $f['unit'],
            'pegawai'      => $pegawai,
            'rekapPer'     => $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']),
            'unitList'     => $this->db->table('unit_kerja')->orderBy('id')->get()->getResultArray(),
            'profList'     => $this->db->table('profesi')->orderBy('id')->get()->getResultArray(),
            'jabPilihan'   => $lib->pilihan(),
            'orgList'      => $lib->unitOrganisasi(),
            'kategoriJab'  => kategori_jabatan_list(),
            'qs'           => $this->qsDari($f),
        ]);
    }

    public function generate()
    {
        $f        = $this->ambilParam();
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
                'generated_at'       => date('Y-m-d H:i:s'),
            ];
            $ada = $this->db->table('rekap_bulanan')
                ->where(['user_id' => $uid, 'bulan' => $bulan, 'tahun' => $tahun])
                ->countAllResults() > 0;
            if ($ada) {
                $this->db->table('rekap_bulanan')
                    ->where(['user_id' => $uid, 'bulan' => $bulan, 'tahun' => $tahun])
                    ->update($data);
            } else {
                $this->db->table('rekap_bulanan')->insert($data);
            }
        }
        catat_aktivitas('Generate Rekap', BULAN_ID[$bulan] . ' ' . $tahun . ' — '
            . count($rekapPer) . ' pegawai');

        return redirect()->to('admin/rekap?' . $this->qsDari($f))
            ->with('flash_sukses', 'Rekap ' . BULAN_ID[$bulan] . " {$tahun} untuk "
                . count($rekapPer) . ' pegawai berhasil disimpan sebagai arsip.');
    }

    public function cetak()
    {
        $f       = $this->ambilParam();
        $pegawai = $this->daftarPegawai($f);
        $label   = $this->labelFilter($f);
        catat_aktivitas('Cetak Laporan', BULAN_ID[$f['bulan']] . ' ' . $f['tahun'] . ' (' . $label . ')');

        return view('admin/laporan_cetak', [
            'bulan'    => $f['bulan'],
            'tahun'    => $f['tahun'],
            'namaUnit' => $label,
            'pegawai'  => $pegawai,
            'rekapPer' => $this->hitungSemua($pegawai, $f['bulan'], $f['tahun']),
        ]);
    }

    public function excel()
    {
        $f    = $this->ambilParam();
        [$bulan, $tahun] = [$f['bulan'], $f['tahun']];
        $mode = $this->request->getGet('mode') === 'detail' ? 'detail' : 'rekap';

        $nama = 'Absensi_' . ucfirst($mode) . '_' . BULAN_ID[$bulan] . '_' . $tahun . '.xls';
        $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nama . '"');

        catat_aktivitas('Export Excel', ucfirst($mode) . ' ' . BULAN_ID[$bulan] . ' ' . $tahun
            . ' (' . $this->labelFilter($f) . ')');

        if ($mode === 'rekap') {
            $pegawai = $this->daftarPegawai($f);
            $isi = view('admin/excel_rekap', [
                'bulan' => $bulan, 'tahun' => $tahun,
                'pegawai' => $pegawai,
                'rekapPer' => $this->hitungSemua($pegawai, $bulan, $tahun),
            ]);
        } else {
            $b = $this->db->table('absensi as a')
                ->select('a.*, u.nama_lengkap, uk.nama AS unit_nama, su.nama AS sub_nama,
                          s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang')
                ->join('users as u', 'u.id = a.user_id')
                ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
                ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
                ->join('shift as s', 's.id = a.shift_id', 'left')
                ->where('MONTH(a.tanggal)', $bulan)->where('YEAR(a.tanggal)', $tahun);
            if ($f['unit']) $b->where('u.unit_kerja_id', $f['unit']);
            if ($f['jab'])  $b->where('u.jabatan_kategori', $f['jab']);
            if ($f['njab']) $b->where('u.jabatan_id', $f['njab']);
            if ($f['org'])  $b->whereIn('u.jabatan_id', (new Struktur())->keturunan($f['org']));
            if ($f['prof']) $b->where('u.profesi_id', $f['prof']);
            $isi = view('admin/excel_detail', [
                'bulan' => $bulan, 'tahun' => $tahun,
                'rows'  => $b->orderBy('a.tanggal')->orderBy('u.nama_lengkap')->get()->getResultArray(),
            ]);
        }

        return $this->response->setBody("\xEF\xBB\xBF" . $isi);
    }
}
