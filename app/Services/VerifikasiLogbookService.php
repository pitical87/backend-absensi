<?php

namespace App\Services;

use App\Models\AtasanLangsung;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi logbook oleh atasan langsung.
 *
 * Hubungan atasan diambil dari tabel `atasan_langsung`: baris dengan
 * `atasan_id` = user yang sedang login berarti user tersebut adalah atasan
 * langsung dari `user_id`. Satu pegawai boleh punya lebih dari satu atasan
 * langsung, jadi setiap atasan yang terdaftar ikut berwenang.
 */
class VerifikasiLogbookService
{
    /**
     * Bawahan langsung beserta jumlah entri dan progres verifikasinya.
     *
     * @return Collection<int, User>
     */
    public function bawahan(User $atasan, int $bulan, int $tahun): Collection
    {
        $daftar = User::query()
            ->whereIn('users.id', $this->subBawahan($atasan))
            ->orderBy('users.nama_lengkap')
            ->get(['users.id', 'users.nama_lengkap', 'users.nip']);

        if ($daftar->isEmpty()) {
            return $daftar;
        }

        $hitung = DB::table('logbooks')
            ->select('user_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS terverifikasi')
            ->whereIn('user_id', $daftar->pluck('id')->all())
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $daftar->each(function (User $u) use ($hitung) {
            $baris = $hitung->get($u->id);
            $total = (int) ($baris->total ?? 0);
            $sudah = (int) ($baris->terverifikasi ?? 0);

            $u->setAttribute('total_entri', $total);
            $u->setAttribute('terverifikasi', $sudah);
            $u->setAttribute('belum', max(0, $total - $sudah));
        });

        return $daftar;
    }

    /**
     * Id seluruh bawahan langsung, untuk menyaring query logbook.
     *
     * @return array<int, int>
     */
    public function idBawahan(User $atasan): array
    {
        return $this->subBawahan($atasan)->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Apakah user ini atasan langsung dari pegawai tersebut.
     */
    public function isAtasan(User $calonAtasan, int $pegawaiId): bool
    {
        if ($pegawaiId <= 0 || $pegawaiId === (int) $calonAtasan->id) {
            return false;
        }

        return AtasanLangsung::where('atasan_id', $calonAtasan->id)
            ->where('user_id', $pegawaiId)
            ->exists();
    }

    /**
     * Entri logbook seorang bawahan dikelompokkan per tanggal, terbaru dulu.
     *
     * @return array{grup: array<string, array<int, array<string, mixed>>>, total: int, terverifikasi: int, belum: int, total_hari: int}
     */
    public function entriBawahan(int $pegawaiId, int $bulan, int $tahun): array
    {
        $entri = Logbook::query()
            ->where('user_id', $pegawaiId)
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->orderByDesc('tanggal')
            ->orderByDesc('jam')
            ->get();

        $grup = [];
        foreach ($entri as $e) {
            $grup[$e->tanggal->format('Y-m-d')][] = [
                'id' => (int) $e->id,
                'jam' => substr((string) $e->jam, 0, 5),
                'isi' => (string) $e->isi,
                'is_verified' => (bool) $e->is_verified,
                'verified_at' => $e->verified_at?->toDateTimeString(),
                'verified_by' => $e->verifikator?->nama_lengkap,
            ];
        }

        $total = $entri->count();
        $sudah = $entri->where('is_verified', true)->count();

        return [
            'grup' => $grup,
            'total' => $total,
            'terverifikasi' => $sudah,
            'belum' => max(0, $total - $sudah),
            'total_hari' => count($grup),
        ];
    }

    /**
     * Terapkan verifikasi atau pembatalan verifikasi atas nama atasan langsung.
     *
     * Semua id harus milik bawahan langsung; bila ada id di luar kewenangan
     * atau di luar rentang perubahan, tidak ada satu pun entri yang disentuh.
     *
     * @param  array<int, mixed>  $ids
     * @return array{ok: bool, pesan: string, jumlah: int, status: int}
     */
    public function terapkan(array $ids, User $atasan, string $aksi, ?int $pegawaiId = null): array
    {
        if (! in_array($aksi, ['verifikasi', 'batal'], true)) {
            return ['ok' => false, 'pesan' => 'Aksi harus verifikasi atau batal.', 'jumlah' => 0, 'status' => 422];
        }

        $ids = collect($ids)
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return ['ok' => false, 'pesan' => 'Pilih minimal satu entri logbook.', 'jumlah' => 0, 'status' => 422];
        }

        $diizinkan = $this->idBawahan($atasan);
        if ($pegawaiId !== null) {
            $diizinkan = array_values(array_filter($diizinkan, fn ($v) => $v === $pegawaiId));
        }

        if ($diizinkan === []) {
            return ['ok' => false, 'pesan' => 'Anda tidak memiliki bawahan langsung untuk diverifikasi.', 'jumlah' => 0, 'status' => 403];
        }

        //ambil pemilik semua id dalam satu query, lalu tolak bila ada yang
        //bukan bawahan langsung pemanggil (tanpa verifikasi parsial)
        $pemilik = Logbook::whereIn('id', $ids)->pluck('user_id', 'id');

        foreach ($ids as $id) {
            if (! in_array((int) $pemilik->get($id), $diizinkan, true)) {
                return [
                    'ok' => false,
                    'pesan' => 'Entri logbook tersebut bukan milik bawahan langsung Anda.',
                    'jumlah' => 0,
                    'status' => 403,
                ];
            }
        }

        $kondisi = $aksi === 'verifikasi'
            ? ['is_verified' => false]
            : ['is_verified' => true];

        $ubah = $aksi === 'verifikasi'
            ? ['is_verified' => true, 'verified_at' => now(), 'verified_by' => $atasan->id]
            : ['is_verified' => false, 'verified_at' => null, 'verified_by' => null];

        $jumlah = Logbook::query()
            ->whereIn('id', $ids)
            ->whereIn('user_id', $diizinkan)
            ->where($kondisi)
            ->update($ubah);

        if ($jumlah === 0) {
            return [
                'ok' => false,
                'pesan' => $aksi === 'verifikasi'
                    ? 'Entri sudah terverifikasi atau tidak ditemukan.'
                    : 'Entri belum terverifikasi atau tidak ditemukan.',
                'jumlah' => 0,
                'status' => 404,
            ];
        }

        return [
            'ok' => true,
            'pesan' => $jumlah.' entri logbook berhasil '.($aksi === 'verifikasi' ? 'diverifikasi' : 'dibatalkan verifikasinya').'.',
            'jumlah' => $jumlah,
            'status' => 200,
        ];
    }

    /**
     * Sub-query id bawahan langsung, dipakai bawahan() dan idBawahan().
     */
    private function subBawahan(User $atasan): \Illuminate\Database\Query\Builder
    {
        return DB::table('atasan_langsung')
            ->select('user_id')
            ->where('atasan_id', $atasan->id)
            ->where('user_id', '!=', $atasan->id)
            ->distinct();
    }
}
