<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DaftarAjax;
use App\Http\Controllers\Controller;
use App\Models\HariLibur;
use Illuminate\Http\Request;

class LiburController extends Controller
{
    use DaftarAjax;

    public function index(Request $request)
    {
        $filter = $this->filterTahun($request);
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return view('admin.libur.index', [
            'judulHalaman' => 'Hari Libur',
            'menuAktif' => 'libur',
            'daftar' => $daftar,
            'tahun' => $filter['tahun'],
            'q' => $filter['q'],
            'jumlah' => $daftar->total(),
            'mingguLibur' => pengaturan('minggu_libur', '0') === '1',
        ]);
    }

    public function data(Request $request)
    {
        $filter = $this->filterTahun($request);

        return $this->tabelAjax($filter);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $filter = $this->filterTahun($request);

        if ($aksi === 'tambah') {
            $tanggal = (string) $request->input('tanggal');
            $ket = trim((string) $request->input('keterangan'));

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return $this->selesaiAjax($request, $filter, 'Tanggal dan keterangan wajib diisi.', false);
            }
            if ((int) substr($tanggal, 0, 4) !== $filter['tahun']) {
                return $this->selesaiAjax($request, $filter, 'Tanggal harus berada pada tahun yang sedang ditampilkan.', false);
            }
            if (HariLibur::whereDate('tanggal', $tanggal)->exists()) {
                return $this->selesaiAjax($request, $filter, 'Tanggal tersebut sudah terdaftar sebagai hari libur.', false);
            }

            HariLibur::create(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Tambah Hari Libur', $tanggal.' — '.$ket);

            return $this->selesaiAjax($request, $filter, 'Hari libur ditambahkan.');
        }

        if ($aksi === 'ubah') {
            $id = (int) $request->input('id');
            $tanggal = (string) $request->input('tanggal');
            $ket = trim((string) $request->input('keterangan'));

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $ket === '') {
                return $this->selesaiAjax($request, $filter, 'Tanggal dan keterangan wajib diisi.', false);
            }

            $h = HariLibur::find($id);
            if (! $h) {
                return $this->selesaiAjax($request, $filter, 'Hari libur tidak ditemukan.', false);
            }
            if (HariLibur::whereDate('tanggal', $tanggal)->where('id', '!=', $id)->exists()) {
                return $this->selesaiAjax($request, $filter, 'Tanggal tersebut sudah terdaftar sebagai hari libur.', false);
            }

            $lama = $h->tanggal->format('Y-m-d').' — '.$h->keterangan;
            $h->update(['tanggal' => $tanggal, 'keterangan' => $ket]);
            catat_aktivitas('Ubah Hari Libur', $lama.'  →  '.$tanggal.' — '.$ket);

            return $this->selesaiAjax($request, $filter, 'Hari libur diperbarui.');
        }

        if ($aksi === 'hapus') {
            $h = HariLibur::find((int) $request->input('id'));
            if ($h) {
                catat_aktivitas('Hapus Hari Libur', $h->tanggal->format('Y-m-d').' — '.$h->keterangan);
                $h->delete();
            }

            return $this->selesaiAjax($request, $filter, 'Hari libur dihapus.');
        }

        if ($aksi === 'minggu') {
            simpan_pengaturan('minggu_libur', $request->input('minggu_libur') ? '1' : '0');
            catat_aktivitas('Pengaturan', 'Status hari Minggu sebagai libur diubah');

            return $this->selesaiAjax($request, $filter, 'Pengaturan hari Minggu diperbarui.');
        }

        return $this->selesaiAjax($request, $filter, 'Aksi tidak dikenal.', false);
    }

    private function filterTahun(Request $request): array
    {
        $tahun = (int) ($request->get('tahun') ?: now()->format('Y'));
        if ($tahun < 2000 || $tahun > (int) now()->format('Y') + 5) {
            $tahun = (int) now()->format('Y');
        }
        pastikan_libur_tetap($tahun);

        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        return $filter + ['tahun' => $tahun];
    }

    private function query(array $filter)
    {
        $q = HariLibur::whereYear('tanggal', $filter['tahun'])->orderBy('tanggal');

        if ($filter['q'] !== '') {
            $q->where(function ($sub) use ($filter) {
                $sub->where('keterangan', 'like', "%{$filter['q']}%")
                    ->orWhere('tanggal', 'like', "%{$filter['q']}%");
            });
        }

        return $q;
    }

    private function tabelAjax(array $filter, string $pesan = '', bool $sukses = true)
    {
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return $this->balasAjax(view('admin.libur.tabel', [
            'daftar' => $daftar,
            'tahun' => $filter['tahun'],
            'q' => $filter['q'],
        ])->render(), [
            'pesan' => $pesan,
            'tahun' => $filter['tahun'],
            'q' => $filter['q'],
            'hal' => $daftar->currentPage(),
            'total' => $daftar->total(),
            'totalHal' => $daftar->lastPage(),
            'jumlah' => $daftar->total(),
        ], $sukses);
    }

    private function selesaiAjax(Request $request, array $filter, string $pesan, bool $sukses = true)
    {
        if (! $request->expectsJson()) {
            return redirect('admin/libur')->with($sukses ? 'success' : 'error', $pesan);
        }

        return $this->tabelAjax($filter, $pesan, $sukses);
    }
}
