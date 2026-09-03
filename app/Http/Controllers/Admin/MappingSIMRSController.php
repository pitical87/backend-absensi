<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MappingSIMRSAccount;
use App\Models\User;
use App\Services\SimrsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MappingSIMRSController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per = 25;

        $b = User::with('mappingSimrs')
            ->where('role', '!=', 'admin');
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

        return view('admin.mapping_simrs.index', [
            'judulHalaman' => 'Mapping Akun SIMRS',
            'menuAktif' => 'mapping_simrs',
            'pegawai' => $pegawai,
            'q' => $q,
            'halaman' => $halaman,
            'totalHal' => max(1, (int) ceil($total / $per)),
            'total' => $total,
        ]);
    }

    public function cari(Request $request, SimrsService $simrs)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'hal' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $simrs->cariPegawai(
                (string) ($data['q'] ?? ''),
                (int) ($data['hal'] ?? 1)
            )
        );
    }

    public function cek(Request $request, SimrsService $simrs)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $mapping = MappingSIMRSAccount::where('user_id', (int) $data['user_id'])->first();
        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Pegawai ini belum memiliki mapping ID SIMRS.',
            ]);
        }

        return response()->json(
            $simrs->cekMapping($mapping->simrs_user_id)
        );
    }

    public function simpan(Request $request)
    {
        $mappingLama = MappingSIMRSAccount::where(
            'user_id',
            (int) $request->input('user_id')
        )->first();

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'simrs_user_id' => [
                'required', 'string', 'max:100',
                Rule::unique('mapping_simrs_accounts', 'simrs_user_id')
                    ->ignore($mappingLama?->id),
            ],
        ], [
            'simrs_user_id.required' => 'ID SIMRS wajib diisi.',
            'simrs_user_id.unique' => 'ID SIMRS tersebut sudah dipakai pengguna lain.',
        ]);

        $pegawai = User::findOrFail($data['user_id']);

        MappingSIMRSAccount::updateOrCreate(
            ['user_id' => $pegawai->id],
            ['simrs_user_id' => trim($data['simrs_user_id'])]
        );

        catat_aktivitas('Mapping SIMRS', $pegawai->nama_lengkap.' → '.$data['simrs_user_id']);

        return redirect()->route('admin.simrs', ['tab' => 'mapping'])
            ->with('success', 'Mapping akun SIMRS untuk '.$pegawai->nama_lengkap.' disimpan.');
    }
}
