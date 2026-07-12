<?php

namespace App\Libraries;

use Config\Database;

/**
 * CutiLib — rekap Hak Cuti Tahunan pegawai PNS.
 *
 * Hak cuti = 12 hari kerja/tahun. Yang memotong jatah: pengajuan berjenis Izin,
 * atau Cuti dengan jenis_cuti = 'Cuti Tahunan', yang berstatus Disetujui penuh
 * (lolos seluruh tahap) pada tahun berjalan. Jenis cuti lain (Sakit, Melahirkan,
 * Alasan Penting, Besar, di Luar Tanggungan Negara) memiliki hak tersendiri di
 * luar cakupan modul ini dan TIDAK memotong jatah 12 hari — ini dicatat sebagai
 * batasan yang jujur pada README, bukan disamaratakan.
 */
class CutiLib
{
    public const HAK_TAHUNAN = 12;

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function rekap(int $userId, int $tahun): array
    {
        $rows = $this->db->table('pengajuan_izin')
            ->select('tanggal_mulai, tanggal_selesai, lama_hari, jenis, jenis_cuti')
            ->where('user_id', $userId)->where('status', 'Disetujui')
            ->where('YEAR(tanggal_mulai)', $tahun)
            ->groupStart()
                ->where('jenis', 'Izin')
                ->orGroupStart()
                    ->where('jenis', 'Cuti')->where('jenis_cuti', 'Cuti Tahunan')
                ->groupEnd()
            ->groupEnd()
            ->get()->getResultArray();

        $terpakai = 0;
        foreach ($rows as $r) {
            $terpakai += (int) ($r['lama_hari'] ?? 0);
        }

        $sisa = max(0, self::HAK_TAHUNAN - $terpakai);

        return [
            'hak'      => self::HAK_TAHUNAN,
            'terpakai' => $terpakai,
            'sisa'     => $sisa,
            'rincian'  => $rows,
        ];
    }
}
