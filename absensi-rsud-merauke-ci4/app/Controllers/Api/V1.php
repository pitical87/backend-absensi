<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Rekap;

/**
 * API v1 — antarmuka data untuk integrasi SIMRS.
 * Seluruh endpoint dilindungi header X-API-KEY (lihat menu admin Pengaturan).
 *
 * GET /api/v1/ping
 * GET /api/v1/pegawai
 * GET /api/v1/absensi?dari=YYYY-MM-DD&sampai=YYYY-MM-DD[&user_id=N]
 * GET /api/v1/rekap?bulan=N&tahun=NNNN
 * GET /api/v1/izin?status=Disetujui&dari=YYYY-MM-DD&sampai=YYYY-MM-DD
 */
class V1 extends BaseController
{
    public function ping()
    {
        return $this->json([
            'sukses'   => true,
            'aplikasi' => 'Sistem Absensi Pegawai RSUD Merauke',
            'versi'    => '2.1',
            'waktu'    => date('c'),
        ]);
    }

    public function pegawai()
    {
        $data = $this->db->table('users as u')
            ->select('u.id, u.nama_lengkap, u.nip, u.email, u.no_hp, u.jenis_kelamin, u.status, u.role,
                      u.jabatan_kategori, j.nama AS jabatan,
                      uk.nama AS unit_kerja, su.nama AS sub_unit, p.nama AS profesi,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_jam_masuk,
                      s.jam_pulang AS shift_jam_pulang')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->join('shift as s', 's.id = u.shift_id', 'left')
            ->join('jabatan as j', 'j.id = u.jabatan_id', 'left')
            ->where('u.role', 'pegawai')
            ->orderBy('u.nama_lengkap')->get()->getResultArray();

        return $this->json(['sukses' => true, 'jumlah' => count($data), 'data' => $data]);
    }
    public function getPegawai($id){
        $data = $this->db->table('users as u')
             ->select('u.id, u.nama_lengkap, u.nip, u.email, u.no_hp, 
                  u.jenis_kelamin, u.status, u.role,
                  u.jabatan_kategori, j.nama AS jabatan,
                  uk.nama AS unit_kerja, su.nama AS sub_unit, p.nama AS profesi,
                  s.kategori AS shift_kategori, s.jam_masuk AS shift_jam_masuk,
                  s.jam_pulang AS shift_jam_pulang')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->join('shift as s', 's.id = u.shift_id', 'left')
            ->join('jabatan as j', 'j.id = u.jabatan_id', 'left')
            ->where('u.role', 'pegawai')
            ->where('u.id', $id)
            ->get()->getRowArray();

        if (! $data) {
            return $this->json(['sukses' => false, 'pesan' => 'Pegawai tidak ditemukan.'], 404);
        }
        return $this->json(['sukses' => true, 'data' => $data]);
    }

    public function absensi()
    {
        $dari   = $this->tanggalValid($this->request->getGet('dari'), date('Y-m-d'));
        $sampai = $this->tanggalValid($this->request->getGet('sampai'), $dari);
        $userId = (int) $this->request->getGet('user_id');

        if ($sampai < $dari) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        if ((strtotime($sampai) - strtotime($dari)) / 86400 > 92) {
            return $this->json(['sukses' => false,
                'pesan' => 'Rentang maksimal 92 hari per permintaan.'], 422);
        }

        $b = $this->db->table('absensi as a')
            ->select('a.id, a.user_id, u.nama_lengkap, a.tanggal, a.waktu_masuk, a.waktu_pulang,
                      a.status_masuk, a.menit_terlambat, a.total_menit_kerja,
                      a.lat_masuk, a.lng_masuk, a.lat_pulang, a.lng_pulang,
                      a.flag_anomali, a.catatan_anomali,
                      s.kategori AS shift_kategori, s.jam_masuk AS shift_jam_masuk,
                      s.jam_pulang AS shift_jam_pulang')
            ->join('users as u', 'u.id = a.user_id')
            ->join('shift as s', 's.id = a.shift_id', 'left')
            ->where('a.tanggal >=', $dari)->where('a.tanggal <=', $sampai);
        if ($userId) {
            $b->where('a.user_id', $userId);
        }
        $data = $b->orderBy('a.tanggal')->orderBy('u.nama_lengkap')->get()->getResultArray();

        return $this->json([
            'sukses' => true,
            'dari'   => $dari,
            'sampai' => $sampai,
            'jumlah' => count($data),
            'data'   => $data,
        ]);
    }

    public function rekap()
    {
        $bulan = min(12, max(1, (int) ($this->request->getGet('bulan') ?: date('n'))));
        $tahun = min(2100, max(2024, (int) ($this->request->getGet('tahun') ?: date('Y'))));

        $pegawai = $this->db->table('users as u')
            ->select('u.id, u.nama_lengkap, uk.nama AS unit_kerja, su.nama AS sub_unit,
                      p.nama AS profesi')
            ->join('unit_kerja as uk', 'uk.id = u.unit_kerja_id', 'left')
            ->join('sub_unit as su', 'su.id = u.sub_unit_id', 'left')
            ->join('profesi as p', 'p.id = u.profesi_id', 'left')
            ->where('u.role', 'pegawai')->where('u.status', 'aktif')
            ->orderBy('u.nama_lengkap')->get()->getResultArray();

        $lib  = new Rekap();
        $data = [];
        foreach ($pegawai as $p) {
            $r = $lib->hitung((int) $p['id'], $bulan, $tahun);
            $data[] = $p + [
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

        return $this->json([
            'sukses' => true,
            'bulan'  => $bulan,
            'tahun'  => $tahun,
            'jumlah' => count($data),
            'data'   => $data,
        ]);
    }

    public function izin()
    {
        $status = (string) ($this->request->getGet('status') ?: 'Disetujui');
        if (! in_array($status, ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'], true)) {
            $status = 'Disetujui';
        }
        $dari   = $this->tanggalValid($this->request->getGet('dari'), date('Y-m-01'));
        $sampai = $this->tanggalValid($this->request->getGet('sampai'), date('Y-m-t'));

        $b = $this->db->table('pengajuan_izin i')
            ->select('i.id, i.user_id, u.nama_lengkap, i.jenis, i.tanggal_mulai, i.tanggal_selesai,
                      i.keterangan, i.status, i.catatan_admin, i.created_at, i.processed_at')
            ->join('users as u', 'u.id = i.user_id')
            ->where('i.tanggal_mulai <=', $sampai)
            ->where('i.tanggal_selesai >=', $dari);
        if ($status !== 'Semua') {
            $b->where('i.status', $status);
        }
        $data = $b->orderBy('i.tanggal_mulai')->get()->getResultArray();

        return $this->json([
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
