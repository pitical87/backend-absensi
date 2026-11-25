<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MappingSIMRSAccount;
use App\Services\ProfilService;
use App\Services\SimrsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function update(Request $req, ProfilService $profil): JsonResponse
    {
        $user = $req->get('user');
        $hasil = $profil->perbarui($user, $req->all());

        if (! $hasil['sukses']) {
            return response()->json([
                'sukses' => false,
                'pesan'  => $hasil['pesan'],
                'errors' => $hasil['galat'],
            ], 422);
        }

        $user->load(['unitKerja', 'subUnit', 'profesi', 'jabatan']);
        $user->append('shift');

        return response()->json([
            'sukses' => true,
            'pesan'  => $hasil['pesan'],
            'user'   => $user,
        ]);
    }

    public function mappingSimrs(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $mapping = MappingSIMRSAccount::where('user_id', (int) $user->id)->first();

        return response()->json([
            'sukses' => true,
            'termapping' => $mapping !== null,
            'simrs_user_id' => $mapping?->simrs_user_id,
        ]);
    }

    public function tesMappingSimrs(Request $req, SimrsService $simrs): JsonResponse
    {
        $user = $req->get('user');
        $mapping = MappingSIMRSAccount::where('user_id', (int) $user->id)->first();

        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akun Anda belum memiliki mapping ID SIMRS.',
            ]);
        }

        return response()->json($simrs->cekMapping($mapping->simrs_user_id));
    }

    public function cekSimrsId(Request $req, SimrsService $simrs): JsonResponse
    {
        $user = $req->get('user');
        $kode = trim((string) $req->query('id'));

        if ($kode === '') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Masukkan ID SIMRS terlebih dahulu.',
            ]);
        }

        $dipakai = MappingSIMRSAccount::where('simrs_user_id', $kode)
            ->where('user_id', '!=', (int) $user->id)
            ->exists();

        if ($dipakai) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'ID SIMRS tersebut sudah dipakai pengguna lain.',
            ]);
        }

        return response()->json($simrs->cekMapping($kode));
    }

    public function simpanMappingSimrs(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $kode = trim((string) $req->input('simrs_user_id'));

        if ($kode === '') {
            return response()->json([
                'sukses' => false,
                'pesan' => 'ID SIMRS wajib diisi.',
            ], 422);
        }

        if (mb_strlen($kode) > 100) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'ID SIMRS maksimal 100 karakter.',
            ], 422);
        }

        $dipakai = MappingSIMRSAccount::where('simrs_user_id', $kode)
            ->where('user_id', '!=', (int) $user->id)
            ->exists();

        if ($dipakai) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'ID SIMRS tersebut sudah dipakai pengguna lain.',
            ], 422);
        }

        MappingSIMRSAccount::updateOrCreate(
            ['user_id' => (int) $user->id],
            ['simrs_user_id' => $kode]
        );

        catat_aktivitas('Mapping SIMRS Mobile', $user->nama_lengkap.' → '.$kode, (int) $user->id);

        return response()->json([
            'sukses' => true,
            'pesan' => 'Mapping akun SIMRS berhasil disimpan.',
            'simrs_user_id' => $kode,
        ]);
    }
}
