<?php

namespace App\Services;

use App\Models\AtasanLangsung;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlurIzinService
{
    /**
     * Daftar atasan langsung aktif milik seorang pegawai dari tabel `atasan_langsung`.
     *
     * @return Collection<int, User>
     */
    public function atasanLangsungUntuk(int $pegawaiId): Collection
    {
        if ($pegawaiId <= 0) {
            return collect();
        }

        return User::query()
            ->join('atasan_langsung as al', 'al.atasan_id', '=', 'users.id')
            ->where('al.user_id', $pegawaiId)
            ->where('al.atasan_id', '!=', $pegawaiId)
            ->where('users.status', 'aktif')
            ->orderBy('al.id')
            ->get(['users.*']);
    }

    /**
     * Daftar ID bawahan langsung milik seorang atasan dari tabel `atasan_langsung`.
     *
     * @return array<int, int>
     */
    public function idBawahanLangsung(int $atasanId): array
    {
        if ($atasanId <= 0) {
            return [];
        }

        return AtasanLangsung::where('atasan_id', $atasanId)
            ->where('user_id', '!=', $atasanId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Apakah user ini tercatat sebagai atasan langsung aktif bagi pegawai tersebut.
     */
    public function isAtasanLangsung(User $calonAtasan, int $pegawaiId): bool
    {
        if ($pegawaiId <= 0 || (int) $calonAtasan->id === $pegawaiId || $calonAtasan->status !== 'aktif') {
            return false;
        }

        return AtasanLangsung::where('user_id', $pegawaiId)
            ->where('atasan_id', (int) $calonAtasan->id)
            ->exists();
    }

    public function tahapUntuk(string $posisiPemohon): array
    {
        return [1];
    }

    public function pejabatTahap(int $tahap, array $pemohon): ?User
    {
        if ($tahap !== 1) {
            return null;
        }

        return $this->atasanLangsungUntuk((int) ($pemohon['id'] ?? 0))->first();
    }

    /**
     * Mulai alur persetujuan izin berbasis `atasan_langsung` (1 tahap).
     * Jika pegawai belum memiliki atasan langsung di tabel `atasan_langsung`,
     * pengajuan tetap berstatus Menunggu agar dapat diproses oleh admin.
     *
     * @return array{0: int, 1: string}
     */
    public function mulai(int $pengajuanId, array $pemohon): array
    {
        $punyaAtasan = $this->atasanLangsungUntuk((int) ($pemohon['id'] ?? 0))->isNotEmpty();

        IzinPersetujuan::create([
            'pengajuan_id' => $pengajuanId,
            'tahap' => 1,
            'posisi_tahap' => $punyaAtasan ? 'Atasan Langsung' : 'Admin',
            'status' => 'Menunggu',
        ]);

        return [1, 'Menunggu'];
    }

    public function bolehBertindak(array $pengajuan, array $pemohon, User $user): bool
    {
        if ((int) ($pengajuan['tahap_aktif'] ?? 0) === 0) {
            return false;
        }
        if ($user->role === 'admin') {
            return true;
        }

        return $this->isAtasanLangsung($user, (int) ($pemohon['id'] ?? 0));
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
                'tahap_aktif' => 0,
                'diproses_oleh' => $olehUserId,
                'processed_at' => Carbon::now(),
            ]);

            if ($izinModel = Izin::find($pengajuan['id'])) {
                $this->notifikasiKeputusan($izinModel, 'Ditolak', $catatan);
            }

            return 'Ditolak';
        }

        // Alur berbasis atasan langsung hanya 1 tahap: bila masih ada sisa baris
        // tahap struktur lama yang Menunggu (mis. HRD), tandai dilewati agar langsung selesai.
        IzinPersetujuan::where('pengajuan_id', $pengajuan['id'])
            ->where('tahap', '!=', $tahap)
            ->where('status', 'Menunggu')
            ->update([
                'status' => 'Dilewati',
                'catatan' => 'Disetujui langsung oleh atasan langsung.',
                'waktu' => Carbon::now(),
            ]);

        $nomor = $this->buatNomorSurat();
        $kode = strtoupper(bin2hex(random_bytes(5)));
        Izin::where('id', $pengajuan['id'])->update([
            'status' => 'Disetujui',
            'tahap_aktif' => 0,
            'diproses_oleh' => $olehUserId,
            'processed_at' => Carbon::now(),
            'nomor_surat' => $nomor,
            'kode_verifikasi' => $kode,
        ]);

        if ($izinModel = Izin::find($pengajuan['id'])) {
            $this->notifikasiKeputusan($izinModel, 'Disetujui', $catatan);
        }

        return 'Disetujui';
    }

    /**
     * Kirim notifikasi in-app + Web Push ke seluruh atasan langsung pemohon
     * (atau ke admin aktif bila pemohon belum memiliki atasan langsung).
     */
    public function notifikasiPengajuanBaru(Izin $izin, ?User $pemohon = null): void
    {
        $pemohon ??= User::find((int) $izin->user_id);
        $namaPemohon = $pemohon?->nama_lengkap ?: 'Pegawai';
        $labelJenis = $izin->jenis_cuti ?: $izin->jenis;
        $rentang = $this->formatRentangTanggal($izin);

        $isi = $namaPemohon.' mengajukan '.$labelJenis.' ('.$rentang.').';

        $atasanList = $this->atasanLangsungUntuk((int) $izin->user_id);
        if ($atasanList->isNotEmpty()) {
            foreach ($atasanList as $atasan) {
                buat_notifikasi((int) $atasan->id, $isi, 'warning', 'persetujuan', 'izin');
            }

            return;
        }

        foreach (User::where('role', 'admin')->where('status', 'aktif')->pluck('id') as $adminId) {
            buat_notifikasi(
                (int) $adminId,
                $isi.' (belum memiliki atasan langsung)',
                'warning',
                'admin/izin',
                'izin',
            );
        }
    }

    /**
     * Kirim notifikasi hasil keputusan (Disetujui/Ditolak) kembali ke pemohon.
     */
    public function notifikasiKeputusan(Izin $izin, string $statusAkhir, ?string $catatan = null): void
    {
        $labelJenis = $izin->jenis_cuti ?: $izin->jenis;
        $rentang = $this->formatRentangTanggal($izin);
        $tipe = $statusAkhir === 'Disetujui' ? 'success' : 'danger';
        $isi = 'Pengajuan '.$labelJenis.' ('.$rentang.') '.strtolower($statusAkhir)
            .($catatan ? ' — '.$catatan : '').'.';

        buat_notifikasi((int) $izin->user_id, $isi, $tipe, 'izin', 'izin');
    }

    private function formatRentangTanggal(Izin $izin): string
    {
        $mulai = $izin->tanggal_mulai instanceof Carbon
            ? $izin->tanggal_mulai->format('Y-m-d')
            : substr((string) $izin->tanggal_mulai, 0, 10);
        $selesai = $izin->tanggal_selesai instanceof Carbon
            ? $izin->tanggal_selesai->format('Y-m-d')
            : substr((string) $izin->tanggal_selesai, 0, 10);

        return $mulai === $selesai
            ? tgl_id($mulai, false)
            : tgl_id($mulai, false).' s.d. '.tgl_id($selesai, false);
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
