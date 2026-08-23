<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $unitList = UnitKerja::select('unit_kerja.*', \DB::raw('(SELECT COUNT(*) FROM users u WHERE u.unit_kerja_id = unit_kerja.id) AS jml_pegawai'))
            ->orderBy('unit_kerja.id')->get()->all();

        $subPerUnit = [];
        foreach (SubUnit::select('sub_unit.*', \DB::raw('(SELECT COUNT(*) FROM users u WHERE u.sub_unit_id = sub_unit.id) AS jml_pegawai'))
                     ->orderBy('sub_unit.unit_kerja_id')->orderBy('sub_unit.id')
                     ->get() as $s) {
            $subPerUnit[(int) $s->unit_kerja_id][] = $s;
        }

        return view('admin.unit', [
            'judulHalaman' => 'Data Unit Kerja',
            'menuAktif'    => 'unit',
            'unitList'     => $unitList,
            'subPerUnit'   => $subPerUnit,
            'pegawaiPilihan' => User::where('role', '!=', 'admin')->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id   = (int) $request->input('id');
        $nama = trim((string) $request->input('nama'));
        $atasanId = (int) $request->input('atasan_id') ?: null;

        switch ($aksi) {
            case 'tambah_unit':
                if ($nama === '') {
                    return $this->kembali('error', 'Nama unit wajib diisi.');
                }
                UnitKerja::create([
                    'nama' => $nama,
                    'punya_sub' => $request->input('punya_sub') ? 1 : 0,
                ]);
                catat_aktivitas('Tambah Unit', $nama);
                return $this->kembali('success', 'Unit kerja ditambahkan.');

            case 'ubah_unit':
                if ($nama !== '') {
                    UnitKerja::where('id', $id)->update([
                        'nama' => $nama,
                        'punya_sub' => $request->input('punya_sub') ? 1 : 0,
                        'atasan_id' => $atasanId,
                    ]);
                    catat_aktivitas('Ubah Unit', $nama);
                }
                return $this->kembali('success', 'Unit kerja diperbarui.');

            case 'hapus_unit':
                $dipakai = User::where('unit_kerja_id', $id)->count() > 0;
                if ($dipakai) {
                    return $this->kembali('error',
                        'Unit tidak dapat dihapus karena masih memiliki pegawai. Pindahkan pegawainya terlebih dahulu.');
                }
                $u = UnitKerja::find($id);
                UnitKerja::where('id', $id)->delete();
                catat_aktivitas('Hapus Unit', $u->nama ?? ('#' . $id));
                return $this->kembali('success', 'Unit kerja beserta sub unitnya dihapus.');

            case 'tambah_sub':
                $unitId = (int) $request->input('unit_kerja_id');
                if ($unitId && $nama !== '') {
                    SubUnit::create(['unit_kerja_id' => $unitId, 'nama' => $nama, 'atasan_id' => $atasanId]);
                    UnitKerja::where('id', $unitId)->update(['punya_sub' => 1]);
                    catat_aktivitas('Tambah Sub Unit', $nama);
                }
                return $this->kembali('success', 'Sub unit ditambahkan.');

            case 'ubah_sub':
                if ($nama !== '') {
                    SubUnit::where('id', $id)->update(['nama' => $nama, 'atasan_id' => $atasanId]);
                    catat_aktivitas('Ubah Sub Unit', $nama);
                }
                return $this->kembali('success', 'Sub unit diperbarui.');

            case 'hapus_sub':
                $dipakai = User::where('sub_unit_id', $id)->count() > 0;
                if ($dipakai) {
                    return $this->kembali('error',
                        'Sub unit tidak dapat dihapus karena masih memiliki pegawai.');
                }
                $s = SubUnit::find($id);
                SubUnit::where('id', $id)->delete();
                catat_aktivitas('Hapus Sub Unit', $s->nama ?? ('#' . $id));
                return $this->kembali('success', 'Sub unit dihapus.');
        }
        return $this->kembali('error', 'Aksi tidak dikenal.');
    }

    private function kembali(string $kunci, string $pesan)
    {
        return redirect('admin/unit')->with($kunci, $pesan);
    }
}
