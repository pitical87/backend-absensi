<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\User;
use App\Services\StrukturService;
use Illuminate\Http\Request;

class StrukturController extends Controller
{
    public function index(StrukturService $lib)
    {
        $perKategori = User::where('status', 'aktif')
            ->where('role', 'pegawai')
            ->select('jabatan_kategori', \DB::raw('COUNT(*) AS jml'))
            ->groupBy('jabatan_kategori')
            ->pluck('jml', 'jabatan_kategori')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $perUnit = User::where('status', 'aktif')
            ->where('role', 'pegawai')
            ->join('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->select('uk.nama', \DB::raw('COUNT(*) AS jml'))
            ->groupBy('uk.id')
            ->orderBy('uk.id')
            ->get();

        return view('admin.struktur.index', [
            'judulHalaman' => 'Struktur Organisasi',
            'menuAktif'    => 'struktur',
            'pohon'        => $lib->pohon(),
            'semua'        => $lib->semua(),
            'kategoriJab'  => array_slice(kategori_jabatan_list(), 0, 5),
            'perKategori'  => $perKategori,
            'perUnit'      => $perUnit,
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id   = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));

        if ($aksi === 'tambah') {
            return $this->handleTambah($request, $nama);
        }

        if ($aksi === 'ubah') {
            return $this->handleUbah($request, $id, $nama);
        }

        if ($aksi === 'hapus') {
            return $this->handleHapus($id);
        }

        return redirect('admin/struktur')->with('error', 'Aksi tidak dikenal.');
    }

    private function handleTambah(Request $request, string $nama)
    {
        $kategori = (string) $request->input('kategori');
        $induk    = (int) $request->input('induk_id') ?: null;
        $unit     = trim((string) $request->input('unit_label')) ?: null;

        if ($nama === '' || ! in_array($kategori, array_slice(kategori_jabatan_list(), 0, 5), true)) {
            return redirect('admin/struktur')
                ->with('error', 'Nama dan kategori jabatan wajib diisi.');
        }

        if ($induk && ! Jabatan::where('id', $induk)->exists()) {
            $induk = null;
        }

        Jabatan::create([
            'nama'       => $nama,
            'kategori'   => $kategori,
            'induk_id'   => $induk,
            'unit_label' => $unit,
            'urutan'     => 99,
        ]);

        catat_aktivitas('Tambah Jabatan', $nama . ' (' . $kategori . ')');

        return redirect('admin/struktur')->with('success', 'Jabatan baru ditambahkan ke struktur.');
    }

    private function handleUbah(Request $request, int $id, string $nama)
    {
        if ($nama === '' || ! $id) {
            return redirect('admin/struktur')->with('success', 'Jabatan diperbarui.');
        }

        $jabatan = Jabatan::find($id);
        if (! $jabatan) {
            return redirect('admin/struktur')->with('error', 'Jabatan tidak ditemukan.');
        }

        $data = ['nama' => $nama];
        if ($request->input('unit_label') !== null) {
            $unit = trim((string) $request->input('unit_label'));
            $data['unit_label'] = $unit !== '' ? $unit : null;
        }

        $jabatan->update($data);

        catat_aktivitas('Ubah Jabatan', '#' . $id . ' → ' . $nama);

        return redirect('admin/struktur')->with('success', 'Jabatan diperbarui.');
    }

    private function handleHapus(int $id)
    {
        $jabatan = Jabatan::find($id);
        if (! $jabatan) {
            return redirect('admin/struktur')->with('error', 'Jabatan tidak ditemukan.');
        }

        $punyaAnak = $jabatan->anak()->exists();
        $dipegang  = $jabatan->users()->exists();

        if ($punyaAnak || $dipegang) {
            return redirect('admin/struktur')->with('error',
                'Jabatan tidak dapat dihapus karena masih memiliki '
                . ($punyaAnak ? 'jabatan bawahan' : 'pemegang jabatan')
                . '. Pindahkan terlebih dahulu.');
        }

        $jabatan->delete();

        catat_aktivitas('Hapus Jabatan', $jabatan->nama);

        return redirect('admin/struktur')->with('success', 'Jabatan dihapus dari struktur.');
    }
}
