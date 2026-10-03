<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\Request;

/**
 * Notifikasi lonceng navbar admin.
 *
 * Semua query dibatasi ke user_id dari sesi admin, jadi satu admin tidak pernah
 * bisa menandai(notifikasi milik orang lain) sebagai dibaca lewat URL.
 */
class NotifikasiController extends Controller
{
    /** Tandai satu notifikasi sudah dibaca. */
    public function tandaiDibaca(Request $request, int $id)
    {
        $notifikasi = Notifikasi::where('user_id', session('uid'))->find($id);
        $notifikasi?->forceFill(['is_read' => true])->save();

        if ($notifikasi && ! $notifikasi->wasChanged('is_read')) {
            catat_aktivitas('Notifikasi Dibaca', 'Notifikasi #'.$id.' ditandai sudah dibaca');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'sukses' => true,
                'belum_dibaca' => jumlah_notifikasi_belum_dibaca((int) session('uid')),
            ]);
        }

        // Tombol "Buka" mengirim tujuan halaman agar notifikasi langsung
        // ditandai dibaca lalu admin dibawa ke halaman yang dimaksud.
        if ($lanjut = $request->input('lanjut')) {
            return redirect()->to($this->tujuanAman($lanjut));
        }

        return back();
    }

    /**
     * Hanya izinkan tujuan berupa path internal yang sudah divirus.
     *
     * Nilai url notifikasi berasal dari kode, tapi tetap divalidasi supaya
     * parameter yang tidak terduga tidak bisa dipakai untuk open redirect.
     */
    private function tujuanAman(?string $url): string
    {
        $url = trim((string) $url);

        // Tolak URL absolut (memiliki skema), protocol-relative, dan path yang
        // mencoba keluar dari root.
        if ($url === ''
            || str_contains($url, '..')
            || str_starts_with($url, '//')
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)) {
            return url('admin');
        }

        return str_starts_with($url, '/') ? $url : '/'.$url;
    }

    /** Tandai semua notifikasi admin sebagai sudah dibaca. */
    public function tandaiSemuaDibaca(Request $request)
    {
        $jumlah = Notifikasi::where('user_id', session('uid'))
            ->belumDibaca()
            ->update(['is_read' => true, 'updated_at' => now()]);

        if ($jumlah > 0) {
            catat_aktivitas('Notifikasi Dibaca', $jumlah.' notifikasi ditandai sudah dibaca');
        }

        if ($request->expectsJson()) {
            return response()->json(['sukses' => true, 'belum_dibaca' => 0]);
        }

        return back()->with('success', $jumlah > 0
            ? $jumlah.' notifikasi ditandai sudah dibaca.'
            : 'Tidak ada notifikasi yang belum dibaca.');
    }
}
