<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VerifikasiEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifikasiEmailController extends Controller
{
    public function status(Request $req, VerifikasiEmailService $servis): JsonResponse
    {
        return response()->json([
            'sukses' => true,
        ] + $servis->status($req->get('user')));
    }

    public function kirim(Request $req, VerifikasiEmailService $servis): JsonResponse
    {
        $hasil = $servis->kirim($req->get('user'), (string) $req->input('email'));

        $isi = [
            'sukses'           => $hasil['sukses'],
            'pesan'            => $hasil['pesan'],
            'terverifikasi'    => $hasil['terverifikasi'],
            'sisa_detik'       => $hasil['sisa_detik'],
            'kirim_ulang_pada' => $hasil['kirim_ulang_pada'],
        ];

        if ($hasil['galat']) {
            $isi['errors'] = $hasil['galat'];

            return response()->json($isi, 422);
        }

        if ($hasil['cooldown']) {
            return response()->json($isi, 429);
        }

        if ($hasil['mail_gagal']) {
            return response()->json($isi, 500);
        }

        $isi['menunggu'] = ! $hasil['terverifikasi'];

        return response()->json($isi);
    }
}
