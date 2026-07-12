<?php

namespace App\Http\Middleware;

use App\Models\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $kunci = (string) Pengaturan::ambil('api_key', '');
        $kirim = (string) $request->header('X-API-KEY', '');

        if ($kunci === '' || $kirim === '' || ! hash_equals($kunci, $kirim)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Kunci API tidak valid. Sertakan header X-API-KEY yang benar.',
            ], 401);
        }

        return $next($request);
    }
}
