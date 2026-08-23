<?php

namespace App\Http\Controllers;

use App\Models\MappingSIMRSAccount;
use App\Models\User;
use App\Services\SimrsService;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    private const JENIS_KELAMIN = ['Laki-Laki', 'Perempuan'];

    public function form()
    {
        $u = User::with(['unitKerja', 'subUnit', 'profesi', 'jabatan', 'shift', 'mappingSimrs'])
            ->find(session('uid'));

        if (! $u) {
            return redirect('login')->with('error', 'Sesi login tidak valid.');
        }

        return view('pegawai.update_data', [
            'judulHalaman' => 'Update Data',
            'u'            => $u,
            'agamaList'    => self::AGAMA,
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
    public function ubahPassword(Request $request)
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

        catat_aktivitas('Ubah Password', $user->nama_lengkap . ' mengubah password akunnya');

        return back()->with('success', 'Password Anda berhasil diperbarui.');
    }

    public function updateData(Request $request)
    {
        $user = User::find(session('uid'));
        if (! $user) {
            return redirect()->route('pegawai.update-data')->with('error', 'Sesi login tidak valid.');
        }

        $nama   = trim((string) $request->input('nama_lengkap'));
        $email  = strtolower(trim((string) $request->input('email')));
        $noHp   = trim((string) $request->input('no_hp'));
        $tempat = trim((string) $request->input('tempat_lahir'));
        $tgl    = trim((string) $request->input('tanggal_lahir'));
        $jk     = (string) $request->input('jenis_kelamin', '');
        $agama  = (string) $request->input('agama', '');

        if ($nama === '' || $email === '') {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Nama lengkap dan email wajib diisi.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Format email tidak valid.');
        }

        if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Email sudah digunakan oleh akun lain.');
        }

        if ($jk !== '' && ! in_array($jk, self::JENIS_KELAMIN, true)) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Jenis kelamin tidak valid.');
        }

        if ($agama !== '' && ! in_array($agama, self::AGAMA, true)) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Agama tidak valid.');
        }

        if ($tgl !== '' && strtotime($tgl) === false) {
            return redirect()->route('pegawai.update-data')
                ->with('error', 'Tanggal lahir tidak valid.');
        }

        $user->update([
            'nama_lengkap'  => mb_substr($nama, 0, 150),
            'email'         => mb_substr($email, 0, 150),
            'no_hp'         => $noHp !== '' ? mb_substr($noHp, 0, 30) : null,
            'tempat_lahir'  => $tempat !== '' ? mb_substr($tempat, 0, 100) : null,
            'tanggal_lahir' => $tgl !== '' ? date('Y-m-d', strtotime($tgl)) : null,
            'jenis_kelamin' => $jk !== '' ? $jk : null,
            'agama'         => $agama !== '' ? $agama : null,
        ]);

        session()->put([
            'nama'  => $user->nama_lengkap,
            'email' => $user->email,
        ]);

        catat_aktivitas('Update Data', $user->nama_lengkap . ' memperbarui data akunnya');

        return redirect()->route('pegawai.update-data')
            ->with('success', 'Data akun berhasil diperbarui.');
    }
}
