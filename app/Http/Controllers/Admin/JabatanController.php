<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use Illuminate\Http\Request;

class JabatanController extends Controller
{
    public function index()
    {
        $jabatans = Jabatan::with(['induk', 'users'])
            ->orderBy('kategori')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(20);

        return view('admin.jabatan.index', [
            'judulHalaman' => 'Master Jabatan',
            'menuAktif' => 'jabatan',
            'jabatans' => $jabatans,
        ]);
    }

    public function create()
    {
        $jabatans = Jabatan::orderBy('kategori')->orderBy('urutan')->orderBy('nama')->get();
        $kategoriList = array_slice(kategori_jabatan_list(), 0, 5);

        return view('admin.jabatan.create', [
            'judulHalaman' => 'Tambah Jabatan',
            'menuAktif' => 'jabatan',
            'jabatans' => $jabatans,
            'kategoriList' => $kategoriList,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kategori' => 'required|in:'.implode(',', array_slice(kategori_jabatan_list(), 0, 5)),
            'induk_id' => 'nullable|integer|exists:jabatan,id',
            'unit_label' => 'nullable|string|max:100',
            'urutan' => 'nullable|integer|min:0',
        ]);

        Jabatan::create($data);

        catat_aktivitas('Tambah Jabatan', $data['nama'].' ('.$data['kategori'].')');

        return redirect()->route('admin.jabatan.index')
            ->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan)
    {
        $jabatans = Jabatan::where('id', '!=', $jabatan->id)
            ->orderBy('kategori')->orderBy('urutan')->orderBy('nama')->get();
        $kategoriList = array_slice(kategori_jabatan_list(), 0, 5);

        return view('admin.jabatan.edit', [
            'judulHalaman' => 'Ubah Jabatan',
            'menuAktif' => 'jabatan',
            'jabatan' => $jabatan,
            'jabatans' => $jabatans,
            'kategoriList' => $kategoriList,
        ]);
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kategori' => 'required|in:'.implode(',', array_slice(kategori_jabatan_list(), 0, 5)),
            'induk_id' => 'nullable|integer|exists:jabatan,id',
            'unit_label' => 'nullable|string|max:100',
            'urutan' => 'nullable|integer|min:0',
        ]);

        if ($data['induk_id'] == $jabatan->id) {
            return back()->withErrors(['induk_id' => 'Jabatan tidak bisa menjadi induk dirinya sendiri.']);
        }

        $jabatan->update($data);

        catat_aktivitas('Ubah Jabatan', '#'.$jabatan->id.' → '.$data['nama']);

        return redirect()->route('admin.jabatan.index')
            ->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Jabatan $jabatan)
    {
        $punyaAnak = $jabatan->anak()->exists();
        $dipegang = $jabatan->users()->exists();

        if ($punyaAnak || $dipegang) {
            return back()->with('error',
                'Jabatan tidak dapat dihapus karena masih memiliki '
                .($punyaAnak ? 'jabatan bawahan' : 'pemegang jabatan')
                .'. Pindahkan terlebih dahulu.');
        }

        $nama = $jabatan->nama;
        $jabatan->delete();

        catat_aktivitas('Hapus Jabatan', $nama);

        return redirect()->route('admin.jabatan.index')
            ->with('success', 'Jabatan berhasil dihapus.');
    }
}
