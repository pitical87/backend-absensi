<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AtasanLangsung;
use App\Models\JadwalShift;
use App\Models\Notifikasi;
use App\Models\PengajuanJadwal;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UbahJadwalService
{
    public const HARI_KE_DEPAN = 30;

    public function batasJam(): int
    {
        return max(0, (int) pengaturan('batas_ubah_jadwal_jam', '1'));
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
     * Batas akhir pengajuan: jam mulai shift lama pada tanggal terkait dikurangi batas jam.
     */
    public function batasWaktu(JadwalShift $jadwal): ?Carbon
    {
        $shiftLama = $jadwal->shift;
        if (! $shiftLama) {
            return null;
        }

        $mulai = Carbon::parse(\Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d') . ' '
            . Carbon::parse($shiftLama->jam_masuk)->format('H:i:s'));

        return $mulai->subHours($this->batasJam());
    }

    /**
     * Cek kelayakan mengajukan perubahan untuk satu baris jadwal.
     *
     * @return array{ok: bool, pesan: ?string}
     */
    public function kelayakan(JadwalShift $jadwal, int $userId): array
    {
        if ((int) $jadwal->user_id !== $userId) {
            return ['ok' => false, 'pesan' => 'Jadwal bukan milik Anda.'];
        }

        if (! $jadwal->shift) {
            return ['ok' => false, 'pesan' => 'Shift jadwal tidak ditemukan.'];
        }

        $batas = $this->batasWaktu($jadwal);
        if ($batas && now()->gt($batas)) {
            return [
                'ok'    => false,
                'pesan' => sprintf(
                    'Batas pengajuan lewat. Paling lambat %d jam sebelum shift dimulai (pukul %s).',
                    $this->batasJam(),
                    $batas->format('H.i')
                ),
            ];
        }

        $sudahAbsen = Absensi::where('user_id', $userId)
            ->where('tanggal', \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d'))
            ->exists();
        if ($sudahAbsen) {
            return ['ok' => false, 'pesan' => 'Anda sudah melakukan absensi pada tanggal tersebut.'];
        }

        $adaAktif = PengajuanJadwal::where('user_id', $userId)
            ->where('tanggal', \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d'))
            ->whereIn('status', ['Menunggu', 'Disetujui'])
            ->exists();
        if ($adaAktif) {
            return ['ok' => false, 'pesan' => 'Sudah ada pengajuan aktif/disetujui untuk tanggal ini.'];
        }

        return ['ok' => true, 'pesan' => null];
    }

    public function jadwalMendatang(int $userId): array
    {
        return JadwalShift::with('shift:id,kategori,jam_masuk,jam_pulang')
            ->where('user_id', $userId)
            ->whereBetween('tanggal_berlaku', [now()->toDateString(), now()->copy()->addDays(self::HARI_KE_DEPAN)->toDateString()])
            ->orderBy('tanggal_berlaku')
            ->get()
            ->all();
    }

    /**
     * Simpan pengajuan baru + notifikasi ke atasan langsung (fallback admin).
     *
     * @return array{ok: bool, pesan: string, pengajuan: ?PengajuanJadwal}
     */
    public function simpan(User $pemohon, string $tanggal, int $shiftBaruId, string $alasan): array
    {
        $jadwal = JadwalShift::with('shift:id,kategori,jam_masuk,jam_pulang')
            ->where('user_id', $pemohon->id)
            ->where('tanggal_berlaku', $tanggal)
            ->first();

        if (! $jadwal) {
            return ['ok' => false, 'pesan' => 'Tidak ada jadwal pada tanggal tersebut.', 'pengajuan' => null];
        }

        $shiftBaru = Shift::where('id', $shiftBaruId)->where('aktif', 1)->first();
        if (! $shiftBaru) {
            return ['ok' => false, 'pesan' => 'Shift tujuan tidak ditemukan atau tidak aktif.', 'pengajuan' => null];
        }

        if ((int) $shiftBaru->id === (int) $jadwal->shift_id) {
            return ['ok' => false, 'pesan' => 'Shift tujuan sama dengan jadwal saat ini.', 'pengajuan' => null];
        }

        $cek = $this->kelayakan($jadwal, (int) $pemohon->id);
        if (! $cek['ok']) {
            return ['ok' => false, 'pesan' => $cek['pesan'], 'pengajuan' => null];
        }

        $pengajuan = DB::transaction(function () use ($pemohon, $jadwal, $shiftBaru, $alasan) {
            $pj = PengajuanJadwal::create([
                'user_id'         => $pemohon->id,
                'tanggal'         => \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d'),
                'jadwal_shift_id' => $jadwal->id,
                'shift_lama_id'   => $jadwal->shift_id,
                'shift_baru_id'   => $shiftBaru->id,
                'alasan'          => $alasan,
                'status'          => 'Menunggu',
                'created_at'      => now(),
            ]);

            return $pj;
        });

        $this->notifikasiAtasan($pemohon, $pengajuan);
        catat_aktivitas(
            'Pengajuan Ubah Jadwal',
            $pemohon->nama_lengkap . ' — ' . tgl_id($pengajuan->tanggal->format('Y-m-d'), false)
                . ': ' . ($pengajuan->shiftLama?->label() ?? '?') . ' → ' . $shiftBaru->label()
        );

        return [
            'ok'       => true,
            'pesan'    => 'Pengajuan perubahan jadwal terkirim dan menunggu persetujuan atasan langsung.',
            'pengajuan' => $pengajuan,
        ];
    }

    private function notifikasiAtasan(User $pemohon, PengajuanJadwal $pj): void
    {
        $isi = $pemohon->nama_lengkap . ' mengajukan ubah jadwal '
            . tgl_id($pj->tanggal->format('Y-m-d'), false)
            . ' (' . ($pj->shiftLama?->kategori ?? '?') . ' → ' . ($pj->shiftBaru?->kategori ?? '?') . ').';
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
                'url'     => 'admin/jadwal_pengajuan',
                'tipe'    => 'warning',
            ]);
        }
    }

    /**
     * Putuskan pengajuan. Setuju → timpa jadwal_shift dalam transaksi.
     *
     * @return array{ok: bool, pesan: string, status: ?string}
     */
    public function putuskan(PengajuanJadwal $pj, string $putusan, int $olehUserId, ?string $catatan = null): array
    {
        if ($pj->status !== 'Menunggu') {
            return ['ok' => false, 'pesan' => 'Pengajuan sudah diproses sebelumnya.', 'status' => $pj->status];
        }
        $putusan = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';

        [$statusAkhir, $pesan] = DB::transaction(function () use ($pj, $putusan, $olehUserId, $catatan) {
            $jsh = JadwalShift::lockForUpdate()->find($pj->jadwal_shift_id);

            if ($putusan === 'Disetujui'
                && (! $jsh || (int) $jsh->user_id !== (int) $pj->user_id || \Carbon\Carbon::parse($jsh->tanggal_berlaku)->format('Y-m-d') !== $pj->tanggal->format('Y-m-d'))) {
                // Jadwal sasaran hilang/diubah admin saat pengajuan menunggu
                $pj->update([
                    'status'            => 'Ditolak',
                    'diproses_oleh'     => $olehUserId,
                    'catatan_keputusan' => trim(($catatan ? $catatan . ' — ' : '') . 'Jadwal tidak lagi tersedia.'),
                    'diproses_pada'     => now(),
                ]);

                return ['Ditolak', 'Pengajuan ditolak otomatis karena jadwal sudah diubah/dihapus admin.'];
            }

            $pj->update([
                'status'            => $putusan,
                'diproses_oleh'     => $olehUserId,
                'catatan_keputusan' => $catatan,
                'diproses_pada'     => now(),
            ]);

            if ($putusan === 'Disetujui' && $jsh) {
                $jsh->update(['shift_id' => $pj->shift_baru_id, 'diubah_oleh' => $olehUserId]);
            }

            return [$putusan, $putusan === 'Disetujui'
                ? 'Pengajuan disetujui — jadwal pemohon telah diganti.'
                : 'Pengajuan ditolak.'];
        });

        $tipe = $statusAkhir === 'Disetujui' ? 'success' : 'danger';
        Notifikasi::create([
            'user_id' => $pj->user_id,
            'isi'     => 'Pengajuan ubah jadwal ' . tgl_id($pj->tanggal->format('Y-m-d'), false)
                . ' (' . ($pj->shiftLama?->kategori ?? '?') . ' → ' . ($pj->shiftBaru?->kategori ?? '?') . ') '
                . strtolower($statusAkhir) . '.',
            'url'     => 'ubah-jadwal',
            'tipe'    => $tipe,
        ]);

        catat_aktivitas(
            'Keputusan Ubah Jadwal',
            'Pengajuan #' . $pj->id . ' (' . ($pj->user?->nama_lengkap ?? '-') . ') → ' . $statusAkhir
        );

        return ['ok' => true, 'pesan' => $pesan, 'status' => $statusAkhir];
    }

    /**
     * Daftar pengajuan Menunggu yang berwenang diputus user (sebagai atasan langsung).
     */
    public function tugasAtasan(User $atasan): array
    {
        return PengajuanJadwal::with(['user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id', 'user.unitKerja:id,nama', 'user.subUnit:id,nama', 'shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang'])
            ->where('status', 'Menunggu')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->filter(fn (PengajuanJadwal $pj) => $this->isAtasan($atasan, (int) $pj->user_id))
            ->values()
            ->all();
    }
}
