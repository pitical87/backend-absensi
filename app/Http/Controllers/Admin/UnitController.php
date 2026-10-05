<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'isi' => $this->renderIsi($mode, $unitAktif, $unitList, $pegawaiPilihan),
        ]);
    }

    /**
     * Endpoint asinkron: isi tab (semua / satu unit / tambah unit) tanpa reload.
     */
    public function data(Request $request): JsonResponse
    {
        $unitList = $this->unitList();
        [$mode, $unitAktif] = $this->modeTab((string) $request->get('tab', 'semua'), $unitList);

        return response()->json([
            'sukses' => true,
            'mode' => $mode,
            'tabs' => $this->renderTabs($mode, $unitAktif, $unitList),
            'isi' => $this->renderIsi($mode, $unitAktif, $unitList, $this->pegawaiPilihan()),
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
            'hapus_sub' => $this->hapusSub($id),
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

        SubUnit::where('id', $id)->update(['nama' => $nama, 'atasan_id' => $atasanId]);
        catat_aktivitas('Ubah Sub Unit', $nama);

        return ['Sub unit diperbarui.', true, null];
    }

    private function hapusSub(int $id): array
    {
        if (User::where('sub_unit_id', $id)->exists()) {
            return ['Sub unit tidak dapat dihapus karena masih memiliki pegawai.', false, null];
        }

        $nama = SubUnit::where('id', $id)->value('nama') ?? ('#'.$id);
        SubUnit::where('id', $id)->delete();
        catat_aktivitas('Hapus Sub Unit', $nama);

        return ['Sub unit dihapus.', true, null];
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
            'isi' => $this->renderIsi($mode, $unitAktif, $unitList, $this->pegawaiPilihan()),
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
        return UnitKerja::select(
            'unit_kerja.*',
            \DB::raw('(SELECT COUNT(*) FROM sub_unit s WHERE s.unit_kerja_id = unit_kerja.id) AS jml_sub'),
            \DB::raw('(SELECT COUNT(*) FROM users u WHERE u.unit_kerja_id = unit_kerja.id) AS jml_pegawai')
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
}
