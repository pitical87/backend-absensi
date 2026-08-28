<?php

namespace App\Services;

use App\Models\AtasanLangsung;
use App\Models\JadwalShift;
use App\Models\Notifikasi;
use App\Models\PengajuanLembur;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PengajuanLemburService
{
    public const HARI_KE_DEPAN = 7;

    public function batasJam(): int
    {
        return max(0, (int) pengaturan('batas_pengajuan_lembur_jam', '2'));
    }

    public function maksLemburPerHariJam(): int
    {
        return max(0, (int) pengaturan('maks_lembur_per_hari_jam', '4'));
    }

    public function toleransiMenit(): int
    {
        return max(0, (int) pengaturan('toleransi_lembur_menit', '5'));
    }

    /**
     * Atasan langsung pegawai: tabel atasan_langsung → sub_unit.atasan_id → unit_kerja.atasan_id.
     */
    public function atasanUntuk(int $pegawaiId): ?User
    {
        $pegawai = User::with('unitKerja:id,nama,atasan_id', 'subUnit:id,nama,atasan_id')
            ->find($pegawaiId);
        if (! $pegawai) {
            return null;
        }

        $atasanId = AtasanLangsung::where('user_id', $pegawai->id)
            ->orderBy('id')
            ->value('atasan_id');
        $atasanId = $atasanId ?: $pegawai->subUnit?->atasan_id ?: $pegawai->unitKerja?->atasan_id;

        if (! $atasanId || (int) $atasanId === (int) $pegawai->id) {
            return null;
        }

        return User::where('id', $atasanId)->where('status', 'aktif')->first();
    }

    public function isAtasan(User $calonAtasan, int $pegawaiId): bool
    {
        $atasan = $this->atasanUntuk($pegawaiId);

        return $atasan && (int) $atasan->id === (int) $calonAtasan->id;
    }

    /**
     * Cek kelayakan pengajuan lembur.
     *
     * @return array{ok: bool, pesan: ?string}
     */
    public function kelayakan(User $pemohon, string $tanggal, string $jamMulai, string $jamSelesai): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return ['ok' => false, 'pesan' => 'Tanggal tidak valid.'];
        }
        if ($tanggal < now()->toDateString() || $tanggal > now()->copy()->addDays(self::HARI_KE_DEPAN)->toDateString()) {
            return ['ok' => false, 'pesan' => 'Lembur hanya bisa diajukan untuk hari ini s.d. ' . self::HARI_KE_DEPAN . ' hari ke depan.'];
        }

        $mulai   = Carbon::parse($jamMulai);
        $selesai = Carbon::parse($jamSelesai);
        if (! $mulai->lt($selesai)) {
            return ['ok' => false, 'pesan' => 'Jam mulai harus lebih awal dari jam selesai.'];
        }

        $durasi = round($mulai->diffInMinutes($selesai) / 60, 1);
        $maks   = $this->maksLemburPerHariJam();
        if ($maks > 0 && $durasi > $maks) {
            return ['ok' => false, 'pesan' => 'Durasi lembur melebihi batas ' . $maks . ' jam per hari.'];
        }

        $adaShift = $this->overlapShift($pemohon->id, $tanggal, $mulai->format('H:i:s'), $selesai->format('H:i:s'));
        if ($adaShift) {
            return ['ok' => false, 'pesan' => 'Rentang lembur bertabrakan dengan jadwal shift yang sudah ada.'];
        }

        // Total durasi pengajuan aktif (Menunggu/Disetujui) pada tanggal tsb tidak boleh melebihi batas harian.
        if ($maks > 0) {
            $totalAda = (float) PengajuanLembur::where('user_id', $pemohon->id)
                ->where('tanggal', $tanggal)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->sum('durasi_jam');
            if ($totalAda + $durasi > $maks) {
                return ['ok' => false, 'pesan' => 'Total lembur pada tanggal tersebut akan melebihi batas ' . $maks . ' jam per hari.'];
            }
        }

        $adaOverlap = PengajuanLembur::where('user_id', $pemohon->id)
            ->where('tanggal', $tanggal)
            ->whereIn('status', ['Menunggu', 'Disetujui'])
            ->get()
            ->contains(function ($pj) use ($mulai, $selesai) {
                $pMulai   = Carbon::parse($pj->jam_mulai);
                $pSelesai = Carbon::parse($pj->jam_selesai);

                return $mulai->lt($pSelesai) && $selesai->gt($pMulai);
            });
        if ($adaOverlap) {
            return ['ok' => false, 'pesan' => 'Anda sudah memiliki pengajuan lembur aktif pada rentang waktu yang sama.'];
        }

        return ['ok' => true, 'pesan' => null];
    }

    private function overlapShift(int $userId, string $tanggal, string $jamMulai, string $jamSelesai): bool
    {
        $mulai   = Carbon::createFromFormat('Y-m-d H:i:s', "$tanggal $jamMulai");
        $selesai = Carbon::createFromFormat('Y-m-d H:i:s', "$tanggal $jamSelesai");

        return JadwalShift::with('shift:id,jam_masuk,jam_pulang')
            ->where('user_id', $userId)
            ->where('tanggal_berlaku', $tanggal)
            ->get()
            ->contains(function ($j) use ($mulai, $selesai) {
                $shift = $j->shift;
                if (! $shift || ! $shift->jam_masuk || ! $shift->jam_pulang) {
                    return false;
                }
                $sMasuk   = Carbon::parse($j->tanggal_berlaku)->setTimeFrom(Carbon::parse($shift->jam_masuk));
                $sPulang  = Carbon::parse($j->tanggal_berlaku)->setTimeFrom(Carbon::parse($shift->jam_pulang));
                if ($sPulang->lte($sMasuk)) {
                    $sPulang->addDay();
                }

                return $mulai->lt($sPulang) && $selesai->gt($sMasuk);
            }) || false;
    }

    /**
     * Simpan pengajuan baru + notifikasi ke atasan langsung (fallback semua admin).
     *
     * @return array{ok: bool, pesan: string, pengajuan: ?PengajuanLembur}
     */
    public function simpan(User $pemohon, string $tanggal, string $jamMulai, string $jamSelesai, string $keterangan): array
    {
        $cek = $this->kelayakan($pemohon, $tanggal, $jamMulai, $jamSelesai);
        if (! $cek['ok']) {
            return ['ok' => false, 'pesan' => $cek['pesan'], 'pengajuan' => null];
        }

        $mulai   = Carbon::parse($jamMulai);
        $selesai = Carbon::parse($jamSelesai);
        $durasi  = round($mulai->diffInMinutes($selesai) / 60, 1);

        $pengajuan = DB::transaction(function () use ($pemohon, $tanggal, $mulai, $selesai, $durasi, $keterangan) {
            return PengajuanLembur::create([
                'user_id'     => $pemohon->id,
                'tanggal'     => $tanggal,
                'jam_mulai'   => $mulai->format('H:i:s'),
                'jam_selesai' => $selesai->format('H:i:s'),
                'durasi_jam'  => $durasi,
                'keterangan'  => $keterangan,
                'status'      => 'Menunggu',
                'created_at'  => now(),
            ]);
        });

        $this->notifikasiAtasan($pemohon, $pengajuan);
        catat_aktivitas(
            'Pengajuan Lembur',
            $pemohon->nama_lengkap . ' — ' . tgl_id($tanggal, false)
                . ' ' . $mulai->format('H.i') . ' - ' . $selesai->format('H.i')
                . ' (' . $durasi . ' jam)'
        );

        return [
            'ok'        => true,
            'pesan'     => 'Pengajuan lembur terkirim dan menunggu persetujuan atasan langsung.',
            'pengajuan' => $pengajuan,
        ];
    }

    private function notifikasiAtasan(User $pemohon, PengajuanLembur $pj): void
    {
        $isi = $pemohon->nama_lengkap . ' mengajukan lembur '
            . tgl_id($pj->tanggal->format('Y-m-d'), false)
            . ' (' . Carbon::parse($pj->jam_mulai)->format('H.i') . ' - '
            . Carbon::parse($pj->jam_selesai)->format('H.i') . ', '
            . (float) $pj->durasi_jam . ' jam).';
        $url = 'persetujuan';

        $atasan = $this->atasanUntuk((int) $pemohon->id);
        if ($atasan) {
            Notifikasi::create(['user_id' => $atasan->id, 'isi' => $isi, 'url' => $url, 'tipe' => 'warning']);

            return;
        }

        foreach (User::where('role', 'admin')->where('status', 'aktif')->pluck('id') as $adminId) {
            Notifikasi::create([
                'user_id' => $adminId,
                'isi'     => $isi . ' (tidak ditemukan atasan langsung)',
                'url'     => 'admin/lembur',
                'tipe'    => 'warning',
            ]);
        }
    }

    /**
     * Putuskan pengajuan lembur.
     *
     * @return array{ok: bool, pesan: string, status: ?string}
     */
    public function putuskan(PengajuanLembur $pj, string $putusan, int $olehUserId, ?string $catatan = null): array
    {
        if ($pj->status !== 'Menunggu') {
            return ['ok' => false, 'pesan' => 'Pengajuan sudah diproses sebelumnya.', 'status' => $pj->status];
        }
        $putusan = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';

        $pj->update([
            'status'            => $putusan,
            'diproses_oleh'     => $olehUserId,
            'catatan_keputusan' => $catatan,
            'diproses_pada'     => now(),
        ]);

        $tipe = $putusan === 'Disetujui' ? 'success' : 'danger';
        Notifikasi::create([
            'user_id' => $pj->user_id,
            'isi'     => 'Pengajuan lembur ' . tgl_id($pj->tanggal->format('Y-m-d'), false)
                . ' (' . Carbon::parse($pj->jam_mulai)->format('H.i') . ' - '
                . Carbon::parse($pj->jam_selesai)->format('H.i') . ') '
                . strtolower($putusan) . '.',
            'url'     => 'lembur',
            'tipe'    => $tipe,
        ]);

        catat_aktivitas(
            'Keputusan Lembur',
            'Pengajuan #' . $pj->id . ' (' . ($pj->user?->nama_lengkap ?? '-') . ') → ' . $putusan
        );

        return [
            'ok'     => true,
            'pesan'  => $putusan === 'Disetujui' ? 'Pengajuan lembur disetujui.' : 'Pengajuan lembur ditolak.',
            'status' => $putusan,
        ];
    }

    /**
     * Daftar pengajuan Menunggu yang berwenang diputus user (sebagai atasan langsung).
     */
    public function tugasAtasan(User $atasan): array
    {
        return PengajuanLembur::with(['user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id', 'user.unitKerja:id,nama', 'user.subUnit:id,nama', 'absenLembur'])
            ->where('status', 'Menunggu')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->filter(fn (PengajuanLembur $pj) => $this->isAtasan($atasan, (int) $pj->user_id))
            ->values()
            ->all();
    }
}
