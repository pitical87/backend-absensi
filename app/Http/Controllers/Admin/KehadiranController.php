<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AbsenMencurigakanMail;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\LogLokasi;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\BintangService;
use DateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class KehadiranController extends Controller
{
    /** Batas baris untuk daftar percobaan absen yang ditolak. */
    private const BATAS_BARIS = 200;

    /** Jumlah catatan kehadiran per halaman tabel. */
    private const PER_HALAMAN = 15;

    /** Batas titik yang digambar di peta sebaran (peta tetap utuh lintas halaman). */
    private const BATAS_TITIK = 500;

    /** Absen mencurigakan: jam sama diulang lebih dari sekian hari dalam sebulan. */
    private const AMBANG_JAM_SAMA = 3;

    /** Absen mencurigakan: koordinat sama diulang lebih dari sekian kali dalam sebulan. */
    private const AMBANG_KOORDINAT_SAMA = 5;

    public function index(Request $request)
    {
        $filter = $this->filter($request);

        // Tabel kehadiran dan panel percobaan dimuat lazy lewat endpoint
        // (data/percobaan) supaya request halaman ini ringan: tanpa menghitung
        // seluruh catatan absensi dan titik peta pada hari yang dipilih.
        return view('admin.kehadiran.index', [
            'judulHalaman' => 'Data Kehadiran',
            'menuAktif' => 'kehadiran',
            'tanggal' => $filter['tanggal'],
            'fUnit' => $filter['unit'],
            'q' => $filter['q'],
            'fStatus' => $filter['status'],
            'pegawaiList' => User::where('role', '!=', 'admin')->where('status', 'aktif')
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap'])->all(),
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'rsLat' => (float) pengaturan('lokasi_lat', 0),
            'rsLng' => (float) pengaturan('lokasi_lng', 0),
            'radius' => (float) pengaturan('radius_meter', 100),
            'hariLiburSet' => $this->daftarLibur(),
            'tahunList' => $this->daftarTahun(),
        ]);
    }

    /**
     * Endpoint asinkron: daftar absen mencurigakan pada satu bulan. Deteksi
     * dilakukan di PHP (bukan GROUP BY SQL) supaya tetap portabel lintas driver
     * database (SQLite/MySQL) dan mudah ditelusuri.
     */
    public function mencurigakan(Request $request)
    {
        if (! $request->expectsJson()) {
            return back();
        }

        $bulan = (int) $request->get('bulan', now()->month);
        $tahun = (int) $request->get('tahun', now()->year);
        if ($bulan < 1 || $bulan > 12) {
            $bulan = (int) now()->month;
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = (int) now()->year;
        }

        $rows = $this->penggunaMencurigakan($bulan, $tahun);

        return response()->json([
            'sukses' => true,
            'total' => count($rows),
            'label' => BULAN_ID[$bulan].' '.$tahun,
            'html' => view('admin.kehadiran.mencurigakan_rows', ['rows' => $rows])->render(),
        ]);
    }

    public function kirimNotifikasiMencurigakan(Request $request)
    {
        $ids = $request->input('user_ids');
        if (! is_array($ids) || $ids === []) {
            return response()->json(['sukses' => false, 'pesan' => 'Pilih minimal satu pegawai.'], 422);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            $ids
        ))));
        if ($ids === []) {
            return response()->json(['sukses' => false, 'pesan' => 'Pilihan pegawai tidak valid.'], 422);
        }

        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');
        if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
            return response()->json(['sukses' => false, 'pesan' => 'Periode pemeriksaan tidak valid.'], 422);
        }

        $mencurigakan = collect($this->penggunaMencurigakan($bulan, $tahun))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $penerima = array_values(array_intersect($ids, $mencurigakan));
        if (count($penerima) !== count($ids)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Daftar pegawai terpilih sudah berubah. Silakan scan ulang sebelum mengirim notifikasi.',
            ], 422);
        }

        $isi = 'Terdapat pola absensi mencurigakan pada akun anda di '.BULAN_ID[$bulan].' '.$tahun
            .'.';
        $terkirim = buat_notifikasi_banyak($penerima, $isi, 'warning', null, 'keamanan');
        if ($terkirim === 0) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Notifikasi tidak berhasil disimpan. Silakan coba lagi.',
            ], 500);
        }

        catat_aktivitas('Kirim Notifikasi Absen Mencurigakan', $terkirim.' pegawai — '.BULAN_ID[$bulan].' '.$tahun);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Notifikasi berhasil dikirim ke '.$terkirim.' pegawai.',
            'terkirim' => $terkirim,
        ]);
    }

    public function kirimEmailMencurigakan(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'subjek' => ['required', 'string', 'max:180', 'not_regex:/[\\r\\n]/'],
            'pesan' => ['required', 'string', 'max:10000'],
        ]);

        $ids = array_map('intval', $validated['user_ids']);
        $mencurigakan = collect($this->penggunaMencurigakan(
            (int) $validated['bulan'],
            (int) $validated['tahun'],
        ))->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        if (array_diff($ids, $mencurigakan) !== []) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Daftar pegawai terpilih sudah berubah. Silakan scan ulang sebelum mengirim email.',
            ], 422);
        }

        $penerima = User::whereIn('id', $ids)
            ->where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->get(['id', 'nama_lengkap', 'email'])
            ->keyBy('id');
        if ($penerima->count() !== count($ids)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Sebagian pegawai tidak ditemukan atau sudah tidak aktif. Scan ulang sebelum mengirim email.',
            ], 422);
        }

        $tanpaEmail = $penerima->filter(fn (User $user) => ! filter_var($user->email, FILTER_VALIDATE_EMAIL));
        if ($tanpaEmail->isNotEmpty()) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Email belum tersedia atau tidak valid untuk: '.$tanpaEmail->pluck('nama_lengkap')->implode(', '),
            ], 422);
        }

        $terkirim = 0;
        $gagal = [];
        foreach ($penerima as $user) {
            try {
                Mail::to($user->email)->send(new AbsenMencurigakanMail(
                    $user->nama_lengkap,
                    $validated['pesan'],
                    $validated['subjek'],
                ));
                $terkirim++;
            } catch (Throwable $e) {
                report($e);
                $gagal[] = $user->nama_lengkap;
            }
        }

        if ($terkirim === 0) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Email tidak berhasil dikirim ke penerima mana pun. Periksa konfigurasi email lalu coba lagi.',
            ], 500);
        }

        catat_aktivitas(
            'Kirim Email Absen Mencurigakan',
            $terkirim.' terkirim, '.count($gagal).' gagal — '.BULAN_ID[(int) $validated['bulan']].' '.$validated['tahun']
        );

        return response()->json([
            'sukses' => $gagal === [],
            'pesan' => $gagal === []
                ? 'Email berhasil dikirim ke '.$terkirim.' pegawai.'
                : 'Email terkirim ke '.$terkirim.' pegawai, tetapi gagal ke: '.implode(', ', $gagal).'.',
            'terkirim' => $terkirim,
            'gagal' => count($gagal),
        ], $gagal === [] ? 200 : 502);
    }

    /**
     * Endpoint asinkron: satu halaman tabel kehadiran tanpa memuat ulang
     * halaman. Balasannya HTML tabel yang sudah dirender server supaya tombol
     * pada setiap baris (Ubah, Hapus, detail anomali) tetap hidup, plus HTML
     * pagination dan titik peta untuk hari yang dipilih.
     */
    public function data(Request $request)
    {
        if (! $request->expectsJson()) {
            return back();
        }

        $filter = $this->filter($request);
        $hal = max(1, (int) $request->get('hal', 1));
        $rows = $this->halamanAbsensi($filter, $hal);

        return response()->json([
            'sukses' => true,
            'tanggal' => $filter['tanggal'],
            'labelTanggal' => tgl_id($filter['tanggal']),
            'total' => $rows->total(),
            'jumlah' => $rows->count(),
            'hal' => $rows->currentPage(),
            'totalHal' => $rows->lastPage(),
            'html' => view('admin.kehadiran.rows', ['rows' => $rows])->render(),
            'paginasi' => view('admin.bersama.paginasi', ['rows' => $rows, 'satuan' => 'catatan'])->render(),
            'titik' => $this->titikAbsensi($filter),
        ]);
    }

    /** Endpoint asinkron: tabel percobaan absen yang ditolak pada satu tanggal. */
    public function percobaan(Request $request)
    {
        if (! $request->expectsJson()) {
            return back();
        }

        $tanggal = $this->filter($request)['tanggal'];
        $ditolak = $this->daftarPercobaan($tanggal);

        return response()->json([
            'sukses' => true,
            'total' => $ditolak->count(),
            'html' => view('admin.kehadiran.percobaan_rows', ['ditolak' => $ditolak])->render(),
        ]);
    }

    public function hapus(Request $request)
    {
        $absen = Absensi::find((int) $request->input('id'));
        $pesan = 'Data absensi dihapus.';

        if ($absen) {
            $nama = (string) User::where('id', $absen->user_id)->value('nama_lengkap');
            $detail = $nama !== '' ? $nama.' — '.tgl_id($absen->tanggal) : 'ID #'.$absen->id;
            $absen->delete();
            catat_aktivitas('Hapus Absensi', $detail);
        }

        if ($request->expectsJson()) {
            return response()->json(['sukses' => true, 'pesan' => $pesan]);
        }

        return redirect()->back()->with('success', $pesan);
    }

    /**
     * Parameter ?tanggal=, ?q=, ?unit=, ?status= dari request, dibersihkan
     * supaya aman dipakai ulang oleh index dan endpoint asinkron.
     *
     * @return array{tanggal: string, q: string, unit: int, status: string}
     */
    private function filter(Request $request): array
    {
        $tanggal = (string) $request->get('tanggal');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = now()->format('Y-m-d');
        }

        $status = (string) $request->get('status');
        if (! in_array($status, ['tepat', 'terlambat'], true)) {
            $status = '';
        }

        return [
            'tanggal' => $tanggal,
            'q' => trim((string) $request->get('q')),
            'unit' => (int) $request->get('unit'),
            'status' => $status,
        ];
    }

    /**
     * Query dasar catatan kehadiran pada tanggal terpilih, sudah menerapkan
     * pencarian, filter status, dan filter unit. Dipakai bersama oleh tabel
     * (halamanAbsensi) dan titik peta (titikAbsensi).
     *
     * @param  array{tanggal: string, q: string, unit: int, status: string}  $filter
     */
    private function kueriAbsensi(array $filter): Builder
    {
        $q = $filter['q'];

        return Absensi::query()
            ->whereDate('absensi.tanggal', $filter['tanggal'])
            ->when($q !== '', fn ($b) => $b->whereHas('user', fn ($u) => $u->where('nama_lengkap', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%")))
            ->when($filter['status'] !== '', fn ($b) => $b->where('absensi.status_masuk', $filter['status'] === 'tepat' ? 'Tepat Waktu' : 'Terlambat'))
            ->when($filter['unit'], fn ($b) => $b->whereHas('user', fn ($u) => $u->where('unit_kerja_id', $filter['unit'])))
            ->orderBy('absensi.waktu_masuk');
    }

    /**
     * Satu halaman tabel kehadiran, lengkap dengan relasi shift hari itu yang
     * dipakai label_shift() pada view.
     *
     * @param  array{tanggal: string, q: string, unit: int, status: string}  $filter
     * @return LengthAwarePaginator
     */
    private function halamanAbsensi(array $filter, int $hal)
    {
        $rows = $this->kueriAbsensi($filter)
            ->with([
                'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
                'user.unitKerja:id,nama',
                'user.subUnit:id,nama',
                'logLokasiDatang' => fn ($l) => $l->select('id', 'jarak_meter', 'tipe'),
            ])
            ->paginate(self::PER_HALAMAN, ['*'], 'hal', $hal);

        // Resolve jadwal tersimpan atau template mingguan untuk tiap pegawai.
        $items = $rows->getCollection();
        $shift = app(\App\Services\JadwalEfektifService::class)
            ->shiftsUntukPegawai($items->map(fn ($r) => $r->user), $filter['tanggal']);
        foreach ($items as $r) {
            $r->setRelation('shiftHariIni', $shift[$r->user_id] ?? null);
        }

        return $rows;
    }

    /**
     * Titik peta sebaran absen untuk seluruh catatan yang cocok dengan filter
     * (bukan hanya halaman yang tampil), dibatasi BATAS_TITIK agar tetap ringan.
     *
     * @param  array{tanggal: string, q: string, unit: int, status: string}  $filter
     * @return array<int, array<string, mixed>>
     */
    private function titikAbsensi(array $filter): array
    {
        $rows = $this->kueriAbsensi($filter)
            ->with('user:id,nama_lengkap')
            ->limit(self::BATAS_TITIK)
            ->get(['absensi.id', 'absensi.user_id', 'lat_masuk', 'lng_masuk', 'waktu_masuk',
                'lat_pulang', 'lng_pulang', 'waktu_pulang', 'flag_anomali']);

        $titik = [];
        foreach ($rows as $r) {
            if ($r->lat_masuk !== null) {
                $titik[] = ['nama' => $r->user?->nama_lengkap, 'tipe' => 'Datang',
                    'lat' => (float) $r->lat_masuk, 'lng' => (float) $r->lng_masuk,
                    'jam' => jam_id($r->waktu_masuk), 'anomali' => (bool) $r->flag_anomali];
            }
            if ($r->lat_pulang !== null) {
                $titik[] = ['nama' => $r->user?->nama_lengkap, 'tipe' => 'Pulang',
                    'lat' => (float) $r->lat_pulang, 'lng' => (float) $r->lng_pulang,
                    'jam' => jam_id($r->waktu_pulang), 'anomali' => (bool) $r->flag_anomali];
            }
        }

        return $titik;
    }

    /** Percobaan absen di luar radius pada satu tanggal. */
    private function daftarPercobaan(string $tanggal)
    {
        return LogLokasi::query()
            ->with('user:id,nama_lengkap')
            ->where('log_lokasi.ditolak', 1)
            ->whereDate('log_lokasi.waktu', $tanggal)
            ->orderBy('log_lokasi.waktu')
            ->limit(self::BATAS_BARIS)
            ->get();
    }

    /** Tanggal libur dalam rentang ±1 tahun, dipakai modal tambah absensi. */
    private function daftarLibur(): array
    {
        return HariLibur::whereBetween('tanggal', [
            now()->subYear()->format('Y-m-d'),
            now()->addYears(2)->format('Y-m-d'),
        ])->get()->mapWithKeys(fn ($h) => [$h->tanggal->toDateString() => true])->all();
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
    {        $waktuMasuk = new DateTime($tanggal.' '.$jamMasuk);
        $waktuPulang = $jamPulang !== '' ? new DateTime($tanggal.' '.$jamPulang) : null;

        $isDokter = User::find($userId)?->profesi?->nama === 'Dokter';

        $user = User::find($userId);
        $shift = $user
            ? app(\App\Services\JadwalEfektifService::class)->shiftUntukTanggal($user, $tanggal)
            : null;

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

    private function daftarTahun(): array
    {
        $min = Absensi::min('tanggal');
        $max = Absensi::max('tanggal');

        $thMin = $min ? (int) date('Y', strtotime((string) $min)) : (int) now()->year;
        $thMax = $max ? (int) date('Y', strtotime((string) $max)) : (int) now()->year;

        if ($thMin < 2000) {
            $thMin = 2000;
        }
        if ($thMax > 2100) {
            $thMax = 2100;
        }
        if ($thMin > $thMax) {
            $thMin = $thMax;
        }

        $list = [];
        for ($y = $thMax; $y >= $thMin; $y--) {
            $list[] = $y;
            if (count($list) >= 6) {
                break;
            }
        }
        if ($list === []) {
            $list[] = (int) now()->year;
        }

        return array_reverse($list);
    }

    private function polaUlang(array $hitungan, int $ambang): ?array
    {
        if ($hitungan === []) {
            return null;
        }
        arsort($hitungan, SORT_NUMERIC);
        $nilai = array_key_first($hitungan);
        $jml = (int) $hitungan[$nilai];

        return $jml > $ambang ? ['nilai' => $nilai, 'jumlah' => $jml] : null;
    }

    private function penggunaMencurigakan(int $bulan, int $tahun): array
    {
        $awal  = sprintf('%04d-%02d-01', $tahun, $bulan);
        $akhir = date('Y-m-t', strtotime($awal));

        $rows = Absensi::query()
            ->whereBetween('absensi.tanggal', [$awal, $akhir])
            ->whereHas('user', fn ($u) => $u->where('role', '!=', 'admin')->where('status', 'aktif'))
            ->with('user:id,nama_lengkap,email')
            ->get([
                'user_id', 'tanggal', 'waktu_masuk', 'waktu_pulang',
                'lat_masuk', 'lng_masuk', 'lat_pulang', 'lng_pulang',
            ]);

        $perUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            if (! isset($perUser[$uid])) {
                $perUser[$uid] = [
                    'user' => $r->user,
                    'masuk' => [],
                    'pulang' => [],
                    'koord' => [],
                ];
            }

            if ($r->waktu_masuk) {
                $jam = $r->waktu_masuk->format('H:i');
                $perUser[$uid]['masuk'][$jam] = ($perUser[$uid]['masuk'][$jam] ?? 0) + 1;
            }
            if ($r->waktu_pulang) {
                $jam = $r->waktu_pulang->format('H:i');
                $perUser[$uid]['pulang'][$jam] = ($perUser[$uid]['pulang'][$jam] ?? 0) + 1;
            }

            foreach ([['lat_masuk', 'lng_masuk'], ['lat_pulang', 'lng_pulang']] as [$la, $ln]) {
                if ($r->$la !== null && $r->$ln !== null) {
                    $kunci = round((float) $r->$la, 6).','.round((float) $r->$ln, 6);
                    $perUser[$uid]['koord'][$kunci] = ($perUser[$uid]['koord'][$kunci] ?? 0) + 1;
                }
            }
        }

        $hasil = [];
        foreach ($perUser as $uid => $d) {
            $masuk  = $this->polaUlang($d['masuk'], self::AMBANG_JAM_SAMA);
            $pulang = $this->polaUlang($d['pulang'], self::AMBANG_JAM_SAMA);
            $koord  = $this->polaUlang($d['koord'], self::AMBANG_KOORDINAT_SAMA);

            if ($masuk === null && $pulang === null && $koord === null) {
                continue;
            }

            $hasil[] = [
                'user_id' => (int) $uid,
                'nama' => $d['user']?->nama_lengkap,
                'email' => $d['user']?->email,
                'masuk' => $masuk,
                'pulang' => $pulang,
                'koord' => $koord,
            ];
        }

        usort($hasil, function ($a, $b) {
            $sa = (($a['koord'] !== null) ? 3 : 0)
                + (($a['masuk'] !== null) ? 1 : 0)
                + (($a['pulang'] !== null) ? 1 : 0)
                + ($a['koord']['jumlah'] ?? 0);
            $sb = (($b['koord'] !== null) ? 3 : 0)
                + (($b['masuk'] !== null) ? 1 : 0)
                + (($b['pulang'] !== null) ? 1 : 0)
                + ($b['koord']['jumlah'] ?? 0);
            if ($sb !== $sa) {
                return $sb <=> $sa;
            }
            $an = strtolower((string) $a['nama']);
            $bn = strtolower((string) $b['nama']);
            if ($an !== $bn) {
                return $an <=> $bn;
            }

            return 0;
        });

        return $hasil;
    }
}
