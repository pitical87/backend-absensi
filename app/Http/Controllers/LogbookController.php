<?php

namespace App\Http\Controllers;

use App\Models\Logbook;
use App\Models\MappingSIMRSAccount;
use App\Models\TemplateLogbook;
use App\Models\User;
use App\Services\SimrsService;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    public function index()
    {
        return view('pegawai.logbook', [
            'judulHalaman' => 'Logbook',
            'templates' => $this->daftarTemplate(),
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }

    public function simpan(Request $request)
    {
        $uid = (int) session('uid');
        if (! $uid) {
            return response()->json(['sukses' => false, 'pesan' => 'Sesi berakhir, silakan login ulang.'], 401);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'array', 'min:1'],
            'tanggal.*' => ['required', 'date'],
            'jam' => ['required', 'array'],
            'jam.*' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'array'],
            'isi.*' => ['required', 'string', 'max:1000'],
        ], [
            'tanggal.required' => 'Minimal satu baris logbook wajib diisi.',
            'tanggal.*.required' => 'Tanggal wajib diisi.',
            'jam.*.required' => 'Jam wajib diisi.',
            'jam.*.date_format' => 'Format jam tidak valid.',
            'isi.*.required' => 'Isi aktivitas wajib diisi.',
            'isi.*.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $sekarang = now();
        $baris = [];
        foreach ($data['tanggal'] as $i => $tgl) {
            $baris[] = [
                'user_id' => $uid,
                'tanggal' => $tgl,
                'jam' => $data['jam'][$i],
                'isi' => trim($data['isi'][$i]),
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        Logbook::insert($baris);
        catat_aktivitas('Logbook', count($baris).' entri logbook disimpan');

        return response()->json([
            'sukses' => true,
            'pesan' => count($baris).' entri logbook tersimpan.',
            'total' => count($baris),
        ]);
    }

    public function data(Request $request)
    {
        $uid = (int) session('uid');

        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'hal' => ['nullable', 'integer', 'min:1'],
        ]);

        $per = 20;
        $hal = max(1, (int) ($f['hal'] ?? 1));

        $query = Logbook::query()->where('user_id', $uid);

        if (! empty($f['q'])) {
            $query->where('isi', 'like', '%'.trim($f['q']).'%');
        }
        if (! empty($f['bulan'])) {
            $query->whereMonth('tanggal', (int) $f['bulan']);
        }
        if (! empty($f['tahun'])) {
            $query->whereYear('tanggal', (int) $f['tahun']);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('tanggal')
            ->orderByDesc('jam')
            ->skip(($hal - 1) * $per)
            ->take($per)
            ->get();

        return response()->json([
            'sukses' => true,
            'total' => $total,
            'halaman' => $hal,
            'per' => $per,
            'totalHal' => max(1, (int) ceil($total / $per)),
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'jam' => substr((string) $r->jam, 0, 5),
                'isi' => (string) $r->isi,
                'is_verified' => $r->is_verified,
                'verified_at' => $r->verified_at?->translatedFormat('d/m/Y H:i'),
            ])->all(),
        ]);
    }

    public function cetakData(Request $request)
    {
        $uid = (int) session('uid');

        $f = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
        ], [
            'bulan.required' => 'Bulan wajib dipilih untuk cetak.',
            'tahun.required' => 'Tahun wajib dipilih untuk cetak.',
        ]);

        $profil = User::query()
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->where('users.id', $uid)
            ->selectRaw("users.nama_lengkap,
                COALESCE(uk.nama, '-') AS unit_nama,
                COALESCE(su.nama, '') AS sub_nama")
            ->first();

        if (! $profil) {
            return response()->json(['sukses' => false, 'pesan' => 'Data pengguna tidak ditemukan.'], 404);
        }

        $entri = Logbook::query()
            ->where('user_id', $uid)
            ->whereMonth('tanggal', (int) $f['bulan'])
            ->whereYear('tanggal', (int) $f['tahun'])
            ->orderBy('tanggal')
            ->orderBy('jam')
            ->get();

        // kelompokkan per tanggal
        $grup = [];
        foreach ($entri as $e) {
            $grup[$e->tanggal->format('Y-m-d')][] = [
                'jam' => substr((string) $e->jam, 0, 5),
                'isi' => (string) $e->isi,
            ];
        }

        return response()->json([
            'sukses'      => true,
            'nama'        => $profil->nama_lengkap,
            'unit'        => trim($profil->unit_nama.($profil->sub_nama ? ' — '.$profil->sub_nama : '')),
            'total_hari'  => count($grup),
            'total_entri' => $entri->count(),
            'data'        => $grup,
        ]);
    }

    public function ambilSimrs(Request $request, SimrsService $simrs)
    {
        $data = $request->validate([
            'dari' => ['required', 'date_format:Y-m-d'],
            'sampai' => ['required', 'date_format:Y-m-d', 'after_or_equal:dari'],
        ], [
            'dari.required' => 'Tanggal awal wajib diisi.',
            'sampai.required' => 'Tanggal akhir wajib diisi.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $mapping = MappingSIMRSAccount::where('user_id', (int) session('uid'))->first();
        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akun Anda belum terMapping ke SIMRS. Lakukan mapping pada menu Update Data.',
            ]);
        }

        $ids = [$mapping->simrs_user_id];

        $ht = $simrs->cariTindakan($ids, $data['dari'], $data['sampai']);
        $hl = $simrs->cariLab($ids, $data['dari'], $data['sampai']);

        $tindakan = $ht['sukses'] ? ($ht['data'] ?? []) : [];
        $lab = $hl['sukses'] ? ($hl['data'] ?? []) : [];

        $gabungan = collect($tindakan)->merge($lab)
            ->sort(function ($a, $b) {
                if ($a['tanggal'] !== $b['tanggal']) {
                    return $a['tanggal'] <=> $b['tanggal'];
                }
                return $a['jam'] <=> $b['jam'];
            })
            ->values()
            ->all();

        return response()->json([
            'sukses' => $ht['sukses'] || $hl['sukses'],
            'pesan' => (! $ht['sukses'] && ! $hl['sukses'])
                ? ($ht['pesan'] ?? 'Gagal mengambil data.')
                : null,
            'peringatan' => array_filter([
                $ht['sukses'] ? null : ($ht['pesan'] ?? 'Data tindakan gagal diambil.'),
                $hl['sukses'] ? null : ($hl['pesan'] ?? 'Data lab gagal diambil.'),
            ]),
            'total_tindakan' => count($tindakan),
            'total_lab' => count($lab),
            'data' => $gabungan,
        ]);
    }

    public function simpanTemplate(Request $request)
    {
        $data = $request->validate([
            'isi' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:user'],
        ], [
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
        ]);

        TemplateLogbook::create([
            'user_id' => (int) session('uid'),
            'type' => $data['type'],
            'isi' => trim($data['isi']),
        ]);

        return redirect()->route('logbook')
            ->with('success', 'Template logbook disimpan.');
    }

    public function hapusTemplate(Request $request)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
        ]);

        $terhapus = TemplateLogbook::where('id', (int) $data['template_id'])
            ->where('user_id', (int) session('uid'))
            ->delete();

        if (! $terhapus) {
            return redirect()->route('logbook')
                ->with('error', 'Template tidak ditemukan atau bukan milik Anda.');
        }

        return redirect()->route('logbook')
            ->with('success', 'Template logbook dihapus.');
    }

    public function hapus(Request $request)
    {
        $uid = (int) session('uid');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        // hanya milik sendiri dan belum diverifikasi
        $terhapus = Logbook::query()
            ->whereIn('id', array_map('intval', $data['ids']))
            ->where('user_id', $uid)
            ->where('is_verified', false)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Tidak ada data yang bisa dihapus (bukan milik Anda atau sudah diverifikasi).',
            ], 404);
        }

        catat_aktivitas('Logbook', $terhapus.' entri logbook dihapus');

        return response()->json([
            'sukses' => true,
            'pesan' => $terhapus.' entri logbook dihapus.',
        ]);
    }

    public function ubah(Request $request)
    {
        $uid = (int) session('uid');

        $data = $request->validate([
            'id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
            'jam' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        // hanya milik sendiri dan belum diverifikasi
        $terubah = Logbook::query()
            ->where('id', (int) $data['id'])
            ->where('user_id', $uid)
            ->where('is_verified', false)
            ->update([
                'tanggal' => $data['tanggal'],
                'jam' => $data['jam'],
                'isi' => trim($data['isi']),
            ]);

        if (! $terubah) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Data tidak ditemukan atau sudah diverifikasi.',
            ], 404);
        }

        catat_aktivitas('Logbook', 'Entri logbook diubah');

        return response()->json(['sukses' => true, 'pesan' => 'Entri logbook diperbarui.']);
    }

    private function daftarTemplate(): array
    {
        $uid = (int) session('uid');

        return TemplateLogbook::query()
            ->leftJoin('users', 'users.id', '=', 'template_logbooks.user_id')
            ->where(function ($q) use ($uid) {
                // type=all bisa dipakai semua user, type=user hanya pembuatnya
                $q->where('template_logbooks.type', 'all')
                    ->orWhere('template_logbooks.user_id', $uid);
            })
            ->orderByDesc('template_logbooks.created_at')
            ->get([
                'template_logbooks.id',
                'template_logbooks.isi',
                'template_logbooks.type',
                'template_logbooks.user_id',
                'users.nama_lengkap',
            ])
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'isi' => (string) $t->isi,
                'type' => (string) $t->type,
                'milik_saya' => ((int) $t->user_id) === $uid,
                'pembuat' => (string) ($t->nama_lengkap ?? '-'),
            ])
            ->all();
    }
}
