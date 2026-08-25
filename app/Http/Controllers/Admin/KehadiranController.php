<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\JadwalShift;
use App\Models\LogLokasi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BintangService;
use DateTime;
use Illuminate\Http\Request;

class KehadiranController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = (string) $request->get('tanggal');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = now()->format('Y-m-d');
        }
        $fUnit = (int) $request->get('unit');
        $hanyaAnomali = $request->get('anomali') === '1';
        $q = trim((string) $request->get('q'));
        $fStatus = (string) $request->get('status');

        $b = Absensi::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama',
            'user.subUnit:id,nama',
            'logLokasiDatang' => fn ($l) => $l->select('id', 'jarak_meter', 'tipe'),
        ])
            ->whereDate('absensi.tanggal', $tanggal);
        if ($q !== '') {
            $b->whereHas('user', function ($u) use ($q) {
                $u->where('nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('nip', 'like', "%{$q}%");
            });
        }
        if ($fStatus === 'tepat' || $fStatus === 'terlambat') {
            $b->where('absensi.status_masuk', $fStatus === 'tepat' ? 'Tepat Waktu' : 'Terlambat');
        }
        if ($fUnit) {
            $b->whereHas('user', fn ($u) => $u->where('unit_kerja_id', $fUnit));
        }
        if ($hanyaAnomali) {
            $b->where('absensi.flag_anomali', 1);
        }
        $rows = $b->orderBy('absensi.waktu_masuk')->get();

        $shiftHariIni = JadwalShift::query()
            ->whereIn('user_id', $rows->pluck('user_id')->unique())
            ->whereDate('tanggal_berlaku', $tanggal)
            ->with('shift:id,kategori,jam_masuk,jam_pulang')
            ->get()
            ->keyBy('user_id');
        foreach ($rows as $r) {
            $r->setRelation('shiftHariIni', $shiftHariIni);
        }

        $ditolak = LogLokasi::query()
            ->with('user:id,nama_lengkap')
            ->where('log_lokasi.ditolak', 1)
            ->whereDate('log_lokasi.waktu', $tanggal)
            ->orderBy('log_lokasi.waktu')
            ->get();

        $titik = [];
        foreach ($rows as $r) {
            if ($r->lat_masuk !== null) {
                $titik[] = ['nama' => $r->user->nama_lengkap, 'tipe' => 'Datang',
                    'lat' => (float) $r->lat_masuk, 'lng' => (float) $r->lng_masuk,
                    'jam' => jam_id($r->waktu_masuk), 'anomali' => (bool) $r->flag_anomali];
            }
            if ($r->lat_pulang !== null) {
                $titik[] = ['nama' => $r->user->nama_lengkap, 'tipe' => 'Pulang',
                    'lat' => (float) $r->lat_pulang, 'lng' => (float) $r->lng_pulang,
                    'jam' => jam_id($r->waktu_pulang), 'anomali' => (bool) $r->flag_anomali];
            }
        }

        return view('admin.kehadiran', [
            'judulHalaman' => 'Data Kehadiran',
            'menuAktif' => 'kehadiran',
            'tanggal' => $tanggal,
            'fUnit' => $fUnit,
            'hanyaAnomali' => $hanyaAnomali,
            'q' => $q,
            'fStatus' => $fStatus,
            'rows' => $rows,
            'ditolak' => $ditolak,
            'titik' => $titik,
            'pegawaiList' => User::where('role', '!=', 'admin')->where('status', 'aktif')
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap'])->all(),
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'rsLat' => (float) pengaturan('lokasi_lat', 0),
            'rsLng' => (float) pengaturan('lokasi_lng', 0),
            'radius' => (float) pengaturan('radius_meter', 100),
            'hariLiburSet' => HariLibur::whereBetween('tanggal', [
                now()->subYear()->format('Y-m-d'), now()->addYears(2)->format('Y-m-d'),
            ])->get()->mapWithKeys(fn ($h) => [$h->tanggal->toDateString() => true])->all(),
        ]);
    }

    public function simpan(Request $request)
    {
        $id = (int) $request->input('id');
        $userId = (int) $request->input('user_id');

        $user = User::find($userId);
        if (! $user || $user->role === 'admin') {
            return redirect()->back()->with('error', 'Pegawai tidak ditemukan.');
        }

        $tanggalMulai = (string) $request->input('tanggal');
        $jamMasuk = trim((string) $request->input('jam_masuk'));
        $jamPulang = trim((string) $request->input('jam_pulang'));

        if ($id) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)) {
                return redirect()->back()->with('error', 'Tanggal tidak valid.');
            }
            if ($jamMasuk === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamMasuk)) {
                return redirect()->back()->with('error', 'Jam masuk wajib diisi dengan format HH:MM.');
            }
            if ($jamPulang !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamPulang)) {
                return redirect()->back()->with('error', 'Format jam pulang tidak valid (HH:MM).');
            }

            $absen = Absensi::find($id);
            if (! $absen) {
                return redirect()->back()->with('error', 'Data absensi tidak ditemukan.');
            }
            $bentrok = Absensi::where('user_id', $userId)
                ->whereDate('tanggal', $tanggalMulai)
                ->where('id', '!=', $id)
                ->exists();
            if ($bentrok) {
                return redirect()->back()->with('error',
                    $user->nama_lengkap.' sudah memiliki catatan absensi pada tanggal tersebut.');
            }

            $absen->fill($this->bangunData($userId, $tanggalMulai, $jamMasuk, $jamPulang))->save();
            catat_aktivitas('Ubah Absensi Manual',
                $user->nama_lengkap.' — '.tgl_id($absen->tanggal).' '.$jamMasuk.($jamPulang ? '-'.$jamPulang : ''));

            return redirect()->back()->with('success', 'Data absensi diperbarui.');
        }

        $hari = $request->input('hari');
        if (is_array($hari) && $hari !== []) {
            return $this->simpanBanyak($user, $hari);
        }

        $tanggalSelesai = trim((string) $request->input('tanggal_selesai', ''));
        if ($tanggalSelesai === '') {
            $tanggalSelesai = $tanggalMulai;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)) {
            return redirect()->back()->with('error', 'Tanggal tidak valid.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)) {
            return redirect()->back()->with('error', 'Tanggal selesai tidak valid.');
        }
        if ($jamMasuk === '' || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamMasuk)) {
            return redirect()->back()->with('error', 'Jam masuk wajib diisi dengan format HH:MM.');
        }
        if ($jamPulang !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamPulang)) {
            return redirect()->back()->with('error', 'Format jam pulang tidak valid (HH:MM).');
        }

        $mulai = DateTime::createFromFormat('Y-m-d', $tanggalMulai);
        $akhir = DateTime::createFromFormat('Y-m-d', $tanggalSelesai);
        if (! $mulai || ! $akhir || $mulai->format('Y-m-d') !== $tanggalMulai || $akhir->format('Y-m-d') !== $tanggalSelesai) {
            return redirect()->back()->with('error', 'Tanggal tidak valid.');
        }
        if ($akhir < $mulai) {
            return redirect()->back()->with('error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
        }
        if ((int) $mulai->diff($akhir)->days > 30) {
            return redirect()->back()->with('error', 'Rentang maksimal satu bulan (31 hari).');
        }

        pastikan_libur_tetap((int) $mulai->format('Y'));
        $liburSet = [];
        foreach (HariLibur::whereBetween('tanggal', [$mulai->format('Y-m-d'), $akhir->format('Y-m-d')])->get() as $h) {
            $liburSet[$h->tanggal->format('Y-m-d')] = true;
        }
        $mingguLibur = pengaturan('minggu_libur', '0') === '1';

        $sudahAda = Absensi::where('user_id', $userId)
            ->whereBetween('tanggal', [$mulai->format('Y-m-d'), $akhir->format('Y-m-d')])
            ->get()
            ->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));

        $ditambah = 0;
        $terlewati = 0;
        $liburDilompati = 0;
        $contohSudah = null;

        for ($d = clone $mulai; $d <= $akhir; $d->modify('+1 day')) {
            $tgl = $d->format('Y-m-d');

            if (isset($liburSet[$tgl]) || ($mingguLibur && (int) $d->format('w') === 0)) {
                $liburDilompati++;

                continue;
            }
            if (isset($sudahAda[$tgl])) {
                $terlewati++;
                $contohSudah ??= tgl_id($tgl, false);

                continue;
            }

            Absensi::create($this->bangunData($userId, $tgl, $jamMasuk, $jamPulang));
            $ditambah++;
        }

        if ($ditambah === 0) {
            if ($terlewati > 0) {
                return redirect()->back()->with('error',
                    $user->nama_lengkap.' sudah memiliki catatan absensi pada '.$contohSudah
                    .($terlewati > 1 ? ' dan '.($terlewati - 1).' tanggal lain dalam rentang ini' : '').'.');
            }

            return redirect()->back()->with('error',
                'Seluruh tanggal dalam rentang merupakan hari libur. Tidak ada absensi yang dibuat.');
        }

        catat_aktivitas('Tambah Absensi Manual',
            $user->nama_lengkap.' — '.$tanggalMulai.' s.d. '.$tanggalSelesai.' '
            .$jamMasuk.($jamPulang ? '-'.$jamPulang : '').' ('.$ditambah.' hari)');

        $pesan = $ditambah.' hari absensi '.$user->nama_lengkap.' berhasil ditambahkan';
        if ($terlewati > 0) {
            $pesan .= ', '.$terlewati.' tanggal dilewati karena sudah ada catatan';
        }
        if ($liburDilompati > 0) {
            $pesan .= ', '.$liburDilompati.' hari Minggu/libur diabaikan';
        }

        return redirect()->back()->with('success', $pesan.'.');
    }

    private function simpanBanyak(User $user, array $hari)
    {
        $baris = [];
        foreach ($hari as $r) {
            $tgl = trim((string) ($r['tanggal'] ?? ''));
            $masuk = trim((string) ($r['masuk'] ?? ''));
            $pulang = trim((string) ($r['pulang'] ?? ''));

            if ($tgl === '' || ($masuk === '' && $pulang === '')) {
                continue;
            }
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
                return redirect()->back()->with('error', 'Ada tanggal tidak valid pada daftar hari.');
            }
            if ($masuk === '') {
                return redirect()->back()->with('error',
                    'Jam masuk wajib diisi bila jam pulang terisi ('.tgl_id($tgl, false).').');
            }
            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $masuk)) {
                return redirect()->back()->with('error',
                    'Format jam masuk tidak valid pada '.tgl_id($tgl, false).' (HH:MM).');
            }
            if ($pulang !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $pulang)) {
                return redirect()->back()->with('error',
                    'Format jam pulang tidak valid pada '.tgl_id($tgl, false).' (HH:MM).');
            }
            $baris[$tgl] = [$masuk, $pulang];
        }

        if ($baris === []) {
            return redirect()->back()->with('error', 'Tidak ada baris absensi yang diisi. Isi jam masuk minimal satu tanggal.');
        }

        ksort($baris);
        $mulai = array_key_first($baris);
        $akhir = array_key_last($baris);
        if ((int) (new DateTime($mulai))->diff(new DateTime($akhir))->days > 30) {
            return redirect()->back()->with('error', 'Rentang maksimal satu bulan (31 hari).');
        }

        pastikan_libur_tetap((int) (new DateTime($mulai))->format('Y'));

        $sudahAda = Absensi::where('user_id', (int) $user->id)
            ->whereBetween('tanggal', [$mulai, $akhir])
            ->get()
            ->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));

        $ditambah = 0;
        $terlewati = 0;
        $contohSudah = null;

        foreach ($baris as $tgl => $jam) {
            if (isset($sudahAda[$tgl])) {
                $terlewati++;
                $contohSudah ??= tgl_id($tgl, false);

                continue;
            }
            Absensi::create($this->bangunData((int) $user->id, $tgl, $jam[0], $jam[1]));
            $ditambah++;
        }

        if ($ditambah === 0) {
            return redirect()->back()->with('error',
                $user->nama_lengkap.' sudah memiliki catatan absensi pada '.$contohSudah
                .($terlewati > 1 ? ' dan '.($terlewati - 1).' tanggal lain' : '')
                .'. Tidak ada data baru yang ditambahkan.');
        }

        catat_aktivitas('Tambah Absensi Manual',
            $user->nama_lengkap.' — '.$mulai.' s.d. '.$akhir.' ('.$ditambah.' hari, per hari)');

        $pesan = $ditambah.' hari absensi '.$user->nama_lengkap.' berhasil ditambahkan';
        if ($terlewati > 0) {
            $pesan .= ', '.$terlewati.' tanggal dilewati karena sudah ada catatan';
        }

        return redirect()->back()->with('success', $pesan.'.');
    }

    private function bangunData(int $userId, string $tanggal, string $jamMasuk, string $jamPulang): array
    {
        $waktuMasuk = new DateTime($tanggal.' '.$jamMasuk);
        $waktuPulang = $jamPulang !== '' ? new DateTime($tanggal.' '.$jamPulang) : null;

        $isDokter = User::find($userId)?->profesi?->nama === 'Dokter';

        $js = JadwalShift::where('user_id', $userId)->where('tanggal_berlaku', $tanggal)->first();
        $shift = $js?->shift;

        $toleransi = (int) pengaturan('toleransi_menit', 5);
        $statusMasuk = null;
        $menitTerlambat = 0;
        $statusPulang = null;
        $menitAwal = 0;
        $bintangMasuk = null;
        $bintangPulang = null;
        $bintangHarian = null;
        $totalMenit = null;

        if (! $isDokter && $shift) {
            $jadwalMasuk = new DateTime($tanggal.' '.date('H:i', strtotime($shift->jam_masuk)));
            $selisih = ceil(($waktuMasuk->getTimestamp() - $jadwalMasuk->getTimestamp()) / 60);

            $statusMasuk = $selisih <= $toleransi ? 'Tepat Waktu' : 'Terlambat';
            $menitTerlambat = max(0, (int) $selisih);
            $bintangMasuk = app(BintangService::class)->bintangMasuk((int) $selisih);
        }

        if ($waktuPulang && ! $isDokter) {
            $totalMenit = max(0, (int) floor(($waktuPulang->getTimestamp() - $waktuMasuk->getTimestamp()) / 60));

            if ($shift) {
                $jamShiftMasuk = date('H:i', strtotime($shift->jam_masuk));
                $jamShiftPulang = date('H:i', strtotime($shift->jam_pulang));

                $menitAwal = app(BintangService::class)->selisihMenitPulang(
                    $jamShiftMasuk, $jamShiftPulang, $tanggal, $waktuPulang
                );
                $bintangPulang = app(BintangService::class)->bintangPulang($menitAwal);
                $statusPulang = $menitAwal > 0 ? 'Lebih Awal' : 'Tepat Waktu';
                $bintangHarian = $bintangMasuk !== null
                    ? app(BintangService::class)->bintangHarian($bintangMasuk, $bintangPulang)
                    : null;
            }
        }

        return [
            'user_id' => $userId,
            'tanggal' => $tanggal,
            'waktu_masuk' => $waktuMasuk->format('Y-m-d H:i:s'),
            'waktu_pulang' => $waktuPulang?->format('Y-m-d H:i:s'),
            'status_masuk' => $isDokter ? null : ($statusMasuk ?? 'Tepat Waktu'),
            'menit_terlambat' => $menitTerlambat,
            'total_menit_kerja' => $totalMenit,
            'status_pulang' => $statusPulang,
            'menit_awal_pulang' => $menitAwal,
            'bintang_masuk' => $bintangMasuk,
            'bintang_pulang' => $bintangPulang,
            'bintang_harian' => $bintangHarian,
        ];
    }

    public function hapus(Request $request)
    {
        $absen = Absensi::find((int) $request->input('id'));
        if ($absen) {
            $nama = (string) User::where('id', $absen->user_id)->value('nama_lengkap');
            $detail = $nama !== '' ? $nama.' — '.tgl_id($absen->tanggal) : 'ID #'.$absen->id;
            $absen->delete();
            catat_aktivitas('Hapus Absensi', $detail);
        }

        return redirect()->back()->with('success', 'Data absensi dihapus.');
    }
}
