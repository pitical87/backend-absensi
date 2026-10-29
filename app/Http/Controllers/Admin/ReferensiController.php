<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DaftarAjax;
use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Models\Jabatans;
use App\Models\PosisiJabatan;
use App\Models\User;
use Illuminate\Http\Request;

class ReferensiController extends Controller
{
    use DaftarAjax;

    public function index(Request $request)
    {
        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        return view('admin.referensi.index', [
            'judulHalaman' => 'Jabatan & Posisi',
            'menuAktif' => 'referensi',
            'daftarKategori' => $this->paginateAjax($this->queryKategori($filter), $filter),
            'daftarPosisi' => $this->paginateAjax($this->queryPosisi($filter), $filter),
            'q' => $filter['q'],
        ]);
    }

    public function data(Request $request, string $jenis)
    {
        if ($jenis !== 'kategori' && $jenis !== 'posisi') {
            return $this->balasAjax('', ['pesan' => 'Jenis tidak dikenal.'], false, 404);
        }

        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        return $this->tabelAjax($jenis, $filter);
    }

    public function aksi(Request $request, string $jenis)
    {
        if ($jenis !== 'kategori' && $jenis !== 'posisi') {
            return $this->balasAjax('', ['pesan' => 'Jenis tidak dikenal.'], false, 404);
        }

        $aksi = (string) $request->input('aksi');
        $filter = $this->filterAjax($request, ['Semua'], 'Semua');

        if ($aksi === 'tambah') {
            return $this->handleTambah($request, $jenis, $filter);
        }
        if ($aksi === 'ubah') {
            return $this->handleUbah($request, $jenis, $filter);
        }
        if ($aksi === 'hapus') {
            return $this->handleHapus($request, $jenis, $filter);
        }

        return $this->selesaiAjax($request, $jenis, $filter, 'Aksi tidak dikenal.', false);
    }

    private function handleTambah(Request $request, string $jenis, array $filter)
    {
        $nama = trim((string) $request->input('nama'));
        $urutan = max(0, (int) $request->input('urutan'));

        if ($galat = $this->validasiNama($jenis, $nama, 0)) {
            return $this->selesaiAjax($request, $jenis, $filter, $galat, false);
        }

        if ($jenis === 'kategori') {
            Jabatans::create([
                'nama' => $nama,
                'untuk_struktur' => $request->boolean('untuk_struktur'),
                'urutan' => $urutan,
            ]);
            catat_aktivitas('Tambah Kategori Jabatan', $nama);
        } else {
            $tingkat = (int) $request->input('tingkat');
            if ($tingkat < 0 || $tingkat > 9) {
                return $this->selesaiAjax($request, $jenis, $filter, 'Tingkat harus berupa angka 0–9.', false);
            }
            PosisiJabatan::create([
                'nama' => $nama,
                'tingkat' => $tingkat,
                'urutan' => $urutan,
            ]);
            catat_aktivitas('Tambah Posisi Jabatan', $nama.' (tingkat '.$tingkat.')');
        }

        return $this->selesaiAjax($request, $jenis, $filter, 'Data ditambahkan.');
    }

    private function handleUbah(Request $request, string $jenis, array $filter)
    {
        $id = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));
        $urutan = max(0, (int) $request->input('urutan'));

        $baris = $jenis === 'kategori'
            ? Jabatans::find($id)
            : PosisiJabatan::find($id);

        if (! $baris) {
            return $this->selesaiAjax($request, $jenis, $filter, 'Data tidak ditemukan.', false);
        }

        if ($galat = $this->validasiNama($jenis, $nama, $id)) {
            return $this->selesaiAjax($request, $jenis, $filter, $galat, false);
        }

        $lama = $baris->nama;

        if ($jenis === 'kategori') {
            $baris->update([
                'nama' => $nama,
                'untuk_struktur' => $request->boolean('untuk_struktur'),
                'urutan' => $urutan,
            ]);
            if ($lama !== $nama) {
                User::where('jabatan_kategori', $lama)->update(['jabatan_kategori' => $nama]);
                Jabatan::where('kategori', $lama)->update(['kategori' => $nama]);
            }
            catat_aktivitas('Ubah Kategori Jabatan', $lama.' → '.$nama);
        } else {
            $tingkat = (int) $request->input('tingkat');
            if ($tingkat < 0 || $tingkat > 9) {
                return $this->selesaiAjax($request, $jenis, $filter, 'Tingkat harus berupa angka 0–9.', false);
            }
            $baris->update([
                'nama' => $nama,
                'tingkat' => $tingkat,
                'urutan' => $urutan,
            ]);
            if ($lama !== $nama) {
                User::where('posisi', $lama)->update(['posisi' => $nama]);
            }
            catat_aktivitas('Ubah Posisi Jabatan', $lama.' → '.$nama);
        }

        return $this->selesaiAjax($request, $jenis, $filter, 'Data diperbarui.');
    }

    private function handleHapus(Request $request, string $jenis, array $filter)
    {
        $id = (int) $request->input('id');
        $baris = $jenis === 'kategori'
            ? Jabatans::find($id)
            : PosisiJabatan::find($id);

        if (! $baris) {
            return $this->selesaiAjax($request, $jenis, $filter, 'Data tidak ditemukan.', false);
        }

        $jumlahJabatan = $jenis === 'kategori' ? Jabatan::where('kategori', $baris->nama)->count() : 0;
        $jumlahPegawai = $jenis === 'kategori'
            ? User::where('jabatan_kategori', $baris->nama)->count()
            : User::where('posisi', $baris->nama)->count();

        if ($jumlahJabatan > 0 || $jumlahPegawai > 0) {
            $rincian = [];
            if ($jumlahJabatan > 0) {
                $rincian[] = $jumlahJabatan.' jabatan struktur';
            }
            if ($jumlahPegawai > 0) {
                $rincian[] = $jumlahPegawai.' pegawai';
            }

            return $this->selesaiAjax($request, $jenis, $filter,
                'Data "'.$baris->nama.'" tidak dapat dihapus karena masih dipakai oleh '
                .implode(' dan ', $rincian).'. Ubah data tersebut terlebih dahulu.', false);
        }

        $nama = $baris->nama;
        $baris->delete();

        catat_aktivitas(
            $jenis === 'kategori' ? 'Hapus Kategori Jabatan' : 'Hapus Posisi Jabatan',
            $nama
        );

        return $this->selesaiAjax($request, $jenis, $filter, 'Data dihapus.');
    }

    private function validasiNama(string $jenis, string $nama, int $kecualiId): string
    {
        $maks = $jenis === 'kategori' ? 100 : 50;

        if ($nama === '') {
            return 'Nama wajib diisi.';
        }
        if (mb_strlen($nama) > $maks) {
            return 'Nama maksimal '.$maks.' karakter.';
        }

        $ada = $jenis === 'kategori'
            ? Jabatans::where('nama', $nama)->where('id', '!=', $kecualiId)->exists()
            : PosisiJabatan::where('nama', $nama)->where('id', '!=', $kecualiId)->exists();

        if ($ada) {
            return 'Nama "'.$nama.'" sudah terdaftar.';
        }

        return '';
    }

    private function queryKategori(array $filter)
    {
        $q = Jabatans::withCount(['jabatan', 'users'])
            ->orderBy('urutan')->orderBy('id');

        if ($filter['q'] !== '') {
            $q->where('nama', 'like', "%{$filter['q']}%");
        }

        return $q;
    }

    private function queryPosisi(array $filter)
    {
        $q = PosisiJabatan::withCount('users')
            ->orderBy('urutan')->orderBy('id');

        if ($filter['q'] !== '') {
            $q->where('nama', 'like', "%{$filter['q']}%");
        }

        return $q;
    }

    private function tabelAjax(string $jenis, array $filter, string $pesan = '', bool $sukses = true)
    {
        $daftar = $jenis === 'kategori'
            ? $this->paginateAjax($this->queryKategori($filter), $filter)
            : $this->paginateAjax($this->queryPosisi($filter), $filter);

        return $this->balasAjax(view('admin.referensi.tabel_'.$jenis, [
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

    private function selesaiAjax(Request $request, string $jenis, array $filter, string $pesan, bool $sukses = true)
    {
        if (! $request->expectsJson()) {
            return redirect('admin/jabatan-posisi')->with($sukses ? 'success' : 'error', $pesan);
        }

        return $this->tabelAjax($jenis, $filter, $pesan, $sukses);
    }
}
