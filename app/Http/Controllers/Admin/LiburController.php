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
        pastikan_libur_tetap($tahun);

        $q = trim((string) $request->get('q'));

        $daftar = HariLibur::whereYear('tanggal', $tahun)
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where(function ($sub) use ($q) {
                    $sub->where('keterangan', 'like', "%{$q}%")
                        ->orWhere('tanggal', 'like', "%{$q}%");
                });
            })
            ->orderBy('tanggal')->get()->all();

        return view('admin.libur.index', [
            'judulHalaman' => 'Hari Libur',
            'menuAktif' => 'libur',
            'daftar' => $daftar,
            'tahun' => $tahun,
            'q' => $q,
            'mingguLibur' => pengaturan('minggu_libur', '0') === '1',
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');

        if ($aksi === 'tambah') {
            $tanggal = (string) $request->input('tanggal');
            $ket = trim((string) $request->input('keterangan'));
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return redirect('admin/libur')->with('error', 'Tanggal dan keterangan wajib diisi.');
            }
            $ada = HariLibur::whereDate('tanggal', $tanggal)->exists();
            if ($ada) {
                return redirect('admin/libur')->with('error', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');
            }
            HariLibur::create(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Tambah Hari Libur', $tanggal.' — '.$ket);

            return redirect('admin/libur')->with('success', 'Hari libur ditambahkan.');
        }

        if ($aksi === 'ubah') {
            $id = (int) $request->input('id');
            $tanggal = (string) $request->input('tanggal');
            $ket = trim((string) $request->input('keterangan'));

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return redirect()->back()->with('error', 'Tanggal dan keterangan wajib diisi.');
            }

            $h = HariLibur::find($id);
            if (! $h) {
                return redirect()->back()->with('error', 'Hari libur tidak ditemukan.');
            }
            $bentrok = HariLibur::whereDate('tanggal', $tanggal)->where('id', '!=', $id)->exists();
            if ($bentrok) {
                return redirect()->back()->with('error', 'Tanggal tersebut sudah terdaftar sebagai hari libur.');
            }

            $lama = $h->tanggal->format('Y-m-d').' — '.$h->keterangan;
            $h->update(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Ubah Hari Libur', $lama.'  →  '.$tanggal.' — '.$ket);

            return redirect()->back()->with('success', 'Hari libur diperbarui.');
        }

        if ($aksi === 'hapus') {
            $id = (int) $request->input('id');
            $h = HariLibur::find($id);
            if ($h) {
                $h->delete();
                catat_aktivitas('Hapus Hari Libur', $h->tanggal.' — '.$h->keterangan);
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
