<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.fonnte.com/send';

    public function __construct()
    {
        $this->apiKey = pengaturan('fonnte_api_key', '');
    }

    public function kirim(string $nomor, string $pesan): bool
    {
        if ($this->apiKey === '' || $nomor === '') {
            return false;
        }

        $nomor = $this->formatNomor($nomor);

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl, [
                'target' => $nomor,
                'message' => $pesan,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    public function kirimNotifikasiIzin(
        string $nomor,
        string $tipe,
        string $namaPemohon,
        string $jenisIzin,
        string $tanggal,
    ): bool {
        $pesan = match ($tipe) {
            'baru' => "📋 *Pengajuan {$jenisIzin} Baru*\n"
                    . "Dari: {$namaPemohon}\n"
                    . "Periode: {$tanggal}\n"
                    . "Silakan buka sistem untuk memproses.",
            'perlu_persetujuan' => "⏳ *Perlu Persetujuan*\n"
                    . "{$namaPemohon} mengajukan {$jenisIzin}\n"
                    . "Periode: {$tanggal}\n"
                    . "Silakan proses di menu Persetujuan.",
            'disetujui' => "✅ *Pengajuan {$jenisIzin} Disetujui*\n"
                    . "Untuk: {$namaPemohon}\n"
                    . "Periode: {$tanggal}",
            'ditolak' => "❌ *Pengajuan {$jenisIzin} Ditolak*\n"
                    . "Untuk: {$namaPemohon}\n"
                    . "Periode: {$tanggal}",
            default => "Notifikasi sistem absensi: {$tipe}",
        };

        return $this->kirim($nomor, $pesan);
    }

    private function formatNomor(string $nomor): string
    {
        $nomor = preg_replace('/[^0-9]/', '', $nomor);
        if (str_starts_with($nomor, '0')) {
            $nomor = '62' . substr($nomor, 1);
        }
        return $nomor;
    }
}
