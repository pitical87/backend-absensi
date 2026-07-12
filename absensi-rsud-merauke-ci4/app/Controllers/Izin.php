<?php

namespace App\Controllers;

use App\Libraries\AlurIzin;
use App\Libraries\CutiLib;

/**
 * Izin — pengajuan Izin / Sakit / Cuti / Dinas Luar oleh pegawai.
 *
 * Izin & Cuti melalui alur persetujuan berjenjang (AlurIzin) sesuai posisi pemohon.
 * Sakit & Dinas Luar tetap memakai persetujuan satu tahap oleh admin (tak berubah).
 */
class Izin extends BaseController
{
    private const JENIS = ['Izin', 'Sakit', 'Cuti', 'Dinas Luar'];
    private const BERJENJANG = ['Izin', 'Cuti'];

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }

        $riwayat = $this->db->table('pengajuan_izin i')
            ->select('i.*, a.nama_lengkap AS admin_nama')
            ->join('users a', 'a.id = i.diproses_oleh', 'left')
            ->where('i.user_id', $u['id'])
            ->orderBy('i.id', 'DESC')->limit(50)
            ->get()->getResultArray();

        // Jejak tahap per pengajuan berjenjang (untuk ditampilkan sebagai progres)
        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($riwayat,
            static fn ($r) => in_array($r['jenis'], self::BERJENJANG, true)), 'id');
        if ($idBerjenjang) {
            foreach ($this->db->table('izin_persetujuan p')
                         ->select('p.*, o.nama_lengkap AS oleh_nama')
                         ->join('users o', 'o.id = p.oleh_user_id', 'left')
                         ->whereIn('pengajuan_id', $idBerjenjang)
                         ->orderBy('tahap')->get()->getResultArray() as $p) {
                $tahapPer[(int) $p['pengajuan_id']][] = $p;
            }
        }

        $cuti = is_pns($u) ? (new CutiLib())->rekap($u['id'], (int) date('Y')) : null;

        return view('pegawai/izin', [
            'u' => $u, 'riwayat' => $riwayat, 'jenisList' => self::JENIS,
            'jenisCutiList' => jenis_cuti_list(), 'tahapPer' => $tahapPer, 'cuti' => $cuti,
        ]);
    }

    public function ajukan()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }

        $jenis     = (string) $this->request->getPost('jenis');
        $jenisCuti = trim((string) $this->request->getPost('jenis_cuti')) ?: null;
        $mulai     = (string) $this->request->getPost('tanggal_mulai');
        $selesai   = (string) $this->request->getPost('tanggal_selesai') ?: $mulai;
        $alamat    = trim((string) $this->request->getPost('alamat_izin')) ?: null;
        $ket       = trim((string) $this->request->getPost('keterangan'));
        $berjenjang = in_array($jenis, self::BERJENJANG, true);

        $galat = [];
        if (! in_array($jenis, self::JENIS, true)) $galat[] = 'Jenis pengajuan tidak valid.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $mulai)) $galat[] = 'Tanggal mulai wajib diisi.';
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $selesai)) $selesai = $mulai;
        if ($selesai < $mulai) $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        if ($ket === '') $galat[] = 'Alasan/keperluan wajib diisi.';
        if (! $galat && (strtotime($selesai) - strtotime($mulai)) / 86400 > 60) {
            $galat[] = 'Rentang pengajuan maksimal 60 hari.';
        }

        if ($jenis === 'Cuti') {
            if (! is_pns($u)) {
                $galat[] = 'Cuti hanya dapat diajukan oleh pegawai berstatus PNS. '
                    . 'Gunakan jenis "Izin" untuk keperluan non-cuti.';
            }
            if (! in_array($jenisCuti, jenis_cuti_list(), true)) {
                $galat[] = 'Jenis cuti wajib dipilih.';
            }
            if ($alamat === null) {
                $galat[] = 'Alamat selama cuti wajib diisi.';
            }
        }
        if ($jenis === 'Izin' && $alamat === null) {
            $galat[] = 'Alamat selama izin wajib diisi.';
        }

        // Cegah tumpang-tindih dengan pengajuan Menunggu/Disetujui lain
        if (! $galat) {
            $tindih = $this->db->table('pengajuan_izin')
                ->where('user_id', $u['id'])->whereIn('status', ['Menunggu', 'Disetujui'])
                ->where('tanggal_mulai <=', $selesai)->where('tanggal_selesai >=', $mulai)
                ->countAllResults() > 0;
            if ($tindih) {
                $galat[] = 'Rentang tanggal tersebut bertumpang-tindih dengan pengajuan lain yang masih Menunggu/Disetujui.';
            }
        }

        // Kuota cuti tahunan: Izin & Cuti Tahunan memotong 12 hari kerja/tahun
        $lamaHari = null;
        if (! $galat && $berjenjang) {
            pastikan_libur_tetap((int) date('Y', strtotime($mulai)));
            pastikan_libur_tetap((int) date('Y', strtotime($selesai)));
            $liburSet = [];
            foreach ($this->db->table('hari_libur')->get()->getResultArray() as $h) {
                $liburSet[$h['tanggal']] = true;
            }
            $mingguLibur = pengaturan('minggu_libur', '0') === '1';
            $lamaHari = hari_kerja_antara($mulai, $selesai, $liburSet, $mingguLibur);
            if ($lamaHari < 1) $lamaHari = 1;

            $motongKuota = $jenis === 'Izin' || ($jenis === 'Cuti' && $jenisCuti === 'Cuti Tahunan');
            if ($motongKuota && is_pns($u)) {
                $sisa = (new CutiLib())->rekap($u['id'], (int) date('Y', strtotime($mulai)))['sisa'];
                if ($lamaHari > $sisa) {
                    $galat[] = "Sisa hak cuti tahun ini hanya {$sisa} hari kerja, "
                        . "sedangkan pengajuan ini memerlukan {$lamaHari} hari kerja.";
                }
            }
        }

        // Lampiran opsional (foto surat sakit / surat tugas): jpg/png/pdf maks 3 MB
        $lampiran = null;
        $berkas   = $this->request->getFile('lampiran');
        if ($berkas && $berkas->isValid()) {
            $eks = strtolower($berkas->getClientExtension() ?: '');
            if (! in_array($eks, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                $galat[] = 'Lampiran hanya boleh berupa JPG, PNG, atau PDF.';
            } elseif ($berkas->getSize() > 3 * 1024 * 1024) {
                $galat[] = 'Ukuran lampiran maksimal 3 MB.';
            } else {
                $dir = WRITEPATH . 'uploads/izin/' . date('Ym');
                if (! is_dir($dir)) mkdir($dir, 0775, true);
                $nama = $u['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . ($eks === 'jpeg' ? 'jpg' : $eks);
                $berkas->move($dir, $nama);
                $lampiran = 'izin/' . date('Ym') . '/' . $nama;
            }
        }

        if ($galat) {
            return redirect()->to('izin')->with('flash_gagal', implode(' ', $galat));
        }

        $data = [
            'user_id'         => $u['id'],
            'jenis'           => $jenis,
            'jenis_cuti'      => $jenis === 'Cuti' ? $jenisCuti : null,
            'tanggal_mulai'   => $mulai,
            'tanggal_selesai' => $selesai,
            'lama_hari'       => $lamaHari,
            'alamat_izin'     => $alamat,
            'keterangan'      => $ket,
            'lampiran'        => $lampiran,
            'status'          => 'Menunggu',
            'tahap_aktif'     => 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ];

        if ($berjenjang) {
            $this->db->transStart();
            $this->db->table('pengajuan_izin')->insert($data);
            $id = (int) $this->db->insertID();
            [$tahapAktif, $statusAwal] = (new AlurIzin())->mulai($id, $u);
            $update = ['tahap_aktif' => $tahapAktif, 'status' => $statusAwal];
            if ($statusAwal === 'Disetujui') {
                $update['processed_at']  = date('Y-m-d H:i:s');
                $update['nomor_surat']   = sprintf('800/%03d/RSUD-MRK/%02d/%d',
                    $this->db->table('pengajuan_izin')->where('nomor_surat IS NOT NULL')
                        ->where('MONTH(created_at)', date('n'))->where('YEAR(created_at)', date('Y'))
                        ->countAllResults() + 1, date('n'), date('Y'));
                $update['kode_verifikasi'] = strtoupper(bin2hex(random_bytes(5)));
            }
            $this->db->table('pengajuan_izin')->where('id', $id)->update($update);
            $this->db->transComplete();
            catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' — ' . ($jenisCuti ?: $jenis)
                . ' (' . $mulai . ' s.d. ' . $selesai . ", {$lamaHari} hr kerja)");
            $pesan = $statusAwal === 'Disetujui'
                ? "Pengajuan {$jenis} langsung disetujui (posisi Anda berada di puncak alur persetujuan)."
                : 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan '
                    . label_tahap_izin($tahapAktif) . '.';
        } else {
            $this->db->table('pengajuan_izin')->insert($data);
            catat_aktivitas('Pengajuan ' . $jenis, $u['nama_lengkap'] . ' mengajukan ' . $jenis
                . ' (' . $mulai . ' s.d. ' . $selesai . ')');
            $pesan = 'Pengajuan ' . $jenis . ' terkirim dan menunggu persetujuan admin.';
        }

        return redirect()->to('izin')->with('flash_sukses', $pesan);
    }

    public function batal(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }
        $baris = $this->db->table('pengajuan_izin')
            ->where('id', $id)->where('user_id', $u['id'])->where('status', 'Menunggu')
            ->get()->getRowArray();
        if ($baris) {
            $this->db->table('pengajuan_izin')->where('id', $id)->delete();
            return redirect()->to('izin')->with('flash_sukses', 'Pengajuan dibatalkan.');
        }
        return redirect()->to('izin')
            ->with('flash_gagal', 'Pengajuan tidak ditemukan atau sudah diproses.');
    }

    /** Cetak dua berkas (Formulir Permohonan + Surat Keterangan) — tersedia setelah Disetujui. */
    public function dokumen(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect()->to('login');
        }
        $b = $this->db->table('pengajuan_izin')->where('id', $id)->where('status', 'Disetujui');
        $bolehLihatSemua = $u['posisi'] === 'Direktur' || $u['role'] === 'admin' || $u['posisi'] === 'HRD';
        if (! $bolehLihatSemua) {
            $b->where('user_id', $u['id']);
        }
        $iz = $b->get()->getRowArray();
        if (! $iz) {
            return redirect()->to('izin')->with('flash_gagal', 'Dokumen belum tersedia untuk pengajuan ini.');
        }
        // Pemilik dokumen (untuk kop surat) bila yang membuka bukan pemohon sendiri
        $pemilik = $iz['user_id'] === $u['id'] ? $u
            : $this->db->table('users')->where('id', $iz['user_id'])->get()->getRowArray();

        $tahap = $this->db->table('izin_persetujuan p')
            ->select('p.*, o.nama_lengkap AS oleh_nama')
            ->join('users o', 'o.id = p.oleh_user_id', 'left')
            ->where('pengajuan_id', $id)->orderBy('tahap')->get()->getResultArray();
        $ttdOleh = $iz['ttd_oleh']
            ? $this->db->table('users')->where('id', $iz['ttd_oleh'])->get()->getRowArray()
            : null;
        $bolehTtd = $u['posisi'] === 'Direktur' || $u['role'] === 'admin';

        return view('pegawai/dokumen_izin', [
            'iz' => $iz, 'u' => $pemilik, 'tahap' => $tahap, 'ttdOleh' => $ttdOleh,
            'bolehTtd' => $bolehTtd,
        ]);
    }

    /** Tanda tangan digital oleh Direktur (atau admin) pada dokumen yang sudah Disetujui penuh. */
    public function tandaTangan(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u || ($u['posisi'] !== 'Direktur' && $u['role'] !== 'admin')) {
            return redirect()->to('dashboard')
                ->with('flash_gagal', 'Hanya Direktur yang dapat membubuhkan tanda tangan digital.');
        }

        $iz = $this->db->table('pengajuan_izin')->where('id', $id)->where('status', 'Disetujui')
            ->get()->getRowArray();
        if (! $iz) {
            return redirect()->back()->with('flash_gagal', 'Pengajuan belum disetujui penuh / tidak ditemukan.');
        }

        $this->db->table('pengajuan_izin')->where('id', $id)->update([
            'ttd_digital' => 1, 'ttd_oleh' => $u['id'], 'ttd_waktu' => date('Y-m-d H:i:s'),
        ]);
        catat_aktivitas('Tanda Tangan Digital', 'Dokumen izin/cuti #' . $id . ' oleh ' . $u['nama_lengkap']);

        return redirect()->back()->with('flash_sukses', 'Dokumen telah ditandatangani secara elektronik.');
    }
}
