<?php

namespace App\Services;

use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\Jabatan;
use App\Models\User;
use Carbon\Carbon;

class AlurIzinService
{
    public function tahapUntuk(string $posisiPemohon): array
    {
        $mulai = posisi_index($posisiPemohon) + 1;
        $semua = [1, 2, 3, 4];
        return array_values(array_filter($semua, fn ($t) => $t >= $mulai));
    }

    public function pejabatTahap(int $tahap, array $pemohon): ?User
    {
        $query = User::where('status', 'aktif');

        if ($tahap === 1) {
            $query->where('posisi', 'Koordinator/Kepala Unit/Ruang/Instalasi')
                ->where('id', '!=', $pemohon['id'])
                ->where('unit_kerja_id', $pemohon['unit_kerja_id']);
            if (! empty($pemohon['sub_unit_id'])) {
                $query->where('sub_unit_id', $pemohon['sub_unit_id']);
            }
        } elseif ($tahap === 2) {
            if (empty($pemohon['seksi_pembina_id'])) {
                return null;
            }
            $query->where('posisi', 'Kepala Seksi/Sub Bagian')
                ->where('jabatan_id', $pemohon['seksi_pembina_id']);
        } elseif ($tahap === 3) {
            $seksiId = posisi_index($pemohon['posisi'] ?? 'Staf') >= 2 && ! empty($pemohon['jabatan_id'])
                ? (int) $pemohon['jabatan_id']
                : (int) ($pemohon['seksi_pembina_id'] ?? 0);
            if (! $seksiId) {
                return null;
            }
            $seksi = Jabatan::find($seksiId);
            if (! $seksi || ! $seksi->induk_id) {
                return null;
            }
            $query->where('posisi', 'Kepala Bidang/Bagian')
                ->where('jabatan_id', $seksi->induk_id);
        } elseif ($tahap === 4) {
            $query->where('posisi', 'HRD');
        } else {
            return null;
        }

        return $query->first();
    }

    public function mulai(int $pengajuanId, array $pemohon): array
    {
        $tahapList = $this->tahapUntuk($pemohon['posisi'] ?? 'Staf');
        $tahapAktif = 0;

        foreach ($tahapList as $t) {
            $pejabat = $this->pejabatTahap($t, $pemohon);
            if ($pejabat) {
                IzinPersetujuan::create([
                    'pengajuan_id' => $pengajuanId,
                    'tahap' => $t,
                    'posisi_tahap' => label_tahap_izin($t),
                    'status' => 'Menunggu',
                ]);
                if (! $tahapAktif) $tahapAktif = $t;
            } elseif ($t === 4) {
                IzinPersetujuan::create([
                    'pengajuan_id' => $pengajuanId,
                    'tahap' => 4,
                    'posisi_tahap' => 'HRD',
                    'status' => 'Menunggu',
                ]);
                if (! $tahapAktif) $tahapAktif = 4;
            } else {
                IzinPersetujuan::create([
                    'pengajuan_id' => $pengajuanId,
                    'tahap' => $t,
                    'posisi_tahap' => label_tahap_izin($t),
                    'status' => 'Dilewati',
                    'catatan' => 'Tidak ada pejabat terdaftar untuk tahap ini — dilewati otomatis.',
                    'waktu' => Carbon::now(),
                ]);
            }
        }

        return $tahapAktif
            ? [$tahapAktif, 'Menunggu']
            : [0, 'Disetujui'];
    }

    public function bolehBertindak(array $pengajuan, array $pemohon, User $user): bool
    {
        if ((int) $pengajuan['tahap_aktif'] === 0) {
            return false;
        }
        if ($user->role === 'admin') {
            return true;
        }
        $pejabat = $this->pejabatTahap((int) $pengajuan['tahap_aktif'], $pemohon);
        return $pejabat && $pejabat->id === $user->id;
    }

    public function proses(array $pengajuan, array $pemohon, int $olehUserId, string $putusan, ?string $catatan): string
    {
        $tahap = (int) $pengajuan['tahap_aktif'];
        $baru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';

        IzinPersetujuan::where('pengajuan_id', $pengajuan['id'])
            ->where('tahap', $tahap)
            ->update([
                'status' => $baru,
                'oleh_user_id' => $olehUserId,
                'catatan' => $catatan,
                'waktu' => Carbon::now(),
            ]);

        if ($baru === 'Ditolak') {
            IzinPersetujuan::where('pengajuan_id', $pengajuan['id'])
                ->where('status', 'Menunggu')
                ->update([
                    'status' => 'Dilewati',
                    'catatan' => 'Pengajuan telah ditolak pada tahap sebelumnya.',
                    'waktu' => Carbon::now(),
                ]);

            Izin::where('id', $pengajuan['id'])->update([
                'status' => 'Ditolak',
                'processed_at' => Carbon::now(),
            ]);
            return 'Ditolak';
        }

        $sisaTahap = array_filter($this->tahapUntuk($pemohon['posisi'] ?? 'Staf'), fn ($t) => $t > $tahap);
        $tahapBerikut = 0;
        foreach ($sisaTahap as $t) {
            $baris = IzinPersetujuan::where('pengajuan_id', $pengajuan['id'])
                ->where('tahap', $t)
                ->first();
            if ($baris && $baris->status === 'Menunggu') {
                $tahapBerikut = $t;
                break;
            }
        }

        if ($tahapBerikut) {
            Izin::where('id', $pengajuan['id'])->update(['tahap_aktif' => $tahapBerikut]);
            return 'Menunggu';
        }

        $nomor = $this->buatNomorSurat();
        $kode = strtoupper(bin2hex(random_bytes(5)));
        Izin::where('id', $pengajuan['id'])->update([
            'status' => 'Disetujui',
            'tahap_aktif' => 0,
            'processed_at' => Carbon::now(),
            'nomor_surat' => $nomor,
            'kode_verifikasi' => $kode,
        ]);
        return 'Disetujui';
    }

    private function buatNomorSurat(): string
    {
        $bulan = (int) Carbon::now()->format('n');
        $tahun = (int) Carbon::now()->format('Y');
        $jml = Izin::whereMonth('processed_at', $bulan)
            ->whereYear('processed_at', $tahun)
            ->whereNotNull('nomor_surat')
            ->count();
        return sprintf('800/%03d/RSUD-MRK/%02d/%d', $jml + 1, $bulan, $tahun);
    }
}
