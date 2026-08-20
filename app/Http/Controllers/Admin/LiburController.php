<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use Illuminate\Http\Request;

class LiburController extends Controller
{
    public function index(Request $request)
    {
        $tahun = (int) ($request->get('tahun') ?: now()->format('Y'));
        $tahun = min(2100, max(2024, $tahun));
        pastikan_libur_tetap($tahun);

        $daftar = HariLibur::whereYear('tanggal', $tahun)
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
                return redirect('admin/libur')->with('error', 'Tanggal dan keterangan wajib diisi.');
            }
            $ada = HariLibur::where('tanggal', $tanggal)->count() > 0;
            if ($ada) {
                return redirect('admin/libur')->with('error', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');
            }
            HariLibur::create(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Tambah Hari Libur', $tanggal . ' — ' . $ket);
            return redirect('admin/libur')->with('success', 'Hari libur ditambahkan.');
        }

        if ($aksi === 'hapus') {
            $id = (int) $request->input('id');
            $h  = HariLibur::find($id);
            if ($h) {
                $h->delete();
                catat_aktivitas('Hapus Hari Libur', $h->tanggal . ' — ' . $h->keterangan);
            }
            return redirect('admin/libur')->with('success', 'Hari libur dihapus.');
        }

        if ($aksi === 'minggu') {
            simpan_pengaturan('minggu_libur', $request->input('minggu_libur') ? '1' : '0');
            catat_aktivitas('Pengaturan', 'Status hari Minggu sebagai libur diubah');
            return redirect('admin/libur')->with('success', 'Pengaturan hari Minggu diperbarui.');
        }

        return redirect('admin/libur')->with('error', 'Aksi tidak dikenal.');
    }
}
