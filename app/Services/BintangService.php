<?php

namespace App\Services;

use DateTime;

class BintangService
{
    public const MAKS = 5;

    public function bintangMasuk(int $menitSetelahJadwal): int
    {
        return match (true) {
            $menitSetelahJadwal <= 0 => 5,
            $menitSetelahJadwal <= 5 => 4,
            $menitSetelahJadwal <= 10 => 3,
            $menitSetelahJadwal <= 15 => 2,
            $menitSetelahJadwal <= 30 => 1,
            default => 0,
        };
    }

    public function bintangPulang(int $menitLebihAwal): int
    {
        return match (true) {
            $menitLebihAwal <= 0 => 5,
            $menitLebihAwal <= 5 => 4,
            $menitLebihAwal <= 10 => 3,
            $menitLebihAwal <= 15 => 2,
            $menitLebihAwal <= 30 => 1,
            default => 0,
        };
    }

    public function bintangHarian(int $bintangMasuk, int $bintangPulang): float
    {
        return round(($bintangMasuk + $bintangPulang) / 2, 0, PHP_ROUND_HALF_UP);
    }

    public function pesanBulanan(float $rataRata): string
    {
        $bulat = (int) round($rataRata, 0, PHP_ROUND_HALF_UP);
        return match (true) {
            $bulat >= 5 => 'Selamat, kamu luar biasa, pertahankan!',
            $bulat >= 4 => 'Ayo semangat, tingkatkan lagi!',
            default => 'Tingkatkan semangatmu!!!',
        };
    }

    public function simbol(int $bintang): string
    {
        $bintang = max(0, min(self::MAKS, $bintang));
        return str_repeat('★', $bintang) . str_repeat('☆', self::MAKS - $bintang);
    }

    public function selisihMenitPulang(string $jamMasuk, string $jamPulang, string $tanggalShift, DateTime $now): int
    {
        $lintas = $jamPulang <= $jamMasuk;
        $jd = new DateTime($tanggalShift . ' ' . $jamPulang);
        if ($lintas) {
            $jd->modify('+1 day');
        }
        return (int) ceil(($jd->getTimestamp() - $now->getTimestamp()) / 60);
    }
}
