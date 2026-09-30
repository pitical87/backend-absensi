<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\VerifikasiEmailService;
use Illuminate\Http\Request;

class VerifikasiEmailController extends Controller
{
    public function form(Request $request, VerifikasiEmailService $servis)
    {
        $user = User::find(session('uid'));
        if (! $user) {
            return redirect()->route('login')->with('galat', 'Sesi login tidak valid.');
        }

        return view('pegawai.verifikasi-email', [
            'judulHalaman' => 'Verifikasi Email',
            'u'            => $user,
            'status'       => $servis->status($user),
        ]);
    }

    public function kirim(Request $request, VerifikasiEmailService $servis)
    {
        $user = User::find(session('uid'));
        if (! $user) {
            return redirect()->route('login')->with('galat', 'Sesi login tidak valid.');
        }

        $hasil = $servis->kirim($user, (string) $request->input('email'));

        if (! $hasil['sukses']) {
            return back()->withInput()->with('error', $hasil['pesan']);
        }

        return back()->with('success', $hasil['pesan']);
    }

    /**
     * Ditutupi tautan pada email. Sengaja tanpa middleware `auth` supaya
     * tautan tetap bisa dibuka dari perangkat lain tanpa harus login.
     */
    public function konfirmasi(Request $request, VerifikasiEmailService $servis)
    {
        $hasil = $servis->konfirmasi(
            (int) $request->route('id'),
            (string) $request->route('token'),
        );

        if (! $hasil['sukses']) {
            return redirect()->route('verifikasi-email.hasil')
                ->with(['hasil' => 'gagal', 'alasan' => $hasil['pesan']]);
        }

        return redirect()->route('verifikasi-email.hasil')->with([
            'hasil' => 'sukses',
            'nama'  => $hasil['nama'],
            'email' => $hasil['email'],
            'waktu' => $hasil['waktu']?->toIso8601String(),
        ]);
    }

    public function hasil()
    {
        $hasil = session('hasil');

        // dibuka langsung tanpa melalui tautan verifikasi
        if (! in_array($hasil, ['sukses', 'gagal'], true)) {
            return redirect()->route('login');
        }

        $sudahMasuk = (bool) session('uid');

        return view('auth.verifikasi-email-selesai', [
            'hasil'        => $hasil,
            'alasan'       => (string) session('alasan', ''),
            'nama'         => (string) session('nama', ''),
            'email'        => (string) session('email', ''),
            'waktu'        => session('waktu'),
            'sudahMasuk'   => $sudahMasuk,
            'tombolMasuk'  => $sudahMasuk
                ? (session('role') === 'admin' ? url('admin') : route('dashboard'))
                : route('login'),
        ]);
    }
}
