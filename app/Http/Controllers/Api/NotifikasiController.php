<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint notifikasi per pengguna untuk aplikasi mobile.
 *
 * Semua query wajib dibatasi ke $req->get('user') — inilah satu-satunya batas
 * aksesnya, jadi jangan pernah menerima user_id dari parameter permintaan.
 */
class NotifikasiController extends Controller
{
    /** Daftar notifikasi milik pengguna yang sedang login. */
    public function daftar(Request $req): JsonResponse
    {
        $user = $req->get('user');
        $kategori = trim((string) ($req->query('kategori') ?? ''));

        if ($kategori !== '' && ! array_key_exists($kategori, Notifikasi::KATEGORI)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Kategori notifikasi tidak dikenal.',
                'kategori_tersedia' => array_keys(Notifikasi::KATEGORI),
            ], 422);
        }

        $perPage = min(50, max(5, (int) ($req->query('per_page') ?: 15)));

        $daftar = Notifikasi::where('user_id', $user->id)
            ->kategori($kategori === '' ? null : $kategori)
            ->when($req->boolean('belum_dibaca'), fn ($q) => $q->belumDibaca())
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'sukses' => true,
            'notifikasi' => collect($daftar->items())->map(fn (Notifikasi $n) => $this->ringkas($n)),
            'total' => $daftar->total(),
            'halaman' => $daftar->currentPage(),
            'halaman_total' => $daftar->lastPage(),
            'per_halaman' => $daftar->perPage(),
            'belum_dibaca' => $this->belumDibaca($req),
        ]);
    }

    /** Jumlah notifikasi belum dibaca — dipanggil ringan untuk badge lonceng. */
    public function total(Request $req): JsonResponse
    {
        $user = $req->get('user');

        return response()->json([
            'sukses' => true,
            'belum_dibaca' => $this->belumDibaca($req),
        ]);
    }

    /** Tandai satu notifikasi sudah dibaca. Notifikasi milik orang lain diabaikan. */
    public function tandaiDibaca(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $notifikasi = Notifikasi::where('user_id', $user->id)->find($id);
        if (! $notifikasi) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $notifikasi->forceFill(['is_read' => true])->save();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Notifikasi ditandai sudah dibaca.',
            'belum_dibaca' => $this->belumDibaca($req),
        ]);
    }

    /** Tandai semua notifikasi milik pengguna sebagai sudah dibaca. */
    public function tandaiSemuaDibaca(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $jumlah = Notifikasi::where('user_id', $user->id)
            ->belumDibaca()
            ->update(['is_read' => true, 'updated_at' => now()]);

        return response()->json([
            'sukses' => true,
            'pesan' => $jumlah > 0
                ? $jumlah.' notifikasi ditandai sudah dibaca.'
                : 'Tidak ada notifikasi yang belum dibaca.',
            'ditandai' => $jumlah,
            'belum_dibaca' => 0,
        ]);
    }

    /** Hapus satu notifikasi milik pengguna. */
    public function hapus(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $notifikasi = Notifikasi::where('user_id', $user->id)->find($id);
        if (! $notifikasi) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Notifikasi tidak ditemukan.',
            ], 404);
        }

        $notifikasi->delete();

        return response()->json([
            'sukses' => true,
            'pesan' => 'Notifikasi dihapus.',
            'belum_dibaca' => $this->belumDibaca($req),
        ]);
    }

    private function belumDibaca(Request $req): int
    {
        return (int) Notifikasi::where('user_id', $req->get('user')->id)
            ->belumDibaca()
            ->count();
    }

    /** @return array<string, mixed> */
    private function ringkas(Notifikasi $notifikasi): array
    {
        return [
            'id' => (int) $notifikasi->id,
            'isi' => $notifikasi->isi,
            'tipe' => $notifikasi->tipe,
            'kategori' => $notifikasi->kategori,
            'kategori_label' => $notifikasi->labelKategori(),
            'url' => $notifikasi->url,
            'is_read' => (bool) $notifikasi->is_read,
            'dibuat_pada' => $notifikasi->created_at?->toIso8601String(),
        ];
    }
}
