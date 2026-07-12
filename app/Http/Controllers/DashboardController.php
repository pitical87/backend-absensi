<?php

namespace App\Http\Controllers;

use App\Services\RekapService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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

        $recBuka = DB::table('absensi as a')
            ->select('a.*', 's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang')
            ->join('shift as s', 's.id', '=', 'a.shift_id', 'left')
            ->where('a.user_id', $u['id'])->whereNull('a.waktu_pulang')
            ->orderBy('a.waktu_masuk', 'DESC')->first();

        $recHariIni = DB::table('absensi as a')
            ->select('a.*', 's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang')
            ->join('shift as s', 's.id', '=', 'a.shift_id', 'left')
            ->where('a.user_id', $u['id'])->where('a.tanggal', $hariIni)
            ->first();

        $shiftGrup = [];
        foreach (DB::table('shift')->where('aktif', 1)
                     ->orderBy('jam_masuk')->get() as $s) {
            $shiftGrup[$s->kategori][] = $s;
        }

        $bolehDatang = ! $recBuka && ! $recHariIni;

        $izinHariIni = DB::table('pengajuan_izin')
            ->where('user_id', $u['id'])->where('status', 'Disetujui')
            ->where('tanggal_mulai', '<=', $hariIni)->where('tanggal_selesai', '>=', $hariIni)
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

    private function penggunaAktif(): ?array
    {
        static $cache = false;
        if ($cache !== false) {
            return $cache;
        }
        $uid = (int) (session('uid') ?? 0);
        if (! $uid) {
            return $cache = null;
        }
        $u = DB::table('users as u')
            ->select('u.*', 'uk.nama AS unit_nama', 'su.nama AS sub_unit_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_jam_masuk',
                     's.jam_pulang AS shift_jam_pulang',
                     'j.nama AS jabatan_nama',
                     DB::raw('COALESCE(j.unit_label, ji.unit_label) AS jabatan_unit'),
                     'sp.nama AS seksi_pembina_nama', 'spi.unit_label AS bidang_pembina_label')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'u.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'u.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'u.jabatan_id')
            ->leftJoin('jabatan as ji', 'ji.id', '=', 'j.induk_id')
            ->leftJoin('jabatan as sp', 'sp.id', '=', 'u.seksi_pembina_id')
            ->leftJoin('jabatan as spi', 'spi.id', '=', 'sp.induk_id')
            ->where('u.id', $uid)
            ->first();

        if (! $u || $u->status !== 'aktif') {
            session()->flush();
            return $cache = null;
        }
        return $cache = (array) $u;
    }
}
