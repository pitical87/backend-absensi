<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtasanLangsung;
use App\Models\User;
use App\Services\AtasanLangsungService;
use Illuminate\Http\Request;

class AtasanLangsungController extends Controller
{
    public function index(AtasanLangsungService $servis)
    {
        $pegawai = User::select(
            'users.id', 'users.nama_lengkap', 'users.email',
            'uk.nama AS unit_nama', 'su.nama AS sub_nama'
        )
            ->where('role', '!=', 'admin')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->orderBy('users.nama_lengkap')
            ->get();

        $relasi = AtasanLangsung::select('user_id', 'atasan_id')->get()->groupBy('user_id');

        return view('admin.atasan_langsung', [
            'judulHalaman' => 'Atasan Langsung',
            'menuAktif'    => 'atasan_langsung',
            'pegawai'      => $pegawai,
            'relasi'       => $relasi,
            'pilihan'      => $servis->pilihan(),
        ]);
    }

    public function aksi(Request $request, AtasanLangsungService $servis)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'atasan'  => ['nullable', 'array'],
        ], [
            'user_id.required' => 'Pegawai wajib dipilih.',
        ]);

        $pegawai = User::where('id', (int) $data['user_id'])->where('role', '!=', 'admin')->first();
        if (! $pegawai) {
            return redirect('admin/atasan_langsung')->with('error', 'Pegawai tidak ditemukan.');
        }

        $jumlah = $servis->sinkron((int) $data['user_id'], $data['atasan'] ?? []);

        catat_aktivitas('Atasan Langsung', $pegawai->nama_lengkap.' → '.$jumlah.' atasan');

        return redirect('admin/atasan_langsung')
            ->with('success', 'Atasan langsung '.$pegawai->nama_lengkap.' diperbarui ('.$jumlah.').');
    }
}
