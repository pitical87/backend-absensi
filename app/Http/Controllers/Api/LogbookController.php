<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\MappingSIMRSAccount;
use App\Models\TemplateLogbook;
use App\Services\SimrsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    public function logbookSimrs(Request $req, SimrsService $simrs, ?string $jenis = null): JsonResponse
    {
        $user = $req->get('user');
        $dari = trim((string) $req->query('dari', ''));
        $sampai = trim((string) $req->query('sampai', ''));
        $jenis = strtolower(trim((string) $jenis));

        if ($jenis !== '' && ! in_array($jenis, ['tindakan', 'lab'], true)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter jenis hanya boleh tindakan atau lab.',
            ], 422);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter dari wajib diisi dengan format YYYY-MM-DD.',
            ], 422);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Parameter sampai wajib diisi dengan format YYYY-MM-DD.',
            ], 422);
        }
        if ($sampai < $dari) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ], 422);
        }

        $mapping = MappingSIMRSAccount::where('user_id', (int) $user->id)->first();
        if (! $mapping) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Akun Anda belum terMapping ke SIMRS. Lakukan mapping akun SIMRS terlebih dahulu.',
            ]);
        }

        $ids = [$mapping->simrs_user_id];

        $ambilTindakan = $jenis === '' || $jenis === 'tindakan';
        $ambilLab = $jenis === '' || $jenis === 'lab';

        $ht = $ambilTindakan ? $simrs->cariTindakan($ids, $dari, $sampai) : null;
        $hl = $ambilLab ? $simrs->cariLab($ids, $dari, $sampai) : null;

        $tindakan = ($ht !== null && ($ht['sukses'] ?? false)) ? ($ht['data'] ?? []) : [];
        $lab = ($hl !== null && ($hl['sukses'] ?? false)) ? ($hl['data'] ?? []) : [];

        $gabungan = collect($tindakan)->merge($lab)
            ->sort(function ($a, $b) {
                if ($a['tanggal'] !== $b['tanggal']) {
                    return $a['tanggal'] <=> $b['tanggal'];
                }

                return $a['jam'] <=> $b['jam'];
            })
            ->values()
            ->all();

        $sukses = ($ambilTindakan ? (bool) ($ht['sukses'] ?? false) : true)
               && ($ambilLab ? (bool) ($hl['sukses'] ?? false) : true);

        $pesan = null;
        if (! $sukses) {
            if ($ambilTindakan && ! ($ht['sukses'] ?? false)) {
                $pesan = $ht['pesan'] ?? 'Gagal mengambil data tindakan.';
            } elseif ($ambilLab) {
                $pesan = $hl['pesan'] ?? 'Gagal mengambil data lab.';
            }
        }

        $peringatan = array_filter([
            $ambilTindakan && ! ($ht['sukses'] ?? false) ? ($ht['pesan'] ?? 'Data tindakan gagal diambil.') : null,
            $ambilLab && ! ($hl['sukses'] ?? false) ? ($hl['pesan'] ?? 'Data lab gagal diambil.') : null,
        ]);

        return response()->json([
            'sukses' => $sukses,
            'pesan' => $pesan,
            'peringatan' => array_values($peringatan),
            'jenis' => $jenis === '' ? 'gabungan' : $jenis,
            'total_tindakan' => count($tindakan),
            'total_lab' => count($lab),
            'data' => $gabungan,
        ]);
    }

    public function logbookData(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $f = $req->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'hal' => ['nullable', 'integer', 'min:1'],
        ]);

        $per = 20;
        $hal = max(1, (int) ($f['hal'] ?? 1));

        $query = Logbook::query()->where('user_id', $user->id);
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
                'verified_at' => $r->verified_at?->format('Y-m-d H:i'),
            ])->all(),
        ]);
    }

    public function logbookSimpan(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'tanggal' => ['required', 'date'],
            'jam' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'jam.date_format' => 'Format jam harus HH:MM.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $logbook = Logbook::create([
            'user_id' => $user->id,
            'tanggal' => $d['tanggal'],
            'jam' => $d['jam'],
            'isi' => trim($d['isi']),
        ]);

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menyimpan entri logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => '1 entri logbook tersimpan.',
            'id' => $logbook->id,
        ], 201);
    }

    public function logbookSimpanBulk(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'entri' => ['required', 'array', 'min:1', 'max:100'],
            'entri.*.tanggal' => ['required', 'date'],
            'entri.*.jam' => ['required', 'date_format:H:i'],
            'entri.*.isi' => ['required', 'string', 'max:1000'],
        ], [
            'entri.required' => 'Daftar entri wajib dikirim.',
            'entri.min' => 'Minimal satu entri logbook.',
            'entri.max' => 'Maksimal 100 entri per request.',
            'entri.*.tanggal.required' => 'Tanggal wajib diisi pada setiap entri.',
            'entri.*.jam.required' => 'Jam wajib diisi pada setiap entri.',
            'entri.*.jam.date_format' => 'Format jam harus HH:MM.',
            'entri.*.isi.required' => 'Isi aktivitas wajib diisi pada setiap entri.',
            'entri.*.isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $sekarang = now();
        $baris = array_map(fn ($e) => [
            'user_id' => $user->id,
            'tanggal' => $e['tanggal'],
            'jam' => $e['jam'],
            'isi' => trim($e['isi']),
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ], $d['entri']);

        Logbook::insert($baris);

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menyimpan '.count($baris).' entri logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => count($baris).' entri logbook tersimpan.',
            'total' => count($baris),
        ], 201);
    }

    public function logbookUbah(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
            'jam' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'id.required' => 'ID entri wajib dikirim.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'jam.required' => 'Jam wajib diisi.',
            'jam.date_format' => 'Format jam harus HH:MM.',
            'isi.required' => 'Isi aktivitas wajib diisi.',
            'isi.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $terubah = Logbook::where('id', (int) $d['id'])
            ->where('user_id', $user->id)
            ->where('is_verified', false)
            ->update([
                'tanggal' => $d['tanggal'],
                'jam' => $d['jam'],
                'isi' => trim($d['isi']),
            ]);

        if (! $terubah) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Entri tidak ditemukan, bukan milik Anda, atau sudah diverifikasi.',
            ], 404);
        }

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' mengubah entri logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Entri logbook diperbarui.']);
    }

    public function logbookHapus(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $terhapus = Logbook::where('id', $id)
            ->where('user_id', $user->id)
            ->where('is_verified', false)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Entri tidak ditemukan, bukan milik Anda, atau sudah diverifikasi.',
            ], 404);
        }

        catat_aktivitas('Logbook Mobile', $user->nama_lengkap.' menghapus entri logbook');

        return response()->json(['sukses' => true, 'pesan' => '1 entri logbook dihapus.']);
    }

    public function templateData(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $templates = TemplateLogbook::where(function ($q) use ($user) {
            $q->where('type', 'all')
                ->orWhere(fn ($qq) => $qq->where('type', 'user')->where('user_id', $user->id));
        })
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'sukses' => true,
            'jumlah' => $templates->count(),
            'data' => $templates->map(fn (TemplateLogbook $t) => [
                'id' => $t->id,
                'isi' => $t->isi,
                'type' => (string) $t->type,
                'milik_sendiri' => (int) $t->user_id === $user->id,
                'dibuat' => optional($t->created_at)->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function templateSimpan(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
        ]);

        $template = TemplateLogbook::create([
            'user_id' => $user->id,
            'type' => 'user',
            'isi' => trim($d['isi']),
        ]);

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' menambah template logbook');

        return response()->json([
            'sukses' => true,
            'pesan' => 'Template logbook disimpan.',
            'id' => $template->id,
        ], 201);
    }

    public function templateUbah(Request $req): JsonResponse
    {
        $user = $req->get('user');

        $d = $req->validate([
            'id' => ['required', 'integer'],
            'isi' => ['required', 'string', 'max:1000'],
        ], [
            'id.required' => 'ID template wajib dikirim.',
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
        ]);

        $terubah = TemplateLogbook::where('id', (int) $d['id'])
            ->where('user_id', $user->id)
            ->update(['isi' => trim($d['isi'])]);

        if (! $terubah) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Template tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' mengubah template logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Template logbook diperbarui.']);
    }

    public function templateHapus(Request $req, int $id): JsonResponse
    {
        $user = $req->get('user');

        $terhapus = TemplateLogbook::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Template tidak ditemukan atau bukan milik Anda.',
            ], 404);
        }

        catat_aktivitas('Template Logbook Mobile', $user->nama_lengkap.' menghapus template logbook');

        return response()->json(['sukses' => true, 'pesan' => 'Template logbook dihapus.']);
    }
}
