<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\LogLokasi;
use App\Models\UnitKerja;
use Illuminate\Http\Request;

class KehadiranController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = (string) $request->get('tanggal');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = now()->format('Y-m-d');
        }
        $fUnit       = (int) $request->get('unit');
        $hanyaAnomali = $request->get('anomali') === '1';

        $b = Absensi::selectRaw("absensi.*, u.nama_lengkap, uk.nama AS unit_nama, su.nama AS sub_nama,
                         s.kategori AS shift_kategori, s.jam_masuk AS shift_masuk, s.jam_pulang AS shift_pulang,
                         (SELECT jarak_meter FROM log_lokasi l
                           WHERE l.absensi_id = absensi.id AND l.tipe = 'datang'
                        ORDER BY l.id DESC LIMIT 1) AS jarak_datang")
            ->join('users as u', 'u.id', '=', 'absensi.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('shift as s', 's.id', '=', 'absensi.shift_id')
            ->where('absensi.tanggal', $tanggal);
        if ($fUnit)        $b->where('u.unit_kerja_id', $fUnit);
        if ($hanyaAnomali) $b->where('absensi.flag_anomali', 1);
        $rows = $b->orderBy('absensi.waktu_masuk')->get()->all();

        $ditolak = LogLokasi::select('log_lokasi.*', 'u.nama_lengkap')
            ->join('users as u', 'u.id', '=', 'log_lokasi.user_id')
            ->where('log_lokasi.ditolak', 1)
            ->whereDate('log_lokasi.waktu', $tanggal)
            ->orderBy('log_lokasi.waktu')
            ->get()
            ->all();

        $titik = [];
        foreach ($rows as $r) {
            if ($r->lat_masuk !== null) {
                $titik[] = ['nama' => $r->nama_lengkap, 'tipe' => 'Datang',
                    'lat' => (float) $r->lat_masuk, 'lng' => (float) $r->lng_masuk,
                    'jam' => jam_id($r->waktu_masuk), 'anomali' => (bool) $r->flag_anomali];
            }
            if ($r->lat_pulang !== null) {
                $titik[] = ['nama' => $r->nama_lengkap, 'tipe' => 'Pulang',
                    'lat' => (float) $r->lat_pulang, 'lng' => (float) $r->lng_pulang,
                    'jam' => jam_id($r->waktu_pulang), 'anomali' => (bool) $r->flag_anomali];
            }
        }

        return view('admin.kehadiran', [
            'judulHalaman' => 'Data Kehadiran',
            'menuAktif'    => 'kehadiran',
            'tanggal'      => $tanggal,
            'fUnit'        => $fUnit,
            'hanyaAnomali' => $hanyaAnomali,
            'rows'         => $rows,
            'ditolak'      => $ditolak,
            'titik'        => $titik,
            'unitList'     => UnitKerja::orderBy('id')->get()->all(),
            'rsLat'        => (float) pengaturan('lokasi_lat', 0),
            'rsLng'        => (float) pengaturan('lokasi_lng', 0),
            'radius'       => (float) pengaturan('radius_meter', 100),
        ]);
    }
}
