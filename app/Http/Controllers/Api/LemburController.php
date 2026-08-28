<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanLembur;
use App\Services\AbsenLemburService;
use App\Services\PengajuanLemburService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    /**
     * GET /lembur — daftar pengajuan lembur sendiri + disetujui (untuk absen).
     */
    public function daftar(Request $req): JsonResponse
    {
        $u   = $req->get('user');
        $svc = app(PengajuanLemburService::class);

        $riwayat = PengajuanLembur::with('absenLembur')
            ->where('user_id', $u->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn ($r) => $this->baris($r));

        $disetujui = PengajuanLembur::with('absenLembur')
            ->where('user_id', $u->id)
            ->where('status', 'Disetujui')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get()
            ->map(fn ($r) => $this->baris($r));

        return response()->json([
            'sukses'     => true,
            'batas_jam'  => $svc->batasJam(),
            'maks_jam'   => $svc->maksLemburPerHariJam(),
            'hari_ke_depan' => PengajuanLemburService::HARI_KE_DEPAN,
            'riwayat'    => $riwayat,
            'disetujui'  => $disetujui,
        ]);
    }

    private function baris($r): array
    {
        return [
            'id'            => $r->id,
            'tanggal'       => $r->tanggal->format('Y-m-d'),
            'hari'          => $r->tanggal->locale('id')->translatedFormat('l'),
            'jam_mulai'     => Carbon::parse($r->jam_mulai)->format('H:i'),
            'jam_selesai'   => Carbon::parse($r->jam_selesai)->format('H:i'),
            'durasi_jam'    => (float) $r->durasi_jam,
            'keterangan'    => $r->keterangan,
            'status'        => $r->status,
            'catatan_keputusan' => $r->catatan_keputusan,
            'diproses_pada' => $r->diproses_pada?->toISOString(),
            'created_at'    => $r->created_at?->toISOString(),
            'absen'         => $r->absenLembur ? [
                'waktu_masuk'  => $r->absenLembur->waktu_masuk?->toISOString(),
                'waktu_pulang' => $r->absenLembur->waktu_pulang?->toISOString(),
                'status_masuk' => $r->absenLembur->status_masuk,
                'durasi_menit' => $r->absenLembur->durasi_menit,
                'bintang'      => $r->absenLembur->bintang_harian,
            ] : null,
        ];
    }

    /**
     * POST /lembur — ajukan lembur.
     * Body: tanggal (Y-m-d), jam_mulai (H:i), jam_selesai (H:i), keterangan.
     */
    public function ajukan(Request $req): JsonResponse
    {
        $u         = $req->get('user');
        $svc       = app(PengajuanLemburService::class);
        $tanggal   = trim((string) $req->input('tanggal'));
        $jamMulai  = trim((string) $req->input('jam_mulai'));
        $jamSelesai = trim((string) $req->input('jam_selesai'));
        $keterangan = trim((string) $req->input('keterangan'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter tanggal wajib format Y-m-d.'], 422);
        }
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamMulai)
            || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jamSelesai)) {
            return response()->json(['sukses' => false, 'pesan' => 'Jam mulai dan selesai wajib format H:i.'], 422);
        }
        if ($keterangan === '') {
            return response()->json(['sukses' => false, 'pesan' => 'Keterangan lembur wajib diisi.'], 422);
        }

        $hasil = $svc->simpan($u, $tanggal, $jamMulai, $jamSelesai, $keterangan);

        return response()->json([
            'sukses'        => $hasil['ok'],
            'pesan'         => $hasil['pesan'],
            'pengajuan_lembur_id' => $hasil['pengajuan']?->id,
        ], $hasil['ok'] ? 201 : 422);
    }

    /**
     * DELETE /lembur/{id} — batal pengajuan sendiri yang masih Menunggu.
     */
    public function batal(Request $req, int $id): JsonResponse
    {
        $u = $req->get('user');
        $pj = PengajuanLembur::where('id', $id)
            ->where('user_id', $u->id)
            ->where('status', 'Menunggu')
            ->first();

        if (! $pj) {
            return response()->json([
                'sukses' => false,
                'pesan'  => 'Pengajuan tidak ditemukan atau sudah diproses.',
            ], 404);
        }

        $pj->update([
            'status'            => 'Ditolak',
            'catatan_keputusan' => 'Dibatalkan oleh pemohon.',
            'diproses_pada'     => now(),
        ]);
        catat_aktivitas('Batalkan Lembur', $u->nama_lengkap . ' menarik pengajuan lembur #' . $pj->id);

        return response()->json(['sukses' => true, 'pesan' => 'Pengajuan lembur berhasil dibatalkan.']);
    }

    /**
     * GET /lembur/total — jumlah Menunggu yang berwenang diputus user (badge atasan).
     */
    public function menungguTotal(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $total = count(app(PengajuanLemburService::class)->tugasAtasan($u));

        return response()->json(['sukses' => true, 'total' => $total]);
    }

    /**
     * GET /lembur/menunggu — daftar Menunggu untuk atasan langsung.
     */
    public function menungguDaftar(Request $req): JsonResponse
    {
        $u      = $req->get('user');
        $daftar = app(PengajuanLemburService::class)->tugasAtasan($u);

        return response()->json([
            'sukses' => true,
            'total'  => count($daftar),
            'data'   => array_map(fn ($r) => [
                'id'      => $r->id,
                'pemohon' => [
                    'id'    => $r->user->id,
                    'nama'  => $r->user->nama_lengkap,
                    'unit'  => $r->user->unitKerja?->nama,
                    'sub_unit' => $r->user->subUnit?->nama,
                ],
                'tanggal'    => $r->tanggal->format('Y-m-d'),
                'jam_mulai'  => \Carbon\Carbon::parse($r->jam_mulai)->format('H:i'),
                'jam_selesai' => \Carbon\Carbon::parse($r->jam_selesai)->format('H:i'),
                'durasi_jam' => (float) $r->durasi_jam,
                'keterangan' => $r->keterangan,
                'diajukan_pada' => $r->created_at?->toISOString(),
            ], $daftar),
        ]);
    }

    /**
     * POST /lembur/proses — putuskan sebagai atasan langsung.
     * Body: id, putusan (setuju|tolak), catatan (opsional).
     */
    public function proses(Request $req): JsonResponse
    {
        $u       = $req->get('user');
        $svc     = app(PengajuanLemburService::class);
        $id      = (int) $req->input('id');
        $putusan = (string) $req->input('putusan');
        $catatan = trim((string) $req->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return response()->json(['sukses' => false, 'pesan' => 'Putusan harus setuju atau tolak.'], 422);
        }

        $pj = PengajuanLembur::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return response()->json(['sukses' => false, 'pesan' => 'Pengajuan tidak ditemukan atau sudah diproses.'], 404);
        }
        if ((int) $pj->user_id === (int) $u->id) {
            return response()->json(['sukses' => false, 'pesan' => 'Tidak dapat memutus pengajuan milik sendiri.'], 403);
        }
        if (! $svc->isAtasan($u, (int) $pj->user_id)) {
            return response()->json(['sukses' => false, 'pesan' => 'Anda bukan atasan langsung pemohon ini.'], 403);
        }

        $hasil = $svc->putuskan($pj, $putusan, $u->id, $catatan);

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan'  => $hasil['pesan'],
            'status' => $hasil['status'],
        ], $hasil['ok'] ? 200 : 422);
    }

    /**
     * GET /lembur/riwayat-persetujuan — riwayat putusan yang dibuat user (sebagai atasan).
     */
    public function riwayatPersetujuan(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $riwayat = PengajuanLembur::with('user:id,nama_lengkap')
            ->where('diproses_oleh', $u->id)
            ->where('status', '!=', 'Menunggu')
            ->orderByDesc('diproses_pada')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id'      => $r->id,
                'waktu'   => $r->diproses_pada?->toISOString(),
                'status'  => $r->status,
                'catatan' => $r->catatan_keputusan,
                'pemohon' => $r->user?->nama_lengkap,
                'tanggal' => $r->tanggal->format('Y-m-d'),
                'jam_mulai' => \Carbon\Carbon::parse($r->jam_mulai)->format('H:i'),
                'jam_selesai' => \Carbon\Carbon::parse($r->jam_selesai)->format('H:i'),
            ]);

        return response()->json(['sukses' => true, 'riwayat' => $riwayat]);
    }

    /**
     * POST /absen-lembur — absen masuk lembur.
     * Body: tanggal (Y-m-d), lat, lng, akurasi (opsional).
     */
    public function absenMasuk(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $tanggal = (string) $req->input('tanggal', now()->toDateString());
        $lat = (float) $req->input('lat');
        $lng = (float) $req->input('lng');
        $akurasi = $req->has('akurasi') ? round((float) $req->input('akurasi'), 2) : null;
        $foto = $req->input('foto');

        if (! $lat || ! $lng || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['sukses' => false, 'pesan' => 'Data absen lembur tidak lengkap.'], 422);
        }

        $fileFoto = $this->simpanSelfie($u->id, 'masuk', $foto);
        if ($fileFoto['galat']) {
            return response()->json(['sukses' => false, 'pesan' => $fileFoto['pesan']], 422);
        }

        $hasil = app(AbsenLemburService::class)->absenMasuk($u, $lat, $lng, $akurasi, $tanggal, $fileFoto['file']);

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan'  => $hasil['pesan'],
            'data'   => $hasil['data'] ?? null,
        ], $hasil['ok'] ? 200 : 422);
    }

    /**
     * PUT /absen-lembur/pulang — absen pulang lembur.
     * Body: tanggal (Y-m-d), lat, lng, akurasi (opsional).
     */
    public function absenPulang(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $tanggal = (string) $req->input('tanggal', now()->toDateString());
        $lat = (float) $req->input('lat');
        $lng = (float) $req->input('lng');
        $akurasi = $req->has('akurasi') ? round((float) $req->input('akurasi'), 2) : null;
        $foto = $req->input('foto');

        if (! $lat || ! $lng || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return response()->json(['sukses' => false, 'pesan' => 'Data absen lembur tidak lengkap.'], 422);
        }

        $fileFoto = $this->simpanSelfie($u->id, 'pulang', $foto);
        if ($fileFoto['galat']) {
            return response()->json(['sukses' => false, 'pesan' => $fileFoto['pesan']], 422);
        }

        $hasil = app(AbsenLemburService::class)->absenPulang($u, $lat, $lng, $akurasi, $tanggal, $fileFoto['file']);

        return response()->json([
            'sukses' => $hasil['ok'],
            'pesan'  => $hasil['pesan'],
            'data'   => $hasil['data'] ?? null,
        ], $hasil['ok'] ? 200 : 422);
    }

    private function simpanSelfie(int $userId, string $tipe, $foto): array
    {
        $wajibSelfie = pengaturan('wajib_selfie', '1') === '1';
        $file = null;
        if ($foto) {
            $file = app(\App\Services\AbsenService::class)->simpanSelfie($userId, $tipe, (string) $foto);
            if ($file === null && $wajibSelfie) {
                return ['galat' => true, 'pesan' => 'Foto selfie tidak valid.', 'file' => null];
            }
        } elseif ($wajibSelfie) {
            return ['galat' => true, 'pesan' => 'Foto selfie wajib disertakan.', 'file' => null];
        }

        return ['galat' => false, 'pesan' => null, 'file' => $file];
    }

    /**
     * GET /absen-lembur/status — status absen lembur per tanggal.
     */
    public function statusLembur(Request $req): JsonResponse
    {
        $u       = $req->get('user');
        $tanggal = (string) $req->query('tanggal', now()->toDateString());

        $absen = \App\Models\AbsenLembur::with('pengajuanLembur')
            ->where('user_id', $u->id)
            ->where('tanggal', $tanggal)
            ->first();

        return response()->json([
            'sukses' => true,
            'absen_masuk' => $absen?->waktu_masuk ? $absen->waktu_masuk->format('H:i') : null,
            'absen_pulang' => $absen?->waktu_pulang ? $absen->waktu_pulang->format('H:i') : null,
            'status_masuk' => $absen?->status_masuk,
            'durasi_menit' => $absen?->durasi_menit,
            'bintang' => $absen?->bintang_harian,
        ]);
    }
}
