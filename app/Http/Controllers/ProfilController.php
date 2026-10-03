<?php

namespace App\Http\Controllers;

use App\Models\MappingSIMRSAccount;
use App\Models\User;
use App\Services\ProfilService;
use App\Services\PasswordService;
use App\Services\SimrsService;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function form()
    {
        $u = User::with(['unitKerja', 'subUnit', 'profesi', 'jabatan', 'mappingSimrs'])
            ->find(session('uid'));

        if (! $u) {
            return redirect('login')->with('error', 'Sesi login tidak valid.');
        }

        return view('pegawai.update_data', [
            'judulHalaman' => 'Update Data',
            'u'            => $u,
            'agamaList'    => ProfilService::AGAMA,
        ]);
    }

    public function cekSimrs(SimrsService $simrs)
    {
        $mapping = MappingSIMRSAccount::where('user_id', (int) session('uid'))->first();
        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Akun Anda belum memiliki mapping ID SIMRS.',
            ]);
        }

        return response()->json(
            $simrs->cekMapping($mapping->simrs_user_id)
        );
    }

    public function checkSimrsId(Request $request, SimrsService $simrs)
    {
        $kode = trim((string) $request->query('id'));
        if ($kode === '') {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Masukkan ID SIMRS terlebih dahulu.',
            ]);
        }

        $dipakai = MappingSIMRSAccount::where('simrs_user_id', $kode)
            ->where('user_id', '!=', (int) session('uid'))->exists();
        if ($dipakai) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'ID SIMRS tersebut sudah dipakai pengguna lain.',
            ]);
        }

        return response()->json(
            $simrs->cekMapping($kode)
        );
    }

    public function simpanMapping(Request $request)
    {
        $user = User::find(session('uid'));
        if (! $user) {
            return redirect()->route('pegawai.update-data')->with('error', 'Sesi login tidak valid.');
        }

        $mappingLama = MappingSIMRSAccount::where('user_id', $user->id)->first();

        $kode = trim((string) $request->input('simrs_user_id'));
        if ($kode === '') {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'ID SIMRS wajib diisi.');
        }

        if (mb_strlen($kode) > 100) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'ID SIMRS maksimal 100 karakter.');
        }

        if (MappingSIMRSAccount::where('simrs_user_id', $kode)
            ->where('user_id', '!=', $user->id)->exists()) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'ID SIMRS tersebut sudah dipakai pengguna lain.');
        }

        MappingSIMRSAccount::updateOrCreate(
            ['user_id' => $user->id],
            ['simrs_user_id' => $kode]
        );

        catat_aktivitas('Mapping SIMRS', $user->nama_lengkap . ' → ' . $kode);

        return redirect()->route('pegawai.update-data')
            ->with('success', 'Mapping akun SIMRS berhasil disimpan. Gunakan tombol Tes Mapping untuk memverifikasi.');
    }
    public function ubahPassword(Request $request, PasswordService $password)
    {
        $passLama = (string) $request->input('password_lama');
        $passBaru = (string) $request->input('password_baru');
        $passKonf = (string) $request->input('password_konfirmasi');

        $user = User::find(session('uid'));
        if (! $user) {
            return back()->with('error', 'Sesi login tidak valid.');
        }

        if (! password_verify($passLama, $user->password_hash)) {
            return back()->with('error', 'Password lama tidak sesuai.');
        }

        if (strlen($passBaru) < 6) {
            return back()->with('error', 'Password baru minimal 6 karakter.');
        }

        if ($passBaru !== $passKonf) {
            return back()->with('error', 'Konfirmasi password baru tidak cocok.');
        }

        $user->update([
            'password_hash' => bcrypt($passBaru),
        ]);

        $password->tandaiDiubah($user, 'di halaman profil');

        catat_aktivitas('Ubah Password', $user->nama_lengkap . ' mengubah password akunnya');

        return back()->with('success', 'Password Anda berhasil diperbarui.');
    }

    public function updateData(Request $request, ProfilService $profil)
    {
        $user = User::find(session('uid'));
        if (! $user) {
            return redirect()->route('pegawai.update-data')->with('error', 'Sesi login tidak valid.');
        }

        $hasil = $profil->perbarui($user, $request->all());

        if (! $hasil['sukses']) {
            return redirect()->route('pegawai.update-data')
                ->withInput()
                ->with('error', $hasil['pesan']);
        }

        session()->put([
            'nama'  => $user->nama_lengkap,
            'email' => $user->email,
        ]);

        return redirect()->route('pegawai.update-data')
            ->with('success', $hasil['pesan']);
    }
}
