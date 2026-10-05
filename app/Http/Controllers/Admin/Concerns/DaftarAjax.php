<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helper untuk daftar admin yang dimuat asinkron: pencarian, kategori/status,
 * pagination, dan aksi yang balas JSON berisi tabel yang sudah disegarkan.
 */
trait DaftarAjax
{
    /** Jumlah baris per halaman untuk daftar asinkron. */
    protected function perHalamanAjax(): int
    {
        return 15;
    }

    /**
     * Baca parameter ?status=, ?q=, ?hal= dari request dan bersihkan nilainya.
     *
     * @param  list<string>  $statusValid
     * @return array{status: string, q: string, hal: int, perHalaman: int}
     */
    protected function filterAjax(Request $request, array $statusValid, string $statusAwal): array
    {
        $status = (string) $request->get('status', $statusAwal);
        if (! in_array($status, $statusValid, true)) {
            $status = $statusAwal;
        }

        return [
            'status' => $status,
            'q' => mb_substr(trim((string) $request->get('q')), 0, 60),
            'hal' => max(1, (int) $request->get('hal', 1)),
            'perHalaman' => $this->perHalamanAjax(),
        ];
    }

    /**
     * Batasi query ke satu halaman; teks pencarian sudah dibersihkan di filterAjax().
     */
    protected function paginateAjax($query, array $filter)
    {
        return $query->paginate($filter['perHalaman'], ['*'], 'hal', $filter['hal'])
            ->withQueryString();
    }

    /**
     * Balasan JSON untuk tabel asinkron dan aksi (berisi tabel yang sudah disegarkan).
     *
     * @param  array<string, mixed>  $tambahan
     */
    protected function balasAjax(string $html, array $tambahan = [], bool $sukses = true, int $kode = 200): JsonResponse
    {
        return response()->json(array_merge([
            'sukses' => $sukses,
            'html' => $html,
        ], $tambahan), $sukses ? $kode : 422);
    }
}
