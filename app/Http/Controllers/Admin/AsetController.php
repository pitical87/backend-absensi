<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menyajikan file di dalam public/assets lewat Laravel dengan header cache
 * jangka panjang + ETag. Dipakai bila file statis tidak (seluruhnya) dilayani
 * langsung oleh web server, agar aset admin tetap ter-cache di browser.
 */
class AsetController extends Controller
{
    /** Satu tahun (detik). */
    private const MAKS_UMUR = 31536000;

    public function tampil(Request $request, string $berkas): BinaryFileResponse
    {
        $dasar = realpath(public_path('assets'));
        $diminta = ltrim(str_replace('\\', '/', $berkas), '/');

        if ($dasar === false || $diminta === '' || str_contains($diminta, "\0")) {
            abort(404);
        }

        $jalur = realpath($dasar.DIRECTORY_SEPARATOR.$diminta);

        // Cegah path traversal: hasil harus file nyata di dalam folder assets.
        if ($jalur === false
            || ! is_file($jalur)
            || ! str_starts_with($jalur, $dasar.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $etag = '"'.md5($jalur.'|'.filemtime($jalur).'|'.filesize($jalur)).'"';

        $respons = response()->file($jalur, [
            'Cache-Control' => 'private, max-age='.self::MAKS_UMUR.', immutable',
            'ETag' => $etag,
        ]);

        // Jawab 304 bila browser sudah punya versi terbaru.
        if ($respons->isNotModified($request)) {
            $respons->setNotModified();
        }

        return $respons;
    }
}
