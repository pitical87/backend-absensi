<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MappingSIMRSAccount;
use App\Models\User;
use App\Services\SimrsService;
use Illuminate\Http\Request;

class SimrsController extends Controller
{
    public function index(Request $request, SimrsService $simrs)
    {
        $q = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per = 25;
        $tab = (string) $request->get('tab', '');

        $b = User::with('mappingSimrs')->where('role', '!=', 'admin');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('nip', 'like', "%{$q}%");
            });
        }
        $total = $b->count();
        $pegawai = $b->orderBy('nama_lengkap')
            ->skip(($halaman - 1) * $per)
            ->take($per)
            ->get();

        return view('admin.simrs.index', [
            'judulHalaman' => 'Integrasi SIMRS',
            'menuAktif' => 'simrs',
            'tab' => $tab,
            // Cek koneksi
            'hasil' => $simrs->cekKoneksi(),
            'timeout' => (int) config('simrs.timeout', 5),
            // Mapping akun
            'pegawai' => $pegawai,
            'q' => $q,
            'halaman' => $halaman,
            'totalHal' => max(1, (int) ceil($total / $per)),
            'total' => $total,
            // Tindakan & lab
            'terMapping' => $this->pegawaiTerMapping(),
        ]);
    }

    public function ambilTindakan(Request $request, SimrsService $simrs)
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'pegawai' => ['nullable', 'array'],
            'pegawai.*' => ['integer'],
        ], [
            'dari.required' => 'Tanggal awal wajib diisi.',
            'dari.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'sampai.required' => 'Tanggal akhir wajib diisi.',
            'sampai.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $query = MappingSIMRSAccount::query();
        if (! empty($data['pegawai'])) {
            $query->whereIn('user_id', $data['pegawai']);
        }
        $ids = $query->pluck('simrs_user_id')->all();

        return response()->json(
            $simrs->cariTindakan($ids, $data['dari'], $data['sampai'])
        );
    }

    public function ambilLab(Request $request, SimrsService $simrs)
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'pegawai' => ['nullable', 'array'],
            'pegawai.*' => ['integer'],
        ], [
            'dari.required' => 'Tanggal awal wajib diisi.',
            'dari.date_format' => 'Format tanggal awal harus YYYY-MM-DD.',
            'sampai.required' => 'Tanggal akhir wajib diisi.',
            'sampai.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $query = MappingSIMRSAccount::query();
        if (! empty($data['pegawai'])) {
            $query->whereIn('user_id', $data['pegawai']);
        }
        $ids = $query->pluck('simrs_user_id')->all();

        return response()->json(
            $simrs->cariLab($ids, $data['dari'], $data['sampai'])
        );
    }

    private function pegawaiTerMapping(): array
    {
        return MappingSIMRSAccount::query()
            ->join('users', 'users.id', '=', 'mapping_simrs_accounts.user_id')
            ->orderBy('users.nama_lengkap')
            ->get([
                'mapping_simrs_accounts.user_id',
                'users.nama_lengkap',
                'mapping_simrs_accounts.simrs_user_id',
            ])
            ->map(fn ($p) => [
                'user_id' => (int) $p->user_id,
                'nama_lengkap' => (string) $p->nama_lengkap,
                'simrs_user_id' => (string) $p->simrs_user_id,
            ])
            ->all();
    }
}
