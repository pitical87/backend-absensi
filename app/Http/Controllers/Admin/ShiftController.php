<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Shift;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    private function wantsJson(Request $request): bool
    {
        return $request->wantsJson() || $request->ajax() || $request->expectsJson();
    }

    private function daftarShift(Request $request): array
    {
        $q = trim((string) $request->get('q'));

        $b = Shift::query()
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($x) use ($q) {
                    $x->where('kategori', 'like', "%{$q}%")
                        ->orWhereRaw('strftime("%H:%M", jam_masuk) LIKE ?', ["%{$q}%"])
                        ->orWhereRaw('strftime("%H:%M", jam_pulang) LIKE ?', ["%{$q}%"]);
                });
            })
            ->orderBy('jam_masuk');

        $total = (clone $b)->count();
        $rows = $b->get();

        $html = view('admin.shift.rows', ['shiftList' => $rows])->render();

        return [
            'sukses' => true,
            'total' => $total,
            'html' => $html,
        ];
    }

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

        return view('admin.shift.index', [
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

    public function data(Request $request)
    {
        return response()->json($this->daftarShift($request));
    }

    public function aksi(Request $request)
    {
        $aksi = (string) $request->input('aksi');
        $id = (int) $request->input('id');
        $qs = (string) $request->input('qs');
        $ke = 'admin/shift'.($qs ? '?'.$qs : '');

        if ($this->wantsJson($request)) {
            try {
                switch ($aksi) {
                    case 'tambah_shift':
                        $kategori = (string) $request->input('kategori');
                        $masuk = (string) $request->input('jam_masuk');
                        $pulang = (string) $request->input('jam_pulang');
                        if (! in_array($kategori, ['Pagi', 'Sore', 'Malam'], true) || ! $masuk || ! $pulang) {
                            return response()->json(['sukses' => false, 'pesan' => 'Kategori dan jam shift wajib diisi.'], 422);
                        }
                        $s = Shift::create([
                            'kategori' => $kategori,
                            'jam_masuk' => $masuk,
                            'jam_pulang' => $pulang,
                            'lintas_hari' => ($pulang <= $masuk) ? 1 : 0,
                            'aktif' => 1,
                        ]);
                        catat_aktivitas('Tambah Shift', "$kategori $masuk-$pulang");

                        return response()->json(array_merge(['sukses' => true, 'pesan' => 'Shift baru ditambahkan.'], $this->daftarShift($request)));

                    case 'ubah_shift':
                        $s = Shift::find($id);
                        if (! $s) {
                            return response()->json(['sukses' => false, 'pesan' => 'Shift tidak ditemukan.'], 404);
                        }
                        $kategori = (string) $request->input('kategori');
                        $masuk = (string) $request->input('jam_masuk');
                        $pulang = (string) $request->input('jam_pulang');
                        if (! in_array($kategori, ['Pagi', 'Sore', 'Malam'], true) || ! $masuk || ! $pulang) {
                            return response()->json(['sukses' => false, 'pesan' => 'Kategori dan jam shift wajib diisi.'], 422);
                        }
                        $s->update([
                            'kategori' => $kategori,
                            'jam_masuk' => $masuk,
                            'jam_pulang' => $pulang,
                            'lintas_hari' => ($pulang <= $masuk) ? 1 : 0,
                        ]);
                        catat_aktivitas('Ubah Shift', "#{$s->id} $kategori $masuk-$pulang");

                        return response()->json(array_merge(['sukses' => true, 'pesan' => 'Shift diperbarui.'], $this->daftarShift($request)));

                    case 'toggle_shift':
                        $s = Shift::find($id);
                        if (! $s) {
                            return response()->json(['sukses' => false, 'pesan' => 'Shift tidak ditemukan.'], 404);
                        }
                        $s->update(['aktif' => (int) ! $s->aktif]);

                        return response()->json(array_merge(['sukses' => true, 'pesan' => 'Status shift diperbarui.'], $this->daftarShift($request)));

                    case 'hapus_shift':
                        $dipakai = JadwalShift::where('shift_id', $id)->count();
                        if ($dipakai > 0) {
                            return response()->json(['sukses' => false, 'pesan' => 'Shift tidak dapat dihapus karena masih dipakai pegawai.'], 422);
                        }
                        $s = Shift::find($id);
                        if ($s) {
                            $s->delete();
                            catat_aktivitas('Hapus Shift', '#'.$id);
                        }

                        return response()->json(array_merge(['sukses' => true, 'pesan' => 'Shift dihapus.'], $this->daftarShift($request)));
                }
            } catch (\Throwable $e) {
                return response()->json(['sukses' => false, 'pesan' => 'Terjadi kesalahan.'], 500);
            }

            return response()->json(['sukses' => false, 'pesan' => 'Aksi tidak dikenal.'], 400);
        }

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

            case 'ubah_shift':
                $s = Shift::find($id);
                if (! $s) {
                    return redirect($ke)->with('error', 'Shift tidak ditemukan.');
                }
                $kategori = (string) $request->input('kategori');
                $masuk = (string) $request->input('jam_masuk');
                $pulang = (string) $request->input('jam_pulang');
                if (! in_array($kategori, ['Pagi', 'Sore', 'Malam'], true) || ! $masuk || ! $pulang) {
                    return redirect($ke)->with('error', 'Kategori dan jam shift wajib diisi.');
                }
                $s->update([
                    'kategori' => $kategori,
                    'jam_masuk' => $masuk,
                    'jam_pulang' => $pulang,
                    'lintas_hari' => ($pulang <= $masuk) ? 1 : 0,
                ]);
                catat_aktivitas('Ubah Shift', "#{$s->id} $kategori $masuk-$pulang");

                return redirect($ke)->with('success', 'Shift diperbarui.');

            case 'toggle_shift':
                $s = Shift::find($id);
                if ($s) {
                    $s->update(['aktif' => (int) ! $s->aktif]);
                }

                return redirect($ke)->with('success', 'Status shift diperbarui.');

            case 'hapus_shift':
                $dipakai = JadwalShift::where('shift_id', $id)->count();
                if ($dipakai > 0) {
                    return redirect($ke)->with('error',
                        'Shift tidak dapat dihapus karena masih dipakai pegawai. Gunakan Nonaktifkan.');
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
