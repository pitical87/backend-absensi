<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Shift;
use App\Services\RekapService;

class DashboardController extends Controller
{
    use HasPenggunaAktif;

    public function index()
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return redirect('login');
        }
        if ($u['role'] === 'admin') {
            return redirect('admin');
        }

        $hariIni = now()->format('Y-m-d');

        $recBuka = Absensi::with('shift:id,kategori,jam_masuk,jam_pulang')
            ->where('user_id', $u['id'])
            ->whereNull('waktu_pulang')
            ->orderBy('waktu_masuk', 'DESC')
            ->first();

        $recHariIni = Absensi::with('shift:id,kategori,jam_masuk,jam_pulang')
            ->where('user_id', $u['id'])
            ->where('tanggal', $hariIni)
            ->first();

        $shiftGrup = [];
        foreach (Shift::where('aktif', 1)->orderBy('jam_masuk')->get() as $s) {
            $shiftGrup[$s->kategori][] = $s;
        }

        $bolehDatang = ! $recBuka && ! $recHariIni;

        $izinHariIni = Izin::where('user_id', $u['id'])
            ->where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $hariIni)
            ->where('tanggal_selesai', '>=', $hariIni)
            ->first();

        $rekap = app(RekapService::class)->hitung((int) $u['id'], (int) now()->format('n'), (int) now()->format('Y'));

        return view('pegawai.dashboard', [
            'u'               => $u,
            'recBuka'         => $recBuka,
            'recHariIni'      => $recHariIni,
            'recTampil'       => $recBuka ?: $recHariIni,
            'bolehDatang'     => $bolehDatang,
            'bolehPulang'     => (bool) $recBuka,
            'selesai'         => $recHariIni && $recHariIni->waktu_pulang,
            'shiftGrup'       => $shiftGrup,
            'bolehPilihShift' => pengaturan('izinkan_pilih_shift', '1') === '1' && $bolehDatang,
            'wajibSelfie'     => pengaturan('wajib_selfie', '1') === '1',
            'izinHariIni'     => $izinHariIni,
            'rekap'           => $rekap,
        ]);
    }
}
