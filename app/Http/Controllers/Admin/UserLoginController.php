<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserLoginController extends Controller
{
    public function index()
    {
        $sesi = ApiToken::select(
                'api_tokens.*',
                'u.nama_lengkap',
                'u.status',
                'uk.nama as unit_nama',
                'su.nama as sub_unit_nama'
            )
            ->join('users as u', 'u.id', '=', 'api_tokens.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->where('u.status', 'aktif')
            ->where('api_tokens.expires_at', '>', now())
            ->orderBy('u.nama_lengkap')
            ->orderBy('api_tokens.created_at', 'DESC')
            ->get();

        $perUser = $sesi->groupBy('user_id')->sortBy(fn ($rows) => $rows->first()->nama_lengkap);

        return view('admin.user_login.index', [
            'judulHalaman' => 'User Login',
            'menuAktif'    => 'user_login',
            'perUser'      => $perUser,
            'totalPengguna' => $perUser->count(),
            'totalPerangkat' => $sesi->count(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $tokenId = (int) $request->input('token_id');
        $token = ApiToken::with('user')->find($tokenId);

        if (! $token) {
            return back()->with('error', 'Sesi tidak ditemukan.');
        }

        $nama = $token->user?->nama_lengkap ?? '#'.$token->user_id;
        $perangkat = $token->namaPerangkat();
        $token->delete();

        catat_aktivitas('Logout Perangkat Mobile', "$nama — $perangkat (IP {$token->ip}) diputus oleh admin");

        return back()->with('success', "Sesi $perangkat milik $nama berhasil diputus.");
    }

    public function logoutSemua(Request $request): RedirectResponse
    {
        $userId = (int) $request->input('user_id');
        $nama = User::where('id', $userId)->value('nama_lengkap') ?? '#'.$userId;
        $jumlah = ApiToken::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->delete();

        catat_aktivitas('Logout Semua Perangkat Mobile', "Semua sesi mobile milik $nama ($jumlah) diputus oleh admin");

        return back()->with('success', "Semua $jumlah sesi mobile milik $nama berhasil diputus.");
    }
}