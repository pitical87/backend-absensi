<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Models\Keterlambatan;
use App\Models\PengajuanLupaAbsen;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LupaAbsenService
{
    /** Rentang pemeriksaan alpa/tidak absen ke belakang: 1 bulan terakhir (30 hari). */
    public const RENTANG_HARI = 30;

    /**
     * Semua atasan langsung aktif pegawai: tabel atasan_langsung → sub_unit.atasan_id → unit_kerja.atasan_id.
     *
     * @return Collection<int, User>
     */
    public function atasanListUntuk(int $pegawaiId): Collection
    {
        $pegawai = User::with('unitKerja:id,nama,atasan_id', 'subUnit:id,nama,atasan_id')
            ->find($pegawaiId);
        if (! $pegawai) {
            return collect();
        }

        $dariTabel = User::query()
            ->join('atasan_langsung as al', 'al.atasan_id', '=', 'users.id')
            ->where('al.user_id', $pegawai->id)
            ->where('al.atasan_id', '!=', $pegawai->id)
            ->where('users.status', 'aktif')
            ->whereNull('users.deleted_at')
            ->orderBy('al.id')
            ->get(['users.*']);

        if ($dariTabel->isNotEmpty()) {
            return $dariTabel;
        }

        $atasanId = $pegawai->subUnit?->atasan_id ?: $pegawai->unitKerja?->atasan_id;
        if (! $atasanId || (int) $atasanId === (int) $pegawai->id) {
            return collect();
        }

        $fallback = User::where('id', $atasanId)->where('status', 'aktif')->first();

        return $fallback ? collect([$fallback]) : collect();
    }

    public function isAtasan(User $calonAtasan, int $pegawaiId): bool
    {
        return $this->atasanListUntuk($pegawaiId)
            ->contains(fn (User $a) => (int) $a->id === (int) $calonAtasan->id);
    }

    /**
     * Periksa jadwal & kehadiran pegawai dalam 1 bulan terakhir (30 hari ke belakang s.d. kemarin).
     * Mengembalikan daftar tanggal terjadwal yang tidak absen / alpa.
     *
     * @return array{
     *   ada_jadwal: bool,
     *   ada_alpa: bool,
     *   tampil_tombol: bool,
     *   total_alpa: int,
     *   total_menunggu: int,
     *   periode: array{mulai: string, sampai: string},
     *   tanggal_alpa: array<int, array<string, mixed>>
     * }
     */
    public function cekKelayakanBulanan(User $user, ?Carbon $hariIni = null): array
    {
        $sekarang = ($hariIni ? $hariIni->copy() : Carbon::now())->startOfDay();
        $mulai = $sekarang->copy()->subDays(self::RENTANG_HARI)->toDateString();
        $sampai = $sekarang->copy()->subDay()->toDateString();
        $akhirCekJadwal = $sekarang->copy()->endOfMonth()->toDateString();

        pastikan_libur_tetap((int) substr($mulai, 0, 4));
        if (substr($sampai, 0, 4) !== substr($mulai, 0, 4)) {
            pastikan_libur_tetap((int) substr($sampai, 0, 4));
        }

        $jadwalSemua = app(JadwalEfektifService::class)
            ->petaUntukPegawai($user, $mulai, $akhirCekJadwal);

        $adaJadwal = $jadwalSemua !== [];

        $totalMenunggu = (int) PengajuanLupaAbsen::where('user_id', $user->id)
            ->where('status', 'Menunggu')
            ->count();

        if (! $adaJadwal) {
            return [
                'ada_jadwal' => false,
                'ada_alpa' => false,
                'tampil_tombol' => false,
                'total_alpa' => 0,
                'total_menunggu' => $totalMenunggu,
                'periode' => ['mulai' => $mulai, 'sampai' => $sampai],
                'tanggal_alpa' => [],
            ];
        }

        $liburMap = [];
        foreach (HariLibur::whereBetween('tanggal', [$mulai, $sampai])->get(['tanggal']) as $h) {
            $liburMap[$h->tanggal->format('Y-m-d')] = true;
        }

        $izinMap = [];
        foreach (Izin::where('user_id', $user->id)
            ->where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $sampai)
            ->where('tanggal_selesai', '>=', $mulai)
            ->get(['tanggal_mulai', 'tanggal_selesai']) as $iz) {
            $t = max($iz->tanggal_mulai->timestamp, strtotime($mulai));
            $u = min($iz->tanggal_selesai->timestamp, strtotime($sampai));
            for ($d = $t; $d <= $u; $d += 86400) {
                $izinMap[date('Y-m-d', $d)] = true;
            }
        }

        $absMap = [];
        foreach (Absensi::where('user_id', $user->id)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->pluck('tanggal') as $tglAbs) {
            $absMap[substr((string) $tglAbs, 0, 10)] = true;
        }

        $pengajuanMenungguMap = PengajuanLupaAbsen::where('user_id', $user->id)
            ->where('status', 'Menunggu')
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->get(['id', 'tanggal', 'status'])
            ->keyBy(fn ($p) => $p->tanggal->format('Y-m-d'));

        $shiftIds = array_values(array_unique(array_values($jadwalSemua)));
        $shiftMap = $shiftIds !== []
            ? Shift::whereIn('id', $shiftIds)->get()->keyBy('id')
            : collect();

        $tanggalAlpa = [];
        $akhir = Carbon::parse($sampai);
        for ($cursor = Carbon::parse($mulai); $cursor->lte($akhir); $cursor->addDay()) {
            $tgl = $cursor->toDateString();

            if (! isset($jadwalSemua[$tgl])) {
                continue;
            }
            if (isset($liburMap[$tgl]) || isset($izinMap[$tgl]) || isset($absMap[$tgl])) {
                continue;
            }

            $shift = $shiftMap->get((int) $jadwalSemua[$tgl]);
            $pending = $pengajuanMenungguMap->get($tgl);

            $tanggalAlpa[] = [
                'tanggal' => $tgl,
                'hari' => $cursor->locale('id')->translatedFormat('l'),
                'shift' => $shift ? [
                    'id' => (int) $shift->id,
                    'kategori' => $shift->kategori,
                    'jam_masuk' => $shift->jam_masuk?->format('H:i'),
                    'jam_pulang' => $shift->jam_pulang?->format('H:i'),
                    'lintas_hari' => (bool) $shift->lintas_hari,
                ] : null,
                'sudah_diajukan' => $pending !== null,
                'pengajuan_id' => $pending ? (int) $pending->id : null,
                'status_pengajuan' => $pending?->status,
            ];
        }

        // Urutkan dari tanggal terbaru ke terlama agar mudah dipilih pegawai.
        $tanggalAlpa = array_reverse($tanggalAlpa);
        $totalAlpa = count($tanggalAlpa);
        $adaAlpa = $totalAlpa > 0;

        return [
            'ada_jadwal' => true,
            'ada_alpa' => $adaAlpa,
            'tampil_tombol' => $adaAlpa,
            'total_alpa' => $totalAlpa,
            'total_menunggu' => $totalMenunggu,
            'periode' => ['mulai' => $mulai, 'sampai' => $sampai],
            'tanggal_alpa' => $tanggalAlpa,
        ];
    }

    /**
     * Simpan pengajuan lupa absen baru + kirim notifikasi ke atasan langsung DAN admin.
     *
     * @return array{ok: bool, pesan: string, pengajuan: ?PengajuanLupaAbsen}
     */
    public function simpan(
        User $pemohon,
        string $tanggal,
        string $alasan,
        ?string $lampiran = null
    ): array {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return ['ok' => false, 'pesan' => 'Tanggal lupa absen tidak valid.', 'pengajuan' => null];
        }

        $alasan = trim($alasan);
        if ($alasan === '') {
            return ['ok' => false, 'pesan' => 'Alasan/keterangan lupa absen wajib diisi.', 'pengajuan' => null];
        }

        $cek = $this->cekKelayakanBulanan($pemohon);
        if (! $cek['ada_jadwal']) {
            return ['ok' => false, 'pesan' => 'Anda belum memiliki jadwal kerja pada periode 1 bulan terakhir.', 'pengajuan' => null];
        }

        $target = collect($cek['tanggal_alpa'])->firstWhere('tanggal', $tanggal);
        if (! $target) {
            if ($tanggal >= now()->toDateString()) {
                return ['ok' => false, 'pesan' => 'Lupa absen hanya dapat diajukan untuk tanggal yang sudah terlewat.', 'pengajuan' => null];
            }
            if ($tanggal < $cek['periode']['mulai']) {
                return ['ok' => false, 'pesan' => 'Tanggal lupa absen berada di luar rentang 1 bulan terakhir.', 'pengajuan' => null];
            }
            if (Absensi::where('user_id', $pemohon->id)->whereDate('tanggal', $tanggal)->exists()) {
                return ['ok' => false, 'pesan' => 'Anda sudah memiliki catatan kehadiran pada tanggal tersebut.', 'pengajuan' => null];
            }
            if (Izin::where('user_id', $pemohon->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal)
                ->exists()) {
                return ['ok' => false, 'pesan' => 'Anda tercatat izin/cuti/sakit pada tanggal tersebut.', 'pengajuan' => null];
            }

            return ['ok' => false, 'pesan' => 'Tidak ditemukan status alpa / jadwal kerja aktif pada tanggal tersebut.', 'pengajuan' => null];
        }

        $adaAktif = PengajuanLupaAbsen::where('user_id', $pemohon->id)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('status', ['Menunggu', 'Disetujui'])
            ->exists();
        if ($adaAktif) {
            return ['ok' => false, 'pesan' => 'Anda sudah memiliki pengajuan lupa absen aktif/disetujui pada tanggal tersebut.', 'pengajuan' => null];
        }

        $shiftId = $target['shift']['id'] ?? null;

        $pengajuan = DB::transaction(function () use ($pemohon, $tanggal, $shiftId, $alasan, $lampiran) {
            return PengajuanLupaAbsen::create([
                'user_id' => $pemohon->id,
                'tanggal' => $tanggal,
                'shift_id' => $shiftId,
                'alasan' => $alasan,
                'lampiran' => $lampiran,
                'status' => 'Menunggu',
                'created_at' => now(),
            ]);
        });

        $this->notifikasiPengajuanBaru($pemohon, $pengajuan);

        catat_aktivitas(
            'Pengajuan Lupa Absen',
            $pemohon->nama_lengkap.' — '.tgl_id($tanggal, false)
        );

        $atasanNama = $this->atasanListUntuk((int) $pemohon->id)->pluck('nama_lengkap');
        $pesan = $atasanNama->isNotEmpty()
            ? 'Pengajuan lupa absen terkirim ke atasan langsung ('.$atasanNama->implode(', ').') dan admin untuk diverifikasi.'
            : 'Pengajuan lupa absen terkirim ke admin untuk diverifikasi.';

        return [
            'ok' => true,
            'pesan' => $pesan,
            'pengajuan' => $pengajuan,
        ];
    }

    /**
     * Kirim notifikasi pengajuan baru ke SEMUA atasan langsung aktif DAN SEMUA admin aktif.
     */
    private function notifikasiPengajuanBaru(User $pemohon, PengajuanLupaAbsen $pj): void
    {
        $tglTeks = tgl_id($pj->tanggal->format('Y-m-d'), false);
        $isi = $pemohon->nama_lengkap.' mengajukan lupa absen untuk tanggal '.$tglTeks.'.';

        $atasanList = $this->atasanListUntuk((int) $pemohon->id);
        foreach ($atasanList as $atasan) {
            buat_notifikasi((int) $atasan->id, $isi, 'warning', 'persetujuan', 'lupa_absen');
        }

        $adminIds = User::where('role', 'admin')
            ->where('status', 'aktif')
            ->pluck('id');
        foreach ($adminIds as $adminId) {
            buat_notifikasi((int) $adminId, $isi, 'warning', 'admin/lupa_absen', 'lupa_absen');
        }
    }

    /**
     * Verifikasi / putuskan pengajuan lupa absen.
     * Bila disetujui, otomatis tersimpan di tabel absensi (tanpa foto, tanpa koordinat,
     * bintang 1 di absen masuk dan absen pulang).
     *
     * @return array{ok: bool, pesan: string, status: ?string, absensi_id: ?int}
     */
    public function putuskan(
        PengajuanLupaAbsen $pj,
        string $putusan,
        int $olehUserId,
        ?string $catatan = null
    ): array {
        if ($pj->status !== 'Menunggu') {
            return [
                'ok' => false,
                'pesan' => 'Pengajuan sudah diproses sebelumnya.',
                'status' => $pj->status,
                'absensi_id' => $pj->absensi_id,
            ];
        }

        $statusBaru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';

        $hasilTx = DB::transaction(function () use ($pj, $statusBaru, $olehUserId, $catatan) {
            $terkunci = PengajuanLupaAbsen::lockForUpdate()->find($pj->id);
            if (! $terkunci || $terkunci->status !== 'Menunggu') {
                return [
                    'ok' => false,
                    'pesan' => 'Pengajuan sudah diproses sebelumnya.',
                    'status' => $terkunci?->status,
                    'absensi_id' => $terkunci?->absensi_id,
                ];
            }

            $absensiId = null;

            if ($statusBaru === 'Disetujui') {
                $pemohon = User::with('profesi')->where('id', $terkunci->user_id)->lockForUpdate()->first();
                if (! $pemohon) {
                    return [
                        'ok' => false,
                        'pesan' => 'Data pegawai pemohon tidak ditemukan.',
                        'status' => $terkunci->status,
                        'absensi_id' => null,
                    ];
                }

                $tanggal = $terkunci->tanggal->format('Y-m-d');
                $sudahAda = Absensi::where('user_id', $pemohon->id)
                    ->whereDate('tanggal', $tanggal)
                    ->exists();
                if ($sudahAda) {
                    return [
                        'ok' => false,
                        'pesan' => 'Pegawai sudah memiliki catatan kehadiran pada tanggal '.$tanggal.'.',
                        'status' => $terkunci->status,
                        'absensi_id' => null,
                    ];
                }

                $shift = $terkunci->shift_id
                    ? Shift::find($terkunci->shift_id)
                    : app(JadwalEfektifService::class)->shiftUntukTanggal($pemohon, $tanggal);

                $jamMasuk = $shift?->jam_masuk?->format('H:i:s') ?? '08:00:00';
                $jamPulang = $shift?->jam_pulang?->format('H:i:s') ?? '16:00:00';
                $isLintasHari = (bool) ($shift?->lintas_hari ?? false);
                if (! $isLintasHari && $jamPulang <= $jamMasuk) {
                    $isLintasHari = true;
                }

                $waktuMasuk = new DateTime($tanggal.' '.$jamMasuk);
                $waktuPulang = new DateTime($tanggal.' '.$jamPulang);
                if ($isLintasHari) {
                    $waktuPulang->modify('+1 day');
                }

                $isDokter = $pemohon->profesi?->nama === 'Dokter';
                $totalMenit = $isDokter
                    ? null
                    : max(0, (int) floor(($waktuPulang->getTimestamp() - $waktuMasuk->getTimestamp()) / 60));

                $absensi = Absensi::create([
                    'user_id' => $pemohon->id,
                    'sesi' => 1,
                    'sumber' => 'lupa_absen',
                    'tanggal' => $tanggal,
                    'waktu_masuk' => $waktuMasuk->format('Y-m-d H:i:s'),
                    'waktu_pulang' => $waktuPulang->format('Y-m-d H:i:s'),
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'lat_pulang' => null,
                    'lng_pulang' => null,
                    'foto_masuk' => null,
                    'foto_pulang' => null,
                    'status_masuk' => $isDokter ? null : 'Tepat Waktu',
                    'menit_terlambat' => 0,
                    'status_pulang' => $isDokter ? null : 'Tepat Waktu',
                    'menit_awal_pulang' => 0,
                    'bintang_masuk' => 1,
                    'bintang_pulang' => 1,
                    'bintang_harian' => 1.0,
                    'total_menit_kerja' => $totalMenit,
                    'flag_anomali' => false,
                    'catatan_anomali' => 'Lupa absen disetujui',
                ]);
                $absensiId = (int) $absensi->id;

                if (! $isDokter) {
                    Keterlambatan::updateOrCreate(
                        ['absensi_id' => $absensiId],
                        [
                            'menit_telat' => 0,
                            'menit_awal_pulang' => 0,
                            'bintang_masuk' => 1,
                            'bintang_pulang' => 1,
                            'total_bintang' => 1.0,
                        ]
                    );
                }
            }

            $terkunci->update([
                'status' => $statusBaru,
                'diproses_oleh' => $olehUserId,
                'catatan_keputusan' => $catatan,
                'diproses_pada' => now(),
                'absensi_id' => $absensiId,
            ]);

            return [
                'ok' => true,
                'pesan' => $statusBaru === 'Disetujui'
                    ? 'Pengajuan lupa absen disetujui dan tercatat di kehadiran (tanpa foto & koordinat, bintang 1 masuk & pulang).'
                    : 'Pengajuan lupa absen ditolak.',
                'status' => $statusBaru,
                'absensi_id' => $absensiId,
            ];
        });

        if (! $hasilTx['ok']) {
            return $hasilTx;
        }

        $pj->refresh();
        $tipe = $statusBaru === 'Disetujui' ? 'success' : 'danger';
        $namaPemroses = $olehUserId > 0
            ? User::where('id', $olehUserId)->value('nama_lengkap')
            : null;
        $tglTeks = tgl_id($pj->tanggal->format('Y-m-d'), false);

        buat_notifikasi(
            (int) $pj->user_id,
            'Pengajuan lupa absen tanggal '.$tglTeks.' '.strtolower($statusBaru)
                .($namaPemroses ? ' oleh '.$namaPemroses : '')
                .($statusBaru === 'Disetujui' ? ' (kehadiran tercatat dengan bintang 1 masuk & pulang)' : '')
                .($catatan ? ' — '.$catatan : '').'.',
            $tipe,
            'lupa-absen',
            'lupa_absen',
        );

        catat_aktivitas(
            'Keputusan Lupa Absen',
            'Pengajuan #'.$pj->id.' ('.($pj->user?->nama_lengkap ?? '-').' — '.$tglTeks.') → '.$statusBaru
        );

        return $hasilTx;
    }

    /**
     * Daftar pengajuan Menunggu yang berwenang diputus user (sebagai atasan langsung atau admin).
     *
     * @return array<int, PengajuanLupaAbsen>
     */
    public function tugasAtasan(User $atasan): array
    {
        $query = PengajuanLupaAbsen::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama',
            'user.subUnit:id,nama',
            'shift:id,kategori,jam_masuk,jam_pulang,lintas_hari',
        ])
            ->where('status', 'Menunggu')
            ->where('user_id', '!=', $atasan->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        if ($atasan->role === 'admin') {
            return $query->values()->all();
        }

        return $query
            ->filter(fn (PengajuanLupaAbsen $pj) => $this->isAtasan($atasan, (int) $pj->user_id))
            ->values()
            ->all();
    }
}
