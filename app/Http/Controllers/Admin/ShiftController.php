<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $shiftList = Shift::orderBy('jam_masuk')->get()->all();

        $q = trim((string) $request->get('q'));
        $fUnit = (int) $request->get('unit');

        $b = User::select('users.id', 'users.nama_lengkap', 'uk.nama AS unit_nama', 'su.nama AS sub_nama',
            'p.nama AS profesi_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->where('users.role', 'pegawai')->where('users.status', 'aktif');
        if ($q !== '') {
            $b->where('users.nama_lengkap', 'like', "%{$q}%");
        }
        if ($fUnit) {
            $b->where('users.unit_kerja_id', $fUnit);
        }
        $pegawai = $b->orderBy('users.nama_lengkap')->get()->all();

        $grup = [];
        foreach ($shiftList as $s) {
            if ($s->aktif) {
                $grup[$s->kategori][] = $s;
            }
        }
        // dd($shiftList);

        return view('admin.shift', [
            'judulHalaman' => 'Pengaturan Shift',
            'menuAktif' => 'shift',
            'shiftList' => $shiftList,
            'shiftGrup' => $grup,
            'pegawai' => $pegawai,
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'q' => $q,
            'fUnit' => $fUnit,
            'izin' => pengaturan('izinkan_pilih_shift', '1') === '1',
            'qs' => http_build_query(array_filter(['q' => $q, 'unit' => $fUnit ?: null])),
        ]);
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id = (int) $request->input('id');
        $qs = (string) $request->input('qs');
        $ke = 'admin/shift'.($qs ? '?'.$qs : '');

        switch ($aksi) {
            case 'tambah_shift':
                $kategori = (string) $request->input('kategori');
                $masuk = (string) $request->input('jam_masuk');
                $pulang = (string) $request->input('jam_pulang');
                if (! in_array($kategori, ['Pagi', 'Sore', 'Malam'], true) || ! $masuk || ! $pulang) {
                    return redirect($ke)->with('error', 'Kategori dan jam shift wajib diisi.');
                }
                Shift::create([
                    'kategori' => $kategori,
                    'jam_masuk' => $masuk,
                    'jam_pulang' => $pulang,
                    'lintas_hari' => ($pulang <= $masuk) ? 1 : 0,
                    'aktif' => 1,
                ]);
                catat_aktivitas('Tambah Shift', "$kategori $masuk-$pulang");

                return redirect($ke)->with('success', 'Shift baru ditambahkan.');

            case 'toggle_shift':
                \DB::update('UPDATE shift SET aktif = 1 - aktif WHERE id = ?', [$id]);

                return redirect($ke)->with('success', 'Status shift diperbarui.');

            case 'hapus_shift':
                $dipakai = JadwalShift::where('shift_id', $id)->count()
                         + Absensi::where('shift_id', $id)->count();
                if ($dipakai > 0) {
                    return redirect($ke)->with('error',
                        'Shift tidak dapat dihapus karena masih dipakai pegawai atau data absensi. Gunakan Nonaktifkan.');
                }
                Shift::where('id', $id)->delete();
                catat_aktivitas('Hapus Shift', '#'.$id);

                return redirect($ke)->with('success', 'Shift dihapus.');

            case 'atur_pegawai':
                $userId = (int) $request->input('user_id');
                $shiftId = (int) $request->input('shift_id') ?: null;

                $lama = User::select('id', 'nama_lengkap')->where('id', $userId)->first();
                if (! $lama) {
                    return redirect($ke)->with('error', 'Pegawai tidak ditemukan.');
                }

                JadwalShift::where('user_id', $userId)
                    ->where('tanggal_berlaku', now()->toDateString())
                    ->delete();

                if ($shiftId) {
                    JadwalShift::create([
                        'user_id' => $userId, 'shift_id' => $shiftId,
                        'tanggal_berlaku' => now()->toDateString(),
                        'diubah_oleh' => session('uid'), 'created_at' => now(),
                    ]);
                    catat_aktivitas('Atur Shift Pegawai', $lama->nama_lengkap);
                } else {
                    catat_aktivitas('Atur Shift Pegawai', $lama->nama_lengkap.' (dikosongkan)');
                }

                return redirect($ke)->with('success',
                    'Jadwal shift pegawai untuk hari ini diperbarui. Untuk jadwal harian, gunakan menu Atur Jadwal Shift.');

            case 'izin_pilih':
                simpan_pengaturan('izinkan_pilih_shift', $request->input('izinkan') ? '1' : '0');
                catat_aktivitas('Pengaturan', 'Izin pemilihan shift mandiri diubah');

                return redirect($ke)->with('success', 'Pengaturan izin pemilihan shift diperbarui.');
        }

        return redirect($ke)->with('error', 'Aksi tidak dikenal.');
    }
}
