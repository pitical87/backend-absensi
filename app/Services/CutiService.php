<?php

namespace App\Services;

use App\Models\Izin;

class CutiService
{
    public const HAK_TAHUNAN = 12;

    public function rekap(int $userId, int $tahun): array
    {
        $rows = Izin::where('user_id', $userId)
            ->where('status', 'Disetujui')
            ->whereYear('tanggal_mulai', $tahun)
            ->where(function ($q) {
                $q->where('jenis', 'Izin')
                    ->orWhere(function ($q2) {
                        $q2->where('jenis', 'Cuti')
                            ->where('jenis_cuti', 'Cuti Tahunan');
                    });
            })
            ->select('tanggal_mulai', 'tanggal_selesai', 'lama_hari', 'jenis', 'jenis_cuti')
            ->get()
            ->toArray();

        $terpakai = 0;
        foreach ($rows as $r) {
            $terpakai += (int) ($r['lama_hari'] ?? 0);
        }

        $sisa = max(0, self::HAK_TAHUNAN - $terpakai);

        return [
            'hak' => self::HAK_TAHUNAN,
            'terpakai' => $terpakai,
            'sisa' => $sisa,
            'rincian' => $rows,
        ];
    }
}
