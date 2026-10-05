<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DataLogbookController extends Controller
{
    public function index(Request $request)
    {
        $bulan = (int) ($request->get('bulan') ?: now()->month);
        $tahun = (int) ($request->get('tahun') ?: now()->year);
        if ($bulan < 1 || $bulan > 12) $bulan = now()->month;
        if ($tahun < 2000 || $tahun > 2100) $tahun = now()->year;

        $q       = trim((string) $request->get('q'));
        $halaman = max(1, (int) $request->get('hal'));
        $per     = 20;

        // rekap per pegawai: jumlah hari kerja + jumlah entri + yang sudah diverifikasi
        $rekap = Logbook::query()
            ->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun)
            ->selectRaw('user_id,
                COUNT(DISTINCT tanggal) AS jumlah_hari,
                COUNT(*) AS jumlah_entri,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS jumlah_verifikasi')
            ->groupBy('user_id');

        $b = User::query()
            ->leftJoinSub($rekap, 'r', 'r.user_id', '=', 'users.id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->when($q !== '', fn ($w) => $w->where('users.nama_lengkap', 'like', "%{$q}%"))
            ->select(
                'users.id',
                'users.nama_lengkap',
                'users.nip',
                'users.status',
                DB::raw("COALESCE(uk.nama, '-') AS unit_nama"),
                DB::raw("COALESCE(su.nama, '') AS sub_nama"),
                DB::raw('COALESCE(r.jumlah_hari, 0) AS jumlah_hari'),
                DB::raw('COALESCE(r.jumlah_entri, 0) AS jumlah_entri'),
                DB::raw('COALESCE(r.jumlah_verifikasi, 0) AS jumlah_verifikasi'),
            );

        $total  = (clone $b)->count();
        $daftar = $b->orderBy('users.nama_lengkap')
            ->skip(($halaman - 1) * $per)
            ->take($per)
            ->get()
            ->all();

        return view('admin.logbook_data.index', [
            'judulHalaman' => 'Data Logbook',
            'menuAktif'    => 'logbook_data',
            'daftar'       => $daftar,
            'total'        => $total,
            'halaman'      => $halaman,
            'totalHal'     => max(1, (int) ceil($total / $per)),
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'q'            => $q,
        ]);
    }

    public function detail(Request $request)
    {
        $f = $request->validate([
            'user_id' => ['required', 'integer'],
            'bulan'   => ['required', 'integer', 'between:1,12'],
            'tahun'   => ['required', 'integer', 'between:2000,2100'],
        ]);

        $user = User::query()
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->where('users.id', (int) $f['user_id'])
            ->selectRaw("users.nama_lengkap,
                COALESCE(uk.nama, '-') AS unit_nama,
                COALESCE(su.nama, '') AS sub_nama")
            ->first();

        if (! $user) {
            return response()->json(['sukses' => false, 'pesan' => 'Pegawai tidak ditemukan.'], 404);
        }

        $entri = Logbook::with('verifikator')
            ->where('user_id', (int) $f['user_id'])
            ->whereMonth('tanggal', (int) $f['bulan'])
            ->whereYear('tanggal', (int) $f['tahun'])
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->get();

        // kelompokkan per tanggal
        $grup = [];
        foreach ($entri as $e) {
            $tgl = $e->tanggal->format('Y-m-d');
            $grup[$tgl][] = [
                'id'          => (int) $e->id,
                'jam'         => substr((string) $e->jam, 0, 5),
                'isi'         => (string) $e->isi,
                'verified'    => (bool) $e->is_verified,
                'verified_at' => $e->verified_at?->translatedFormat('d/m/Y H:i'),
                'verifikator' => (string) ($e->verifikator?->nama_lengkap ?? ''),
            ];
        }

        return response()->json([
            'sukses'      => true,
            'nama'        => $user->nama_lengkap,
            'unit'        => trim($user->unit_nama.($user->sub_nama ? ' — '.$user->sub_nama : '')),
            'total_hari'  => count($grup),
            'total_entri' => $entri->count(),
            'terverifikasi' => $entri->filter(fn ($e) => $e->is_verified)->count(),
            'data'        => $grup,
        ]);
    }

    /** Verifikasi / batal verifikasi sekumpulan entri. */
    public function verifikasi(Request $request)
    {
        $f = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'aksi'  => ['required', 'in:verifikasi,batal'],
        ], [
            'ids.required' => 'Pilih minimal satu entri logbook.',
            'aksi.required' => 'Pilih tindakan Verifikasi atau Batal Verifikasi.',
            'aksi.in' => 'Tindakan tidak valid.',
        ]);

        $verifikasi = $f['aksi'] === 'verifikasi';
        $jumlah = Logbook::query()
            ->whereIn('id', array_map('intval', $f['ids']))
            ->update([
                'is_verified' => $verifikasi,
                'verified_at' => $verifikasi ? now() : null,
                'verified_by' => $verifikasi ? (int) session('uid') : null,
            ]);

        catat_aktivitas(
            $verifikasi ? 'Verifikasi Logbook' : 'Batal Verifikasi Logbook',
            $jumlah.' entri logbook'.($verifikasi ? ' diverifikasi' : ' dibatalkan verifikasinya')
        );

        return response()->json([
            'sukses' => true,
            'pesan'  => $jumlah.' entri logbook'.($verifikasi ? ' berhasil diverifikasi.' : ' berhasil dibatalkan verifikasinya.'),
        ]);
    }

    public function ubah(Request $request)
    {
        $f = $request->validate([
            'id'      => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
            'jam'     => ['required', 'date_format:H:i'],
            'isi'     => ['required', 'string', 'max:1000'],
        ], [
            'id.required' => 'Entri logbook tidak ditemukan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'jam.date_format' => 'Format jam tidak valid.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $entri = Logbook::find((int) $f['id']);
        if (! $entri) {
            return response()->json(['sukses' => false, 'pesan' => 'Entri logbook tidak ditemukan.'], 404);
        }

        // Admin boleh mengubah entri terverifikasi, tetapi verifikasinya dilepas.
        $entri->update([
            'tanggal'     => $f['tanggal'],
            'jam'         => $f['jam'],
            'isi'         => trim($f['isi']),
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        catat_aktivitas('Logbook', 'Entri logbook '.$entri->id.' diubah oleh admin'
            .($entri->wasChanged('is_verified') && ! $entri->is_verified ? ' (verifikasi dilepas)' : ''));

        return response()->json([
            'sukses' => true,
            'pesan'  => 'Entri logbook diperbarui.',
        ]);
    }

    public function hapus(Request $request)
    {
        $f = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], [
            'ids.required' => 'Pilih minimal satu entri logbook.',
        ]);

        $jumlah = Logbook::query()
            ->whereIn('id', array_map('intval', $f['ids']))
            ->delete();

        if (! $jumlah) {
            return response()->json(['sukses' => false, 'pesan' => 'Entri logbook tidak ditemukan.'], 404);
        }

        catat_aktivitas('Logbook', $jumlah.' entri logbook dihapus oleh admin');

        return response()->json([
            'sukses' => true,
            'pesan'  => $jumlah.' entri logbook dihapus.',
        ]);
    }
}