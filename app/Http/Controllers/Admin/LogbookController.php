<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\MappingSIMRSAccount;
use App\Models\TemplateLogbook;
use App\Models\User;
use Illuminate\Http\Request;

class LogbookController extends Controller
{
    public function index()
    {
        return view('admin.logbook.index', [
            'judulHalaman' => 'Buat Logbook',
            'menuAktif' => 'logbook',
            'daftarPegawai' => $this->daftarPegawai(),
            'pegawai' => $this->pegawaiTerMapping(),
            'templates' => $this->daftarTemplate(),
        ]);
    }

    public function simpan(Request $request)
    {
        $uid = (int) session('uid');
        if (! $uid) {
            return response()->json(['sukses' => false, 'pesan' => 'Sesi berakhir, silakan login ulang.'], 401);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'tanggal' => ['required', 'array', 'min:1'],
            'tanggal.*' => ['required', 'date'],
            'jam' => ['required', 'array'],
            'jam.*' => ['required', 'date_format:H:i'],
            'isi' => ['required', 'array'],
            'isi.*' => ['required', 'string', 'max:1000'],
        ], [
            'user_id.required' => 'Pilih pegawai lebih dulu pada kolom Pegawai.',
            'user_id.integer' => 'Pegawai tidak valid.',
            'user_id.exists' => 'Pegawai tidak ditemukan.',
            'tanggal.required' => 'Minimal satu baris logbook wajib diisi.',
            'tanggal.*.required' => 'Tanggal wajib diisi.',
            'jam.*.required' => 'Jam wajib diisi.',
            'jam.*.date_format' => 'Format jam tidak valid.',
            'isi.*.required' => 'Isi aktivitas wajib diisi.',
            'isi.*.max' => 'Isi aktivitas maksimal 1000 karakter.',
        ]);

        $targetId = (int) $data['user_id'];

        $sekarang = now();
        $baris = [];
        foreach ($data['tanggal'] as $i => $tgl) {
            $baris[] = [
                'user_id' => $targetId,
                'tanggal' => $tgl,
                'jam' => $data['jam'][$i],
                'isi' => trim($data['isi'][$i]),
                // entri yang dibuat admin dianggap sudah disetujui, sehingga
                // tidak perlu menunggu verifikasi atasan langsung
                'is_verified' => true,
                'verified_at' => $sekarang,
                'verified_by' => $uid,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        Logbook::insert($baris);

        $nama = User::where('id', $targetId)->value('nama_lengkap');
        catat_aktivitas('Logbook', count($baris).' entri logbook terverifikasi otomatis untuk '.$nama.' oleh admin');

        return response()->json([
            'sukses' => true,
            'pesan' => count($baris).' entri logbook '.$nama.' tersimpan dan otomatis terverifikasi.',
            'total' => count($baris),
            'terverifikasi' => true,
        ]);
    }

    public function data(Request $request)
    {
        $f = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'hal' => ['nullable', 'integer', 'min:1'],
        ]);

        $uid = (int) ($f['user_id'] ?? 0) ?: (int) session('uid');

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

    public function simpanTemplate(Request $request)
    {
        $data = $request->validate([
            'isi' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:all,user'],
        ], [
            'isi.required' => 'Isi template wajib diisi.',
            'isi.max' => 'Isi template maksimal 1000 karakter.',
            'type.required' => 'Tipe template wajib dipilih.',
            'type.in' => 'Tipe template tidak valid.',
        ]);

        TemplateLogbook::create([
            'user_id' => (int) session('uid'),
            'type' => $data['type'],
            'isi' => trim($data['isi']),
        ]);

        return redirect()->route('admin.logbook.index')
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
            return redirect()->route('admin.logbook.index')
                ->with('error', 'Template tidak ditemukan atau bukan milik Anda.');
        }

        return redirect()->route('admin.logbook.index')
            ->with('success', 'Template logbook dihapus.');
    }

    public function hapus(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $uid = (int) ($data['user_id'] ?? 0) ?: (int) session('uid');

        // hanya entri pegawai terpilih dan belum diverifikasi
        $terhapus = Logbook::query()
            ->whereIn('id', array_map('intval', $data['ids']))
            ->where('user_id', $uid)
            ->where('is_verified', false)
            ->delete();

        if (! $terhapus) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Tidak ada data yang bisa dihapus (bukan milik pegawai terpilih atau sudah diverifikasi).',
            ], 404);
        }

        catat_aktivitas('Logbook', $terhapus.' entri logbook dihapus oleh admin');

        return response()->json([
            'sukses' => true,
            'pesan' => $terhapus.' entri logbook dihapus.',
        ]);
    }

    public function ubah(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
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

        $uid = (int) ($data['user_id'] ?? 0) ?: (int) session('uid');

        // hanya entri pegawai terpilih dan belum diverifikasi
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

        catat_aktivitas('Logbook', 'Entri logbook diubah oleh admin');

        return response()->json(['sukses' => true, 'pesan' => 'Entri logbook diperbarui.']);
    }

    /** Daftar pegawai aktif yang dapat dipilih sebagai pemilik logbook. */
    private function daftarPegawai()
    {
        return User::query()
            ->where('role', '!=', 'admin')
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'nip']);
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
