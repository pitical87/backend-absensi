<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfilService;
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
}
