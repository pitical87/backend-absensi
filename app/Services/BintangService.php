<?php

namespace App\Services;

use DateTime;

class BintangService
{
    public const MAKS = 5;

    /** Batas bawah bintang: siapa pun yang sudah absen tetap minimal 1 bintang. */
    public const MIN = 1;

    public const GRACE_MENIT = 10;

    /**
     * $menitSetelahJadwal bertanda: negatif = absen lebih awal dari jadwal.
     * Lebih awal -> 5 · tepat s.d. toleransi 10' -> 4 · pelanggaran efektif
     * (setelah dikurangi 10'): <=5' -> 4, <=10' -> 3, <=15' -> 2, >15' -> 1.
     * Tidak ada bintang 0: berapa pun keterlambatannya, yang sudah absen
     * tetap mendapat minimal MIN (1) bintang.
     */
    public static function bintangMasuk(int $menitSetelahJadwal): int
    {
        if ($menitSetelahJadwal < 0) {
            return 5;
        }

        $eff = max(0, $menitSetelahJadwal - self::GRACE_MENIT);

        return match (true) {
            $eff <= 5 => 4,
            $eff <= 10 => 3,
            $eff <= 15 => 2,
            default => self::MIN,
        };
    }

    /**
     * $menitLebihAwal bertanda: negatif = pulang melewati jam pulang.
     * Melewati jam pulang -> 5 · tepat -> 4 · pulang cepat efektif
     * (setelah dikurangi 10'): <=5' -> 4, <=10' -> 3, <=15' -> 2, >15' -> 1.
     * Sama seperti masuk, tidak ada bintang 0 untuk yang sudah absen pulang.
     */
    public static function bintangPulang(int $menitLebihAwal): int
    {
        if ($menitLebihAwal < 0) {
            return 5;
        }

        if ($menitLebihAwal === 0) {
            return 4;
        }

        $eff = max(0, $menitLebihAwal - self::GRACE_MENIT);

        return match (true) {
            $eff <= 5 => 4,
            $eff <= 10 => 3,
            $eff <= 15 => 2,
            default => self::MIN,
        };
    }

    public function bintangHarian(int $bintangMasuk, int $bintangPulang): float
    {
        return round(($bintangMasuk + $bintangPulang) / 2, 1, PHP_ROUND_HALF_UP);
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
