<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AncamanLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginGagalController extends Controller
{
    public function __construct(private readonly AncamanLoginService $ancaman) {}

    public function index(Request $request): View
    {
        $filter = $this->filter($request);

        return view('admin.login_gagal.index', [
            'judulHalaman' => 'Login Gagal',
            'menuAktif' => 'login_gagal',
            'rows' => $this->ancaman->daftar($filter),
            'ringkasan' => $this->ancaman->ringkasan((int) $filter['jam']),
            'filter' => $filter,
            'opsiJendela' => AncamanLoginService::OPSI_JENDELA,
            'opsiSumber' => $this->opsiSumber(),
        ]);
    }

    /**
     * Endpoint asinkron: pencarian, filter, dan pagination tanpa refresh
     * halaman, mengikuti pola UserLoginController::data().
     */
    public function data(Request $request): JsonResponse
    {
        $filter = $this->filter($request);
        $rows = $this->ancaman->daftar($filter);

        return response()->json([
            'sukses' => true,
            'total' => $rows->total(),
            'dari' => $rows->firstItem(),
            'sampai' => $rows->lastItem(),
            'halaman' => $rows->currentPage(),
            'totalHal' => $rows->lastPage(),
            'ringkasan' => $this->ancaman->ringkasan((int) $filter['jam']),
            'tbody' => view('admin.login_gagal.rows', ['rows' => $rows])->render(),
            'paginasi' => view('admin.login_gagal.paginasi', [
                'rows' => $rows,
            ])->render(),
        ]);
    }

    /** Blokir / buka blokir satu akun dan cabut sesinya. */
    public function alihkanStatus(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');

        $hasil = $this->ancaman->alihkanBlokir($id, (int) session('uid'));

        if (! $hasil['sukses']) {
            return response()->json(['sukses' => false, 'pesan' => $hasil['pesan']], 422);
        }

        catat_aktivitas(
            'Blokir Akun Login',
            ($hasil['status'] === 'nonaktif' ? 'Memblokir' : 'Membuka blokir').' akun #'.$id
        );

        return response()->json($hasil);
    }

    /**
     * @return array<string, mixed>
     */
    private function filter(Request $request): array
    {
        $jam = (int) $request->get('jam', AncamanLoginService::JENDELA_JAM);
        if (! isset(AncamanLoginService::OPSI_JENDELA[$jam])) {
            $jam = AncamanLoginService::JENDELA_JAM;
        }

        return [
            'q' => trim((string) $request->get('q')),
            'level' => trim((string) $request->get('level')),
            'sumber' => trim((string) $request->get('sumber')),
            'jam' => $jam,
            'halaman' => max(1, (int) $request->get('hal')),
        ];
    }

    /** @return array<int, string> */
    private function opsiSumber(): array
    {
        return ['web' => 'Web', 'api' => 'API / Mobile', 'google' => 'Google'];
    }
}
