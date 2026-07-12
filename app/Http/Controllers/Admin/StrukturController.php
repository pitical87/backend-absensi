<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StrukturService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StrukturController extends Controller
{
    public function index(StrukturService $lib)
    {
        $perKategori = [];
        foreach (DB::table('users')
                     ->select('jabatan_kategori', DB::raw('COUNT(*) AS jml'))
                     ->where('status', 'aktif')->where('role', 'pegawai')
                     ->groupBy('jabatan_kategori')->get() as $r) {
            $perKategori[$r->jabatan_kategori] = (int) $r->jml;
        }

        $perUnit = DB::table('users as u')
            ->select('uk.nama', DB::raw('COUNT(*) AS jml'))
            ->join('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->where('u.status', 'aktif')->where('u.role', 'pegawai')
            ->groupBy('uk.id')->orderBy('uk.id')->get()->all();

        return view('admin.struktur', [
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
            $kategori = (string) $request->input('kategori');
            $induk    = (int) $request->input('induk_id') ?: null;
            $unit     = trim((string) $request->input('unit_label')) ?: null;

            if ($nama === '' || ! in_array($kategori, array_slice(kategori_jabatan_list(), 0, 5), true)) {
                return redirect('admin/struktur')
                    ->with('flash_gagal', 'Nama dan kategori jabatan wajib diisi.');
            }
            if ($induk && ! DB::table('jabatan')->where('id', $induk)->count()) {
                $induk = null;
            }
            DB::table('jabatan')->insert([
                'nama' => $nama, 'kategori' => $kategori, 'induk_id' => $induk,
                'unit_label' => $unit,
                'urutan' => 99,
            ]);
            catat_aktivitas('Tambah Jabatan', $nama . ' (' . $kategori . ')');
            return redirect('admin/struktur')->with('flash_sukses', 'Jabatan baru ditambahkan ke struktur.');
        }

        if ($aksi === 'ubah') {
            if ($nama !== '' && $id) {
                $data = ['nama' => $nama];
                $unit = trim((string) $request->input('unit_label'));
                if ($request->input('unit_label') !== null) {
                    $data['unit_label'] = $unit !== '' ? $unit : null;
                }
                DB::table('jabatan')->where('id', $id)->update($data);
                catat_aktivitas('Ubah Jabatan', '#' . $id . ' → ' . $nama);
            }
            return redirect('admin/struktur')->with('flash_sukses', 'Jabatan diperbarui.');
        }

        if ($aksi === 'hapus') {
            $punyaAnak = DB::table('jabatan')->where('induk_id', $id)->count() > 0;
            $dipegang  = DB::table('users')->where('jabatan_id', $id)->count() > 0;
            if ($punyaAnak || $dipegang) {
                return redirect('admin/struktur')->with('flash_gagal',
                    'Jabatan tidak dapat dihapus karena masih memiliki '
                    . ($punyaAnak ? 'jabatan bawahan' : 'pemegang jabatan')
                    . '. Pindahkan terlebih dahulu.');
            }
            $j = DB::table('jabatan')->where('id', $id)->first();
            DB::table('jabatan')->where('id', $id)->delete();
            catat_aktivitas('Hapus Jabatan', $j->nama ?? ('#' . $id));
            return redirect('admin/struktur')->with('flash_sukses', 'Jabatan dihapus dari struktur.');
        }

        return redirect('admin/struktur')->with('flash_gagal', 'Aksi tidak dikenal.');
    }
}
