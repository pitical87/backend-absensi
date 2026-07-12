<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StrukturService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PegawaiController extends Controller
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    public function index(Request $request)
    {
        $q     = trim((string) $request->get('q'));
        $fUnit = (int) $request->get('unit');

        $b = DB::table('users as u')
            ->select('u.*', 'uk.nama AS unit_nama', 'su.nama AS sub_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang',
                     'j.nama AS jabatan_nama')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'u.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('u.nama_lengkap', 'like', "%{$q}%")->orWhere('u.email', 'like', "%{$q}%");
            });
        }
        if ($fUnit) {
            $b->where('u.unit_kerja_id', $fUnit);
        }
        $pegawai = $b->orderBy('u.nama_lengkap')->get()->all();

        return view('admin.pegawai_index', [
            'judulHalaman' => 'Data Pegawai',
            'menuAktif'    => 'pegawai',
            'pegawai'      => $pegawai,
            'unitList'     => DB::table('unit_kerja')->orderBy('id')->get()->all(),
            'q'            => $q,
            'fUnit'        => $fUnit,
        ]);
    }

    public function form(StrukturService $struktur,int $id = 0)
    {
        $edit = null;
        if ($id) {
            $edit = DB::table('users')->where('id', $id)->first();
            if (! $edit) {
                return redirect('admin/pegawai')->with('flash_gagal', 'Pegawai tidak ditemukan.');
            }
        }

        $sub = [];
        foreach (DB::table('sub_unit')->orderBy('unit_kerja_id')->orderBy('id')->get() as $s) {
            $sub[(int) $s->unit_kerja_id][] = ['id' => (int) $s->id, 'nama' => $s->nama];
        }
        $shiftGrup = [];
        foreach (DB::table('shift')->where('aktif', 1)->orderBy('jam_masuk')->get() as $s) {
            $shiftGrup[$s->kategori][] = $s;
        }

        return view('admin.pegawai_form', [
            'judulHalaman' => $edit ? 'Ubah Data Pegawai' : 'Tambah Pegawai',
            'menuAktif'    => 'pegawai',
            'edit'         => $edit,
            'unitList'     => DB::table('unit_kerja')->orderBy('id')->get()->all(),
            'profList'     => DB::table('profesi')->orderBy('id')->get()->all(),
            'subPerUnit'   => $sub,
            'shiftGrup'    => $shiftGrup,
            'agamaList'    => self::AGAMA,
            'jabPilihan'   => $struktur->pilihan(),
            'kategoriJab'  => kategori_jabatan_list(),
            'posisiList'   => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ]);
    }

    public function simpan(Request $request, StrukturService $struktur)
    {
        $id = (int) $request->input('id');
        $d  = [
            'nama_lengkap'  => trim((string) $request->input('nama_lengkap')),
            'tempat_lahir'  => trim((string) $request->input('tempat_lahir')) ?: null,
            'tanggal_lahir' => $request->input('tanggal_lahir') ?: null,
            'jenis_kelamin' => $request->input('jenis_kelamin') ?: null,
            'agama'         => $request->input('agama') ?: null,
            'email'         => trim((string) $request->input('email')),
            'no_hp'         => trim((string) $request->input('no_hp')) ?: null,
            'nip'           => trim((string) $request->input('nip')) ?: null,
            'unit_kerja_id' => (int) $request->input('unit_kerja_id') ?: null,
            'sub_unit_id'   => (int) $request->input('sub_unit_id') ?: null,
            'profesi_id'    => (int) $request->input('profesi_id') ?: null,
            'shift_id'      => (int) $request->input('shift_id') ?: null,
            'role'          => in_array($request->input('role'), ['admin', 'pegawai'], true)
                               ? $request->input('role') : 'pegawai',
            'status'        => in_array($request->input('status'), ['aktif', 'nonaktif'], true)
                               ? $request->input('status') : 'aktif',
        ];
        $pass = (string) $request->input('password');

        $galat = [];
        if ($d['nama_lengkap'] === '') $galat[] = 'Nama lengkap wajib diisi.';
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Email tidak valid.';
        if (! $id && strlen($pass) < 6) $galat[] = 'Password minimal 6 karakter untuk pegawai baru.';
        if ($id && $pass !== '' && strlen($pass) < 6) $galat[] = 'Password baru minimal 6 karakter.';

        $dupe = DB::table('users')->where('email', $d['email'])->where('id', '!=', $id)->count() > 0;
        if ($dupe) $galat[] = 'Email sudah digunakan pegawai lain.';

        [$kategoriJab, $jabatanId, $galatJab] = $struktur->resolusi(
            (string) $request->input('jabatan_kategori'),
            (int) $request->input('jabatan_id'),
            $id
        );
        if ($galatJab !== '') $galat[] = $galatJab;
        $d['jabatan_kategori'] = $kategoriJab;
        $d['jabatan_id']       = $jabatanId;

        $statusPegawai = (string) $request->input('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$posisi, $seksiPembinaId, $galatPosisi] = $struktur->resolusiPosisi(
            (string) $request->input('posisi'),
            $kategoriJab,
            $jabatanId,
            (int) $request->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') $galat[] = $galatPosisi;
        $d['posisi']           = $posisi;
        $d['status_pegawai']   = $statusPegawai;
        $d['seksi_pembina_id'] = $seksiPembinaId;

        if ($d['sub_unit_id']) {
            $sah = DB::table('sub_unit')->where('id', $d['sub_unit_id'])
                        ->where('unit_kerja_id', $d['unit_kerja_id'])->count() > 0;
            if (! $sah) $d['sub_unit_id'] = null;
        }

        if ($galat) {
            return redirect('admin/pegawai/form' . ($id ? '/' . $id : ''))
                ->with('flash_gagal', implode(' ', $galat));
        }

        if ($id) {
            $lama = DB::table('users')->select('shift_id')->where('id', $id)->first();
            if ($pass !== '') {
                $d['password_hash'] = bcrypt($pass);
            }
            DB::table('users')->where('id', $id)->update($d);

            if ($d['shift_id'] && (int) ($lama->shift_id ?? 0) !== (int) $d['shift_id']) {
                DB::table('jadwal_shift')->insert([
                    'user_id' => $id, 'shift_id' => $d['shift_id'],
                    'tanggal_berlaku' => now()->format('Y-m-d'),
                    'diubah_oleh' => session('uid'), 'created_at' => now(),
                ]);
            }
            catat_aktivitas('Ubah Pegawai', $d['nama_lengkap'] . ' (' . $d['email'] . ')');
            $pesan = 'Data pegawai diperbarui.';
        } else {
            $d['password_hash'] = bcrypt($pass);
            $d['created_at']    = now();
            DB::table('users')->insert($d);
            catat_aktivitas('Tambah Pegawai', $d['nama_lengkap'] . ' (' . $d['email'] . ')');
            $pesan = 'Pegawai baru berhasil ditambahkan.';
        }

        return redirect('admin/pegawai')->with('flash_sukses', $pesan);
    }

    public function ubahStatus(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id === (int) session('uid')) {
            return redirect('admin/pegawai')
                ->with('flash_gagal', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $u = DB::table('users')->where('id', $id)->first();
        if ($u) {
            $baru = $u->status === 'aktif' ? 'nonaktif' : 'aktif';
            DB::table('users')->where('id', $id)->update(['status' => $baru]);
            catat_aktivitas('Status Pegawai', $u->nama_lengkap . ' → ' . $baru);
        }
        return redirect('admin/pegawai')->with('flash_sukses', 'Status pegawai diperbarui.');
    }

    public function hapus(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id === (int) session('uid')) {
            return redirect('admin/pegawai')
                ->with('flash_gagal', 'Anda tidak dapat menghapus akun sendiri.');
        }
        $u = DB::table('users')->where('id', $id)->first();
        if ($u) {
            DB::table('users')->where('id', $id)->delete();
            catat_aktivitas('Hapus Pegawai', $u->nama_lengkap . ' (' . $u->email . ') beserta seluruh data absensinya');
        }
        return redirect('admin/pegawai')
            ->with('flash_sukses', 'Pegawai beserta seluruh data absensinya telah dihapus.');
    }
}
