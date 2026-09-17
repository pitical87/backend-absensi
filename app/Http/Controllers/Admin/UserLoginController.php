<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserLoginController extends Controller
{
    public function index(Request $request)
    {
        [$totalPengguna, $totalPerangkat] = $this->ringkasan();

        return view('admin.user_login.index', [
            'judulHalaman'   => 'User Login',
            'menuAktif'      => 'user_login',
            'totalPengguna'  => $totalPengguna,
            'totalPerangkat' => $totalPerangkat,
            'rows'           => $this->kueriPengguna($request)->paginate(15),
            'q'              => trim((string) $request->get('q')),
        ]);
    }

    /**
     * Endpoint asinkron: daftar pengguna ber-sesi aktif + jumlah perangkat
     * & aktivitas terakhir, dengan pencarian & pagination tanpa refresh halaman.
     */
    public function data(Request $request): JsonResponse
    {
        [$totalPengguna, $totalPerangkat] = $this->ringkasan();
        $rows = $this->kueriPengguna($request)->paginate(15);

        return response()->json([
            'sukses'         => true,
            'total'          => $rows->total(),
            'dari'           => $rows->firstItem(),
            'sampai'         => $rows->lastItem(),
            'halaman'        => $rows->currentPage(),
            'totalHal'       => $rows->lastPage(),
            'totalPengguna'  => $totalPengguna,
            'totalPerangkat' => $totalPerangkat,
            'tbody'          => view('admin.user_login.rows', ['rows' => $rows])->render(),
            'paginasi'       => view('admin.user_login.paginasi', ['rows' => $rows])->render(),
        ]);
    }

    /**
     * Endpoint asinkron: daftar semua perangkat/sesi aktif milik satu pengguna.
     */
    public function detail(Request $request): JsonResponse
    {
        $userId = (int) $request->get('user_id');
        $user = User::where('id', $userId)->first();

        if (! $user) {
            return response()->json(['sukses' => false, 'pesan' => 'Pegawai tidak ditemukan.'], 404);
        }

        $sesi = ApiToken::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'sukses'   => true,
            'nama'     => $user->nama_lengkap,
            'unit'     => $user->unitKerja?->nama,
            'sub_unit' => $user->subUnit?->nama,
            'jumlah'   => $sesi->count(),
            'tbody'    => view('admin.user_login.detail_rows', ['sesi' => $sesi])->render(),
        ]);
    }

    public function logout(Request $request)
    {
        $tokenId = (int) $request->input('token_id');
        $token = ApiToken::with('user')->find($tokenId);

        if (! $token) {
            return $this->balasLogout($request, false, 'Sesi tidak ditemukan.');
        }

        $nama = $token->user?->nama_lengkap ?? '#'.$token->user_id;
        $perangkat = $token->namaPerangkat();
        $ip = (string) $token->ip;
        $token->delete();

        catat_aktivitas('Logout Perangkat Mobile', "$nama — $perangkat (IP $ip) diputus oleh admin");

        return $this->balasLogout($request, true, "Sesi $perangkat milik $nama berhasil diputus.");
    }

    public function logoutSemua(Request $request)
    {
        $userId = (int) $request->input('user_id');
        $nama = User::where('id', $userId)->value('nama_lengkap') ?? '#'.$userId;
        $jumlah = ApiToken::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->delete();

        catat_aktivitas('Logout Semua Perangkat Mobile', "Semua sesi mobile milik $nama ($jumlah) diputus oleh admin");

        return $this->balasLogout($request, true, "Semua $jumlah sesi mobile milik $nama berhasil diputus.");
    }

    private function balasLogout(Request $request, bool $sukses, string $pesan): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['sukses' => $sukses, 'pesan' => $pesan], $sukses ? 200 : 404);
        }

        return back()->with($sukses ? 'success' : 'error', $pesan);
    }

    private function kueriPengguna(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $b = ApiToken::query()
            ->join('users as u', 'u.id', '=', 'api_tokens.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->where('u.status', 'aktif')
            ->where('api_tokens.expires_at', '>', now())
            ->selectRaw('u.id as user_id, u.nama_lengkap, u.email, uk.nama as unit_nama, su.nama as sub_unit_nama, COUNT(api_tokens.id) as jumlah_perangkat, MAX(api_tokens.last_aktivitas) as terakhir_aktif')
            ->groupBy('u.id', 'u.nama_lengkap', 'u.email', 'uk.nama', 'su.nama')
            ->orderBy('u.nama_lengkap');

        if ($q !== '') {
            $b->where(function ($w) use ($q) {
                $w->where('u.nama_lengkap', 'like', "%{$q}%")
                  ->orWhere('u.email', 'like', "%{$q}%")
                  ->orWhere('uk.nama', 'like', "%{$q}%")
                  ->orWhere('su.nama', 'like', "%{$q}%");
            });
        }

        return $b;
    }

    private function ringkasan(): array
    {
        $aktif = fn ($query) => $query
            ->join('users as u', 'u.id', '=', 'api_tokens.user_id')
            ->where('u.status', 'aktif')
            ->where('api_tokens.expires_at', '>', now());

        $totalPerangkat = $aktif(ApiToken::query())->count();
        $totalPengguna = $aktif(ApiToken::query())->distinct()->count('api_tokens.user_id');

        return [(int) $totalPengguna, (int) $totalPerangkat];
    }
}