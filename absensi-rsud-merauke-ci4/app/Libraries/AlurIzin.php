<?php

namespace App\Libraries;

use Config\Database;

/**
 * AlurIzin — mesin alur persetujuan berjenjang untuk pengajuan Izin & Cuti.
 *
 * Urutan posisi: Staf(0) → Koordinator/Kepala Unit(1) → Kepala Seksi/Sub Bagian(2)
 *   → Kepala Bidang/Bagian(3) → HRD(4) → Direktur(5, hanya penanda tangan, bukan tahap).
 * Tahap yang harus dilalui sebuah pengajuan = (posisi_index(pemohon)+1) .. 4.
 * Tahap tanpa pejabat yang dapat ditentukan otomatis ditandai "Dilewati" agar alur
 * tidak pernah macet — kecuali tahap HRD, yang selalu dibuat (admin dapat mengambil
 * alih bila belum ada akun berposisi HRD).
 */
class AlurIzin
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** Tahap-tahap (1..4) yang wajib dilalui oleh pemohon dengan posisi tertentu. */
    public function tahapUntuk(string $posisiPemohon): array
    {
        $mulai = posisi_index($posisiPemohon) + 1;
        $semua = [1, 2, 3, 4];
        return array_values(array_filter($semua, static fn ($t) => $t >= $mulai));
    }

    /**
     * Mencari user aktif yang berwenang menyetujui tahap tertentu untuk pemohon ini.
     * Mengembalikan array user (lengkap dgn kolom users) atau null bila tak ada.
     */
    public function pejabatTahap(int $tahap, array $pemohon): ?array
    {
        $u = $this->db->table('users as u')->select('u.*');

        if ($tahap === 1) { // Koordinator/Kepala Unit/Ruang/Instalasi — tempat kerja sama
            $u->where('u.posisi', 'Koordinator/Kepala Unit/Ruang/Instalasi')
              ->where('u.status', 'aktif')->where('u.id !=', $pemohon['id'])
              ->where('u.unit_kerja_id', $pemohon['unit_kerja_id']);
            if (! empty($pemohon['sub_unit_id'])) {
                $u->where('u.sub_unit_id', $pemohon['sub_unit_id']);
            }
        } elseif ($tahap === 2) { // Kepala Seksi/Sub Bagian pembina pemohon
            if (empty($pemohon['seksi_pembina_id'])) {
                return null;
            }
            $u->where('u.posisi', 'Kepala Seksi/Sub Bagian')->where('u.status', 'aktif')
              ->where('u.jabatan_id', $pemohon['seksi_pembina_id']);
        } elseif ($tahap === 3) { // Kepala Bidang/Bagian — induk dari seksi pembina / jabatan sendiri
            $seksiId = posisi_index($pemohon['posisi']) >= 2 && ! empty($pemohon['jabatan_id'])
                ? (int) $pemohon['jabatan_id']
                : (int) ($pemohon['seksi_pembina_id'] ?? 0);
            if (! $seksiId) {
                return null;
            }
            $seksi = $this->db->table('jabatan')->where('id', $seksiId)->get()->getRowArray();
            if (! $seksi || ! $seksi['induk_id']) {
                return null;
            }
            $u->where('u.posisi', 'Kepala Bidang/Bagian')->where('u.status', 'aktif')
              ->where('u.jabatan_id', (int) $seksi['induk_id']);
        } elseif ($tahap === 4) { // HRD
            $u->where('u.posisi', 'HRD')->where('u.status', 'aktif');
        } else {
            return null;
        }

        return $u->get(1)->getRowArray();
    }

    /**
     * Membuat seluruh baris izin_persetujuan untuk pengajuan baru, menandai tahap
     * tanpa pejabat sebagai "Dilewati", dan mengembalikan [tahap_aktif, status_awal].
     * status_awal = 'Disetujui' bila seluruh tahap dilewati/tak diperlukan (pemohon
     * sendiri sudah di puncak alur, mis. HRD atau Direktur mengajukan izin).
     */
    public function mulai(int $pengajuanId, array $pemohon): array
    {
        $tahapList = $this->tahapUntuk($pemohon['posisi']);
        $tahapAktif = 0;

        foreach ($tahapList as $t) {
            $pejabat = $this->pejabatTahap($t, $pemohon);
            if ($pejabat) {
                $this->db->table('izin_persetujuan')->insert([
                    'pengajuan_id' => $pengajuanId, 'tahap' => $t,
                    'posisi_tahap' => label_tahap_izin($t), 'status' => 'Menunggu',
                ]);
                if (! $tahapAktif) $tahapAktif = $t;
            } elseif ($t === 4) {
                // HRD selalu dibuat meski belum ada akun HRD — admin dapat mengambil alih
                $this->db->table('izin_persetujuan')->insert([
                    'pengajuan_id' => $pengajuanId, 'tahap' => 4,
                    'posisi_tahap' => 'HRD', 'status' => 'Menunggu',
                ]);
                if (! $tahapAktif) $tahapAktif = 4;
            } else {
                $this->db->table('izin_persetujuan')->insert([
                    'pengajuan_id' => $pengajuanId, 'tahap' => $t,
                    'posisi_tahap' => label_tahap_izin($t), 'status' => 'Dilewati',
                    'catatan' => 'Tidak ada pejabat terdaftar untuk tahap ini — dilewati otomatis.',
                    'waktu' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $tahapAktif
            ? [$tahapAktif, 'Menunggu']
            : [0, 'Disetujui']; // tak ada tahap sama sekali (pemohon HRD/Direktur sendiri)
    }

    /** Apakah $user berwenang bertindak pada tahap_aktif pengajuan ini? */
    public function bolehBertindak(array $pengajuan, array $pemohon, array $user): bool
    {
        if ((int) $pengajuan['tahap_aktif'] === 0) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true; // admin selalu dapat mengambil alih tahap manapun
        }
        $pejabat = $this->pejabatTahap((int) $pengajuan['tahap_aktif'], $pemohon);
        return $pejabat && (int) $pejabat['id'] === (int) $user['id'];
    }

    /**
     * Memproses putusan pada tahap aktif. Mengembalikan status akhir pengajuan
     * setelah diperbarui: 'Menunggu' (lanjut ke tahap berikut), 'Disetujui', atau 'Ditolak'.
     */
    public function proses(array $pengajuan, array $pemohon, int $olehUserId, string $putusan, ?string $catatan): string
    {
        $tahap = (int) $pengajuan['tahap_aktif'];
        $baru  = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';

        $this->db->table('izin_persetujuan')
            ->where('pengajuan_id', $pengajuan['id'])->where('tahap', $tahap)
            ->update(['status' => $baru, 'oleh_user_id' => $olehUserId,
                      'catatan' => $catatan, 'waktu' => date('Y-m-d H:i:s')]);

        if ($baru === 'Ditolak') {
            // Tahap-tahap sisanya ditandai Dilewati (pengajuan sudah final ditolak)
            $this->db->table('izin_persetujuan')
                ->where('pengajuan_id', $pengajuan['id'])->where('status', 'Menunggu')
                ->update(['status' => 'Dilewati', 'catatan' => 'Pengajuan telah ditolak pada tahap sebelumnya.',
                          'waktu' => date('Y-m-d H:i:s')]);
            $this->db->table('pengajuan_izin')->where('id', $pengajuan['id'])
                ->update(['status' => 'Ditolak', 'processed_at' => date('Y-m-d H:i:s')]);
            return 'Ditolak';
        }

        // Cari tahap berikutnya yang masih Menunggu
        $sisaTahap = array_filter($this->tahapUntuk($pemohon['posisi']), static fn ($t) => $t > $tahap);
        $tahapBerikut = 0;
        foreach ($sisaTahap as $t) {
            $baris = $this->db->table('izin_persetujuan')
                ->where('pengajuan_id', $pengajuan['id'])->where('tahap', $t)
                ->get()->getRowArray();
            if ($baris && $baris['status'] === 'Menunggu') {
                $tahapBerikut = $t;
                break;
            }
        }

        if ($tahapBerikut) {
            $this->db->table('pengajuan_izin')->where('id', $pengajuan['id'])
                ->update(['tahap_aktif' => $tahapBerikut]);
            return 'Menunggu';
        }

        // Tidak ada tahap berikut — pengajuan selesai & disetujui penuh
        $nomor = $this->buatNomorSurat();
        $kode  = strtoupper(bin2hex(random_bytes(5)));
        $this->db->table('pengajuan_izin')->where('id', $pengajuan['id'])->update([
            'status' => 'Disetujui', 'tahap_aktif' => 0, 'processed_at' => date('Y-m-d H:i:s'),
            'nomor_surat' => $nomor, 'kode_verifikasi' => $kode,
        ]);
        return 'Disetujui';
    }

    /** Nomor surat berurutan per bulan: 800/NNN/RSUD-MRK/MM/YYYY. */
    private function buatNomorSurat(): string
    {
        $bulan = date('n'); $tahun = date('Y');
        $jml = $this->db->table('pengajuan_izin')
            ->where('MONTH(processed_at)', $bulan)->where('YEAR(processed_at)', $tahun)
            ->where('nomor_surat IS NOT NULL')->countAllResults();
        return sprintf('800/%03d/RSUD-MRK/%02d/%d', $jml + 1, $bulan, $tahun);
    }
}
