<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtasanLangsung;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $unitList = $this->unitList();
        $pegawaiPilihan = $this->pegawaiPilihan();
        [$mode, $unitAktif] = $this->modeTab((string) $request->query('tab', 'semua'), $unitList);

        return view('admin.unit.index', [
            'judulHalaman' => 'Data Unit Kerja',
            'menuAktif' => 'unit',
            'unitList' => $unitList,
            'pegawaiPilihan' => $pegawaiPilihan,
            'mode' => $mode,
            'unitAktif' => $unitAktif,
            'unitUbah' => null,
            'tabs' => $this->renderTabs($mode, $unitAktif, $unitList),
            'tabAktif' => $this->kunciAktif($mode, $unitAktif),
            // Seluruh isi tab dirender sekaligus di awal, jadi berpindah tab
            // tidak lagi menunggu permintaan jaringan.
            'isiSemua' => $this->renderIsiSemua($unitList, $pegawaiPilihan),
        ]);
    }

    public function pegawaiSubUntukHapus(SubUnit $subUnit): JsonResponse
    {
        return response()->json([
            'sub_unit' => [
                'id' => (int) $subUnit->id,
                'nama' => $subUnit->nama,
            ],
            'pegawai' => User::where('sub_unit_id', $subUnit->id)
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'email'])
                ->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'nama' => $user->nama_lengkap,
                    'email' => $user->email,
                ])
                ->values(),
            'tujuan' => SubUnit::where('unit_kerja_id', $subUnit->unit_kerja_id)
                ->where('id', '!=', $subUnit->id)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (SubUnit $tujuan) => [
                    'id' => (int) $tujuan->id,
                    'nama' => $tujuan->nama,
                ])
                ->values(),
        ]);
    }

    public function pegawaiSubUntukKelola(SubUnit $subUnit): JsonResponse
    {
        return response()->json([
            'sub_unit' => [
                'id' => (int) $subUnit->id,
                'nama' => $subUnit->nama,
                'unit_kerja_id' => (int) $subUnit->unit_kerja_id,
            ],
            'pegawai' => User::where('role', 'pegawai')
                ->where('status', 'aktif')
                ->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'email', 'unit_kerja_id', 'sub_unit_id'])
                ->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'nama' => $user->nama_lengkap,
                    'email' => $user->email,
                    'di_sub_unit' => (int) $user->sub_unit_id === (int) $subUnit->id,
                    'unit_kerja_id' => (int) $user->unit_kerja_id,
                ])
                ->values(),
        ]);
    }

    /**
     * Semua aksi lewat AJAX akan membalas JSON berisi tab yang sudah disegarkan
     * ulang, jadi klien cukup menimpa isi tanpa memanggil endpoint lagi.
     */
    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));
        $atasanId = (int) $request->input('atasan_id') ?: null;

        [$pesan, $sukses, $mode] = match ($aksi) {
            'tambah_unit' => $this->tambahUnit($nama, (bool) $request->input('punya_sub')),
            'ubah_unit' => $this->ubahUnit($id, $nama, $atasanId, (bool) $request->input('punya_sub')),
            'hapus_unit' => $this->hapusUnit($id),
            'tambah_sub' => $this->tambahSub((int) $request->input('unit_kerja_id'), $nama, $atasanId),
            'ubah_sub' => $this->ubahSub($id, $nama, $atasanId),
            'pindahkan_pegawai_sub' => $this->pindahkanPegawaiSub(
                (int) $request->input('sub_unit_id'),
                (int) $request->input('user_id'),
            ),
            'hapus_sub' => $this->hapusSub($id, $request),
            'kirim_pesan_jadwal' => $this->kirimPesanJadwal($id),
            default => ['Aksi tidak dikenal.', false, null],
        };

        if ($request->expectsJson()) {
            return $this->balasJson($request, $sukses, $pesan, $mode);
        }

        return redirect('admin/unit')->with($sukses ? 'success' : 'error', $pesan);
    }

    // ── AKSI ──────────────────────────────────────────────────────────

    private function tambahUnit(string $nama, bool $punyaSub): array
    {
        if ($nama === '') {
            return ['Nama unit wajib diisi.', false, 'tambah'];
        }

        UnitKerja::create(['nama' => $nama, 'punya_sub' => $punyaSub ? 1 : 0]);
        catat_aktivitas('Tambah Unit', $nama);

        return ['Unit kerja ditambahkan.', true, 'semua'];
    }

    private function ubahUnit(int $id, string $nama, ?int $atasanId, bool $punyaSub): array
    {
        if ($nama === '') {
            return ['Nama unit wajib diisi.', false, null];
        }

        UnitKerja::where('id', $id)->update([
            'nama' => $nama,
            'punya_sub' => $punyaSub ? 1 : 0,
            'atasan_id' => $atasanId,
        ]);
        catat_aktivitas('Ubah Unit', $nama);

        return ['Unit kerja diperbarui.', true, null];
    }

    private function hapusUnit(int $id): array
    {
        if (User::where('unit_kerja_id', $id)->exists()) {
            return ['Unit tidak dapat dihapus karena masih memiliki pegawai. Pindahkan pegawainya terlebih dahulu.', false, null];
        }

        $nama = UnitKerja::where('id', $id)->value('nama') ?? ('#'.$id);
        UnitKerja::where('id', $id)->delete();
        catat_aktivitas('Hapus Unit', $nama);

        return ['Unit kerja beserta sub unitnya dihapus.', true, 'semua'];
    }

    private function tambahSub(int $unitId, string $nama, ?int $atasanId): array
    {
        if ($unitId < 1 || $nama === '') {
            return ['Sub unit wajib memiliki unit kerja dan nama.', false, null];
        }

        SubUnit::create(['unit_kerja_id' => $unitId, 'nama' => $nama, 'atasan_id' => $atasanId]);
        UnitKerja::where('id', $unitId)->update(['punya_sub' => 1]);
        catat_aktivitas('Tambah Sub Unit', $nama);

        return ['Sub unit ditambahkan.', true, null];
    }

    private function ubahSub(int $id, string $nama, ?int $atasanId): array
    {
        if ($nama === '') {
            return ['Nama sub unit wajib diisi.', false, null];
        }

        $sub = SubUnit::find($id);
        if (! $sub) {
            return ['Sub unit tidak ditemukan.', false, null];
        }

        $sub->update(['nama' => $nama, 'atasan_id' => $atasanId]);

        // Update atasan_langsung for all users in this sub unit
        if ($atasanId) {
            $pegawaiIds = User::where('sub_unit_id', $id)
                ->where('role', '!=', 'admin')
                ->pluck('id')
                ->all();

            if ($pegawaiIds) {
                AtasanLangsung::whereIn('user_id', $pegawaiIds)->delete();

                $rows = array_map(fn ($uid) => ['user_id' => $uid, 'atasan_id' => $atasanId], $pegawaiIds);
                AtasanLangsung::insert($rows);
            }
        }

        catat_aktivitas('Ubah Sub Unit', $nama.($atasanId ? ' — atasan sinkron ke '.count($pegawaiIds ?? []).' pegawai' : ''));

        return ['Sub unit diperbarui.', true, null];
    }

    private function pindahkanPegawaiSub(int $subUnitId, int $userId): array
    {
        if ($subUnitId < 1 || $userId < 1) {
            return ['Pilih pegawai dan sub unit yang valid.', false, null];
        }

        $hasil = DB::transaction(function () use ($subUnitId, $userId) {
            $subUnit = SubUnit::whereKey($subUnitId)->lockForUpdate()->first();
            $pegawai = User::whereKey($userId)->lockForUpdate()->first();
            if (! $subUnit || ! $pegawai) {
                return ['error' => 'Pegawai atau sub unit tidak ditemukan.'];
            }
            if ($pegawai->role !== 'pegawai') {
                return ['error' => 'Hanya akun pegawai yang dapat dimasukkan ke sub unit.'];
            }
            if ((int) $pegawai->sub_unit_id === (int) $subUnit->id) {
                return ['error' => 'Pegawai sudah berada di sub unit ini.'];
            }

            $pegawai->update([
                'unit_kerja_id' => $subUnit->unit_kerja_id,
                'sub_unit_id' => $subUnit->id,
            ]);
            $atasanId = $subUnit->atasan_id
                ?: UnitKerja::whereKey($subUnit->unit_kerja_id)->value('atasan_id');

            if ($atasanId && (int) $atasanId !== (int) $pegawai->id) {
                AtasanLangsung::firstOrCreate([
                    'user_id' => $pegawai->id,
                    'atasan_id' => $atasanId,
                ]);
            }

            return [
                'nama_pegawai' => $pegawai->nama_lengkap,
                'nama_sub_unit' => $subUnit->nama,
            ];
        });

        if (isset($hasil['error'])) {
            return [$hasil['error'], false, null];
        }

        catat_aktivitas('Pindahkan Pegawai ke Sub Unit', $hasil['nama_pegawai'].' — '.$hasil['nama_sub_unit']);

        return [
            $hasil['nama_pegawai'].' dipindahkan ke sub unit '.$hasil['nama_sub_unit'].'.',
            true,
            null,
        ];
    }

    private function hapusSub(int $id, Request $request): array
    {
        $sub = SubUnit::find($id);
        if (! $sub) {
            return ['Sub unit tidak ditemukan.', false, null];
        }

        $mode = $request->input('mode_hapus_sub');
        if (! in_array($mode, ['pindahkan', 'nonaktifkan'], true)) {
            return ['Pilih apakah pegawai akan dipindahkan atau dinonaktifkan.', false, null];
        }

        $pegawaiInput = $request->input('pegawai_ids', []);
        $penempatan = $request->input('pindah_ke_sub_unit_id', []);
        if (! is_array($pegawaiInput) || ! is_array($penempatan)) {
            return ['Daftar pegawai atau tujuan pemindahan tidak valid.', false, null];
        }

        $hasil = DB::transaction(function () use ($sub, $mode, $pegawaiInput, $penempatan) {
            $subTerkunci = SubUnit::whereKey($sub->id)->lockForUpdate()->first();
            if (! $subTerkunci) {
                return ['error' => 'Sub unit tidak ditemukan.'];
            }

            $pegawai = User::where('sub_unit_id', $subTerkunci->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
            $pegawaiIds = $pegawai->pluck('id')->map(fn ($id) => (string) $id)->all();
            $inputIds = array_map('strval', $pegawaiInput);
            sort($pegawaiIds);
            sort($inputIds);
            if ($pegawaiIds !== $inputIds) {
                return ['error' => 'Daftar pegawai berubah. Muat ulang pilihan tujuan lalu coba lagi.'];
            }

            if ($mode === 'nonaktifkan') {
                if ($penempatan !== []) {
                    return ['error' => 'Data tujuan pemindahan tidak sesuai dengan tindakan yang dipilih.'];
                }

                if ($pegawaiIds !== []) {
                    User::whereIn('id', $pegawaiIds)
                        ->where('sub_unit_id', $subTerkunci->id)
                        ->update(['status' => 'nonaktif', 'sub_unit_id' => null]);
                }
            } else {
                $penempatanIds = array_map('strval', array_keys($penempatan));
                sort($penempatanIds);
                if ($pegawaiIds !== $penempatanIds) {
                    return ['error' => 'Pilih tujuan untuk setiap pegawai yang akan dipindahkan.'];
                }

                $tujuanPerPegawai = [];
                $tujuanIds = [];
                foreach ($penempatan as $pegawaiId => $tujuanId) {
                    if (! ctype_digit((string) $pegawaiId) || ! in_array((string) $pegawaiId, $pegawaiIds, true)) {
                        return ['error' => 'Daftar pegawai untuk dipindahkan tidak valid.'];
                    }

                    if ($tujuanId === 'none') {
                        $tujuanPerPegawai[(int) $pegawaiId] = null;
                        continue;
                    }

                    $tujuanId = filter_var($tujuanId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($tujuanId === false || $tujuanId === (int) $subTerkunci->id) {
                        return ['error' => 'Pilih tujuan pemindahan yang valid untuk setiap pegawai.'];
                    }

                    $tujuanPerPegawai[(int) $pegawaiId] = $tujuanId;
                    $tujuanIds[] = $tujuanId;
                }

                $tujuanIds = array_values(array_unique($tujuanIds));
                $tujuanList = SubUnit::where('unit_kerja_id', $subTerkunci->unit_kerja_id)
                    ->whereIn('id', $tujuanIds)
                    ->lockForUpdate()
                    ->get(['id', 'nama'])
                    ->keyBy('id');
                if ($tujuanList->count() !== count($tujuanIds)) {
                    return ['error' => 'Sub unit tujuan harus berada dalam unit kerja yang sama.'];
                }

                $kelompok = [];
                foreach ($tujuanPerPegawai as $pegawaiId => $tujuanId) {
                    $kelompok[$tujuanId ?? 'none'][] = $pegawaiId;
                }
                foreach ($kelompok as $tujuanId => $ids) {
                    User::whereIn('id', $ids)
                        ->where('sub_unit_id', $subTerkunci->id)
                        ->update(['sub_unit_id' => $tujuanId === 'none' ? null : (int) $tujuanId]);
                }
            }

            $nama = $subTerkunci->nama;
            $subTerkunci->delete();

            return [
                'jumlah' => $pegawai->count(),
                'nama' => $nama,
            ];
        });

        if (isset($hasil['error'])) {
            return [$hasil['error'], false, null];
        }

        $aksiPegawai = $mode === 'nonaktifkan' ? 'dinonaktifkan' : 'dipindahkan';
        catat_aktivitas('Hapus Sub Unit', $hasil['nama'].' — '.$hasil['jumlah'].' pegawai '.$aksiPegawai);

        $pesan = $mode === 'nonaktifkan'
            ? 'Sub unit dihapus dan '.$hasil['jumlah'].' pegawai dinonaktifkan.'
            : 'Sub unit dihapus dan '.$hasil['jumlah'].' pegawai dipindahkan ke tujuan masing-masing.';

        return [$pesan, true, null];
    }

    private function kirimPesanJadwal(int $unitId): array
    {
        $bulanIni = now()->startOfMonth()->toDateString();
        $bulanDepan = now()->endOfMonth()->toDateString();

        $pegawai = User::where('unit_kerja_id', $unitId)
            ->where('role', '!=', 'admin')
            ->whereNotExists(function ($q) use ($bulanIni, $bulanDepan) {
                $q->selectRaw('1')
                    ->from('jadwal_shift')
                    ->whereColumn('jadwal_shift.user_id', 'users.id')
                    ->whereBetween('tanggal_berlaku', [$bulanIni, $bulanDepan]);
            })
            ->get(['id', 'nama_lengkap']);

        if ($pegawai->isEmpty()) {
            return ['Tidak ada pegawai yang belum set jadwal.', false, null];
        }

        // TODO: Implement actual notification/pesan sending
        // For now just log the activity
        $nama = UnitKerja::where('id', $unitId)->value('nama') ?? ('#'.$unitId);
        catat_aktivitas('Kirim Pesan Set Jadwal', "Unit {$nama} — {$pegawai->count()} pegawai");

        return ['Pesan set jadwal dikirim ke '.$pegawai->count().' pegawai.', true, null];
    }

    private function balasJson(Request $request, bool $sukses, string $pesan, ?string $mode): JsonResponse
    {
        $unitList = $this->unitList();
        $mode = $this->modeAsal($request, $mode, $unitList);
        $unitAktif = $mode === 'unit' ? $unitList->firstWhere('id', $this->unitKunci($request)) : null;

        return response()->json([
            'sukses' => $sukses,
            'pesan' => $pesan,
            'mode' => $mode,
            'tabs' => $this->renderTabs($mode, $unitAktif, $unitList),
            'isiSemua' => $this->renderIsiSemua($unitList, $this->pegawaiPilihan()),
        ], $sukses ? 200 : 422);
    }

    /**
     * Tab tujuan setelah aksi: sebagian aksi memaksa pindah tab (mis. setelah
     * unit dihapus), sisanya tetap di tab_asal yang dikirim formulir.
     */
    private function modeAsal(Request $request, ?string $mode, $unitList): string
    {
        if ($mode !== null) {
            return $mode;
        }

        return $unitList->firstWhere('id', $this->unitKunci($request)) ? 'unit' : 'semua';
    }

    /**
     * Unit asal dari tombol/tab yang dipakai: sub unit mengirim unit_kerja_id,
     * modal ubah unit hanya mengirim tab_asal.
     */
    private function unitKunci(Request $request): int
    {
        return (int) $request->get('unit_kerja_id') ?: (int) $request->get('tab_asal');
    }

    // ── DATA & RENDER ─────────────────────────────────────────────────

    private function unitList()
    {
        $bulanIni = now()->startOfMonth()->toDateString();
        $bulanDepan = now()->endOfMonth()->toDateString();

        return UnitKerja::select(
            'unit_kerja.*',
            \DB::raw('(SELECT COUNT(*) FROM sub_unit s WHERE s.unit_kerja_id = unit_kerja.id) AS jml_sub'),
            \DB::raw('(SELECT COUNT(*) FROM users u WHERE u.unit_kerja_id = unit_kerja.id) AS jml_pegawai'),
            \DB::raw("(
                SELECT COUNT(*)
                FROM users u
                WHERE u.unit_kerja_id = unit_kerja.id
                AND u.role != 'admin'
                AND NOT EXISTS (
                    SELECT 1 FROM jadwal_shift js
                    WHERE js.user_id = u.id
                    AND js.tanggal_berlaku BETWEEN '{$bulanIni}' AND '{$bulanDepan}'
                )
            ) AS jml_belum_jadwal")
        )
            ->orderBy('unit_kerja.nama')
            ->get();
    }

    private function pegawaiPilihan()
    {
        return User::where('role', '!=', 'admin')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'nip', 'email']);
    }

    /**
     * @return array{0: string, 1: ?UnitKerja}
     */
    private function modeTab(string $tab, $unitList): array
    {
        if ($tab === 'tambah') {
            return ['tambah', null];
        }

        $unit = ctype_digit($tab) ? $unitList->firstWhere('id', (int) $tab) : null;

        if ($unit) {
            return ['unit', $unit];
        }

        return ['semua', null];
    }

    private function renderTabs(string $mode, ?UnitKerja $unitAktif, $unitList): string
    {
        return view('admin.unit.tabs', [
            'unitList' => $unitList,
            'mode' => $mode,
            'unitAktif' => $unitAktif,
        ])->render();
    }

    private function renderIsi(string $mode, ?UnitKerja $unitAktif, $unitList, $pegawaiPilihan): string
    {
        if ($mode === 'tambah') {
            return view('admin.unit.tab_tambah')->render();
        }

        if ($mode === 'unit' && $unitAktif) {
            return view('admin.unit.tab_unit', [
                'unitAktif' => $unitAktif,
                'subAktif' => SubUnit::select(
                    'sub_unit.*',
                    \DB::raw('(SELECT COUNT(*) FROM users u WHERE u.sub_unit_id = sub_unit.id) AS jml_pegawai')
                )
                    ->where('unit_kerja_id', $unitAktif->id)
                    ->orderBy('sub_unit.nama')
                    ->get(),
                'pegawaiPilihan' => $pegawaiPilihan,
            ])->render();
        }

        return view('admin.unit.tab_semua', [
            'unitList' => $unitList,
            'pegawaiPilihan' => $pegawaiPilihan,
        ])->render();
    }

    /**
     * Seluruh panel tab (semua unit + tab tambah) dirender dalam satu panggilan
     * supaya klien dapat memuat semuanya sekaligus dan berpindah tab instan.
     *
     * @return array<string, string> kunci panel => HTML
     */
    private function renderIsiSemua($unitList, $pegawaiPilihan): array
    {
        $peta = [
            'semua' => $this->renderIsi('semua', null, $unitList, $pegawaiPilihan),
            'tambah' => $this->renderIsi('tambah', null, $unitList, $pegawaiPilihan),
        ];

        foreach ($unitList as $uk) {
            $peta[(string) (int) $uk->id] = $this->renderIsi('unit', $uk, $unitList, $pegawaiPilihan);
        }

        return $peta;
    }

    /** Kunci panel yang ditampilkan pada muat pertama. */
    private function kunciAktif(string $mode, ?UnitKerja $unitAktif): string
    {
        if ($mode === 'unit' && $unitAktif) {
            return (string) (int) $unitAktif->id;
        }

        return $mode;
    }
}
