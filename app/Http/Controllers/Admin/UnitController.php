<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function index()
    {
        $unitList = DB::table('unit_kerja as uk')
            ->selectRaw('uk.*, (SELECT COUNT(*) FROM users u WHERE u.unit_kerja_id = uk.id) AS jml_pegawai')
            ->orderBy('uk.id')->get()->all();

        $subPerUnit = [];
        foreach (DB::table('sub_unit as su')
                     ->selectRaw('su.*, (SELECT COUNT(*) FROM users u WHERE u.sub_unit_id = su.id) AS jml_pegawai')
                     ->orderBy('su.unit_kerja_id')->orderBy('su.id')
                     ->get() as $s) {
            $subPerUnit[(int) $s->unit_kerja_id][] = $s;
        }

        return view('admin.unit', [
            'judulHalaman' => 'Data Unit Kerja',
            'menuAktif'    => 'unit',
            'unitList'     => $unitList,
            'subPerUnit'   => $subPerUnit,
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id   = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));

        switch ($aksi) {
            case 'tambah_unit':
                if ($nama === '') {
                    return $this->kembali('flash_gagal', 'Nama unit wajib diisi.');
                }
                DB::table('unit_kerja')->insert([
                    'nama' => $nama,
                    'punya_sub' => $request->input('punya_sub') ? 1 : 0,
                ]);
                catat_aktivitas('Tambah Unit', $nama);
                return $this->kembali('flash_sukses', 'Unit kerja ditambahkan.');

            case 'ubah_unit':
                if ($nama !== '') {
                    DB::table('unit_kerja')->where('id', $id)->update([
                        'nama' => $nama,
                        'punya_sub' => $request->input('punya_sub') ? 1 : 0,
                    ]);
                    catat_aktivitas('Ubah Unit', $nama);
                }
                return $this->kembali('flash_sukses', 'Unit kerja diperbarui.');

            case 'hapus_unit':
                $dipakai = DB::table('users')->where('unit_kerja_id', $id)->count() > 0;
                if ($dipakai) {
                    return $this->kembali('flash_gagal',
                        'Unit tidak dapat dihapus karena masih memiliki pegawai. Pindahkan pegawainya terlebih dahulu.');
                }
                $u = DB::table('unit_kerja')->where('id', $id)->first();
                DB::table('unit_kerja')->where('id', $id)->delete();
                catat_aktivitas('Hapus Unit', $u->nama ?? ('#' . $id));
                return $this->kembali('flash_sukses', 'Unit kerja beserta sub unitnya dihapus.');

            case 'tambah_sub':
                $unitId = (int) $request->input('unit_kerja_id');
                if ($unitId && $nama !== '') {
                    DB::table('sub_unit')->insert(['unit_kerja_id' => $unitId, 'nama' => $nama]);
                    DB::table('unit_kerja')->where('id', $unitId)->update(['punya_sub' => 1]);
                    catat_aktivitas('Tambah Sub Unit', $nama);
                }
                return $this->kembali('flash_sukses', 'Sub unit ditambahkan.');

            case 'hapus_sub':
                $dipakai = DB::table('users')->where('sub_unit_id', $id)->count() > 0;
                if ($dipakai) {
                    return $this->kembali('flash_gagal',
                        'Sub unit tidak dapat dihapus karena masih memiliki pegawai.');
                }
                $s = DB::table('sub_unit')->where('id', $id)->first();
                DB::table('sub_unit')->where('id', $id)->delete();
                catat_aktivitas('Hapus Sub Unit', $s->nama ?? ('#' . $id));
                return $this->kembali('flash_sukses', 'Sub unit dihapus.');
        }
        return $this->kembali('flash_gagal', 'Aksi tidak dikenal.');
    }

    private function kembali(string $kunci, string $pesan)
    {
        return redirect('admin/unit')->with($kunci, $pesan);
    }
}
