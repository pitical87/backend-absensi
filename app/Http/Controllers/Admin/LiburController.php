<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiburController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->get('tahun') ?: now()->format('Y'));
        $tahun = min(2100, max(2024, $tahun));
        pastikan_libur_tetap($tahun);

        $daftar = DB::table('hari_libur')
            ->whereYear('tanggal', $tahun)
            ->orderBy('tanggal')->get()->all();

        return view('admin.libur', [
            'judulHalaman' => 'Hari Libur',
            'menuAktif'    => 'libur',
            'daftar'       => $daftar,
            'tahun'        => $tahun,
            'mingguLibur'  => pengaturan('minggu_libur', '0') === '1',
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');

        if ($aksi === 'tambah') {
            $tanggal = (string) $request->input('tanggal');
            $ket     = trim((string) $request->input('keterangan'));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return redirect('admin/libur')->with('flash_gagal', 'Tanggal dan keterangan wajib diisi.');
            }
            $ada = DB::table('hari_libur')->where('tanggal', $tanggal)->count() > 0;
            if ($ada) {
                return redirect('admin/libur')->with('flash_gagal', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');
            }
            DB::table('hari_libur')->insert(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Tambah Hari Libur', $tanggal . ' — ' . $ket);
            return redirect('admin/libur')->with('flash_sukses', 'Hari libur ditambahkan.');
        }

        if ($aksi === 'hapus') {
            $id = (int) $request->input('id');
            $h  = DB::table('hari_libur')->where('id', $id)->first();
            if ($h) {
                DB::table('hari_libur')->where('id', $id)->delete();
                catat_aktivitas('Hapus Hari Libur', $h->tanggal . ' — ' . $h->keterangan);
            }
            return redirect('admin/libur')->with('flash_sukses', 'Hari libur dihapus.');
        }

        if ($aksi === 'minggu') {
            simpan_pengaturan('minggu_libur', $request->input('minggu_libur') ? '1' : '0');
            catat_aktivitas('Pengaturan', 'Status hari Minggu sebagai libur diubah');
            return redirect('admin/libur')->with('flash_sukses', 'Pengaturan hari Minggu diperbarui.');
        }

        return redirect('admin/libur')->with('flash_gagal', 'Aksi tidak dikenal.');
    }
}
