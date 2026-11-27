<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\PengajuanLupaAbsen;
use App\Models\User;
use App\Services\LupaAbsenService;
use Illuminate\Support\Facades\Storage;

class FotoController extends Controller
{
    use HasPenggunaAktif;

    public function tampil(int $absensiId, string $tipe)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response('Tidak berwenang.', 401);
        }
        if (! in_array($tipe, ['datang', 'pulang'], true)) {
            return response('Tidak ditemukan.', 404);
        }

        $rec = Absensi::find($absensiId);
        if (! $rec || ($u['role'] !== 'admin' && (int) $rec->user_id !== (int) $u['id'])) {
            return response('Tidak ditemukan.', 404);
        }

        $relatif = $tipe === 'datang' ? $rec->foto_masuk : $rec->foto_pulang;
        return $this->kirimBerkas($relatif);
    }

    public function lampiranIzin(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response('Tidak berwenang.', 401);
        }

        $iz = Izin::find($id);
        if (! $iz || ($u['role'] !== 'admin' && (int) $iz->user_id !== (int) $u['id'])) {
            return response('Tidak ditemukan.', 404);
        }
        return $this->kirimBerkas($iz->lampiran);
    }

    public function lampiranLupaAbsen(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return response('Tidak berwenang.', 401);
        }

        $pj = PengajuanLupaAbsen::find($id);
        if (! $pj) {
            return response('Tidak ditemukan.', 404);
        }

        $boleh = $u['role'] === 'admin' || (int) $pj->user_id === (int) $u['id'];
        if (! $boleh) {
            $userModel = User::find((int) $u['id']);
            $boleh = $userModel && app(LupaAbsenService::class)->isAtasan($userModel, (int) $pj->user_id);
        }
        if (! $boleh) {
            return response('Tidak ditemukan.', 404);
        }

        return $this->kirimBerkas($pj->lampiran);
    }

    private function kirimBerkas(?string $relatif)
    {
        if (! $relatif || str_contains($relatif, '..') || ! preg_match('#^[a-z]+/\d{6}/[\w.\-]+$#', $relatif)) {
            return response('Berkas tidak ditemukan.', 404);
        }

        if (! Storage::disk('public')->exists($relatif)) {
            return response('Berkas tidak ditemukan.', 404);
        }

        return Storage::disk('public')->response($relatif, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
