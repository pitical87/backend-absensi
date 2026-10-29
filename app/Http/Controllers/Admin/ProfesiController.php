<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DaftarAjax;
use App\Http\Controllers\Controller;
use App\Models\Profesi;
use App\Models\User;
use Illuminate\Http\Request;

class ProfesiController extends Controller
{
    use DaftarAjax;

    public function index(Request $request)
    {
        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        return view('admin.profesi.index', [
            'judulHalaman' => 'Master Profesi',
            'menuAktif' => 'profesi',
            'daftar' => $this->paginateAjax($this->query($filter), $filter),
            'q' => $filter['q'],
        ]);
    }

    public function data(Request $request)
    {
        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        return $this->tabelAjax($filter);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        if ($aksi === 'tambah') {
            return $this->handleTambah($request, $filter);
        }
        if ($aksi === 'ubah') {
            return $this->handleUbah($request, $filter);
        }
        if ($aksi === 'hapus') {
            return $this->handleHapus($request, $filter);
        }

        return $this->selesaiAjax($request, $filter, 'Aksi tidak dikenal.', false);
    }

    private function handleTambah(Request $request, array $filter)
    {
        $nama = trim((string) $request->input('nama'));

        if ($galat = $this->validasiNama($nama, 0)) {
            return $this->selesaiAjax($request, $filter, $galat, false);
        }

        Profesi::create(['nama' => $nama]);
        catat_aktivitas('Tambah Profesi', $nama);

        return $this->selesaiAjax($request, $filter, 'Profesi ditambahkan.');
    }

    private function handleUbah(Request $request, array $filter)
    {
        $id = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));

        $profesi = Profesi::find($id);
        if (! $profesi) {
            return $this->selesaiAjax($request, $filter, 'Profesi tidak ditemukan.', false);
        }

        if ($galat = $this->validasiNama($nama, $id)) {
            return $this->selesaiAjax($request, $filter, $galat, false);
        }

        $lama = $profesi->nama;
        $profesi->update(['nama' => $nama]);

        catat_aktivitas('Ubah Profesi', $lama.' → '.$nama);

        return $this->selesaiAjax($request, $filter, 'Profesi diperbarui.');
    }

    private function handleHapus(Request $request, array $filter)
    {
        $id = (int) $request->input('id');
        $profesi = Profesi::find($id);

        if (! $profesi) {
            return $this->selesaiAjax($request, $filter, 'Profesi tidak ditemukan.', false);
        }

        $dipakai = User::where('profesi_id', $id)->count();
        if ($dipakai > 0) {
            return $this->selesaiAjax($request, $filter,
                'Profesi "'.$profesi->nama.'" tidak dapat dihapus karena masih dipakai oleh '
                .$dipakai.' pegawai. Pindahkan pegawai ke profesi lain terlebih dahulu.', false);
        }

        $nama = $profesi->nama;
        $profesi->delete();

        catat_aktivitas('Hapus Profesi', $nama);

        return $this->selesaiAjax($request, $filter, 'Profesi dihapus.');
    }

    private function validasiNama(string $nama, int $kecualiId): string
    {
        if ($nama === '') {
            return 'Nama profesi wajib diisi.';
        }
        if (mb_strlen($nama) > 100) {
            return 'Nama profesi maksimal 100 karakter.';
        }
        if (Profesi::where('nama', $nama)->where('id', '!=', $kecualiId)->exists()) {
            return 'Profesi "'.$nama.'" sudah terdaftar.';
        }

        return '';
    }

    private function query(array $filter)
    {
        $q = Profesi::withCount('users')->orderBy('nama');

        if ($filter['q'] !== '') {
            $q->where('nama', 'like', "%{$filter['q']}%");
        }

        return $q;
    }

    private function tabelAjax(array $filter, string $pesan = '', bool $sukses = true)
    {
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return $this->balasAjax(view('admin.profesi.tabel', [
            'daftar' => $daftar,
            'q' => $filter['q'],
        ])->render(), [
            'pesan' => $pesan,
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
            return redirect('admin/profesi')->with($sukses ? 'success' : 'error', $pesan);
        }

        return $this->tabelAjax($filter, $pesan, $sukses);
    }
}
