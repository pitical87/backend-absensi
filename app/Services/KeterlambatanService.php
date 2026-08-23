<?php

namespace App\Services;

use App\Models\Keterlambatan;

class KeterlambatanService
{
    public function catatDatang(int $absensiId, int $menitSetelahJadwal): Keterlambatan
    {
        $row = Keterlambatan::firstOrNew(['absensi_id' => $absensiId]);
        $row->menit_telat = max(0, $menitSetelahJadwal);
        $row->bintang_masuk = BintangService::bintangMasuk($menitSetelahJadwal);
        $row->save();

        return $row;
    }

    public function catatPulang(int $absensiId, int $menitLebihAwal): Keterlambatan
    {
        $row = Keterlambatan::firstOrNew(['absensi_id' => $absensiId]);
        $row->menit_awal_pulang = max(0, $menitLebihAwal);
        $row->bintang_pulang = BintangService::bintangPulang($menitLebihAwal);
        $row->total_bintang = round((($row->bintang_masuk ?? 4) + $row->bintang_pulang) / 2, 1, PHP_ROUND_HALF_UP);
        $row->save();

        return $row;
    }
}
