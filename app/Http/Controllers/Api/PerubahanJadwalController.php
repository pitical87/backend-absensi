<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanJadwal;
use App\Services\UbahJadwalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerubahanJadwalController extends Controller
{
    private function barisJadwal(UbahJadwalService $svc, $jadwal, int $userId): array
    {
        $cek       = $svc->kelayakan($jadwal, $userId);
        $pengajuan = PengajuanJadwal::where('user_id', $userId)
            ->where('tanggal', \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d'))
            ->whereIn('status', ['Menunggu', 'Disetujui'])
            ->first();

        return [
            'tanggal'    => \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->format('Y-m-d'),
            'hari'       => \Carbon\Carbon::parse($jadwal->tanggal_berlaku)->locale('id')->translatedFormat('l'),
            'shift'      => $jadwal->shift ? [
                'id'         => $jadwal->shift->id,
                'kategori'   => $jadwal->shift->kategori,
                'jam_masuk'  => \Carbon\Carbon::parse($jadwal->shift->jam_masuk)->format('H:i'),
                'jam_pulang' => \Carbon\Carbon::parse($jadwal->shift->jam_pulang)->format('H:i'),
            ] : null,
            'bisa_ajukan' => $cek['ok'],
            'alasan_blok' => $cek['pesan'],
            'batas_waktu' => ($batas = $svc->batasWaktu($jadwal))?->toISOString(),
            'pengajuan_aktif' => $pengajuan ? [
                'id'     => $pengajuan->id,
                'status' => $pengajuan->status,
            ] : null,
        ];
    }

    /**
     * GET /perubahan-jadwal — jadwal mendatang + kelayakan + riwayat pengajuan sendiri.
     */
    public function daftar(Request $req): JsonResponse
    {
        $u   = $req->get('user');
        $svc = app(UbahJadwalService::class);

        $mendatang = array_map(
            fn ($j) => $this->barisJadwal($svc, $j, (int) $u->id),
            $svc->jadwalMendatang((int) $u->id)
        );

        $riwayat = PengajuanJadwal::with(['shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang'])
            ->where('user_id', $u->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'tanggal'     => $r->tanggal->format('Y-m-d'),
                'shift_lama'  => $r->shiftLama?->kategori,
                'shift_baru'  => $r->shiftBaru?->kategori,
                'alasan'      => $r->alasan,
                'status'      => $r->status,
                'catatan_keputusan' => $r->catatan_keputusan,
                'diproses_pada'     => $r->diproses_pada?->toISOString(),
                'created_at'        => $r->created_at?->toISOString(),
            ]);

        return response()->json([
            'sukses'      => true,
            'batas_jam'   => $svc->batasJam(),
            'jadwal'      => $mendatang,
            'riwayat'     => $riwayat,
        ]);
    }

    /**
     * POST /perubahan-jadwal — ajukan perubahan jadwal.
     * Body: tanggal (Y-m-d), shift_baru_id, alasan.
     */
    public function ajukan(Request $req): JsonResponse
    {
        $u     = $req->get('user');
        $svc   = app(UbahJadwalService::class);
        $tgl   = trim((string) $req->input('tanggal'));
        $shift = (int) $req->input('shift_baru_id');
        $alasan = trim((string) $req->input('alasan'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter tanggal wajib format Y-m-d.'], 422);
        }
        if ($tgl < now()->toDateString() || $tgl > now()->copy()->addDays(30)->toDateString()) {
            return response()->json(['sukses' => false, 'pesan' => 'Tanggal harus dalam rentang hari ini s.d. 30 hari ke depan.'], 422);
        }
        if ($shift < 1) {
            return response()->json(['sukses' => false, 'pesan' => 'Parameter shift_baru_id wajib diisi.'], 422);
        }
        if ($alasan === '') {
            return response()->json(['sukses' => false, 'pesan' => 'Alasan pengajuan wajib diisi.'], 422);
        }

        $hasil = $svc->simpan($u, $tgl, $shift, $alasan);

        return response()->json([
            'sukses'         => $hasil['ok'],
            'pesan'          => $hasil['pesan'],
            'pengajuan_jadwal_id' => $hasil['pengajuan']?->id,
        ], $hasil['ok'] ? 201 : 422);
    }

    /**
     * DELETE /perubahan-jadwal/{id} — batal pengajuan sendiri yang masih Menunggu.
     */
    public function batal(Request $req, int $id): JsonResponse
    {
        $u = $req->get('user');
        $pj = PengajuanJadwal::where('id', $id)
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
        catat_aktivitas('Batalkan Ubah Jadwal', $u->nama_lengkap . ' menarik pengajuan ubah jadwal #' . $pj->id);

        return response()->json(['sukses' => true, 'pesan' => 'Pengajuan perubahan jadwal berhasil dibatalkan.']);
    }

    /**
     * GET /perubahan-jadwal/total — jumlah Menunggu yang berwenang diputus user (badge atasan).
     */
    public function menungguTotal(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $total = count(app(UbahJadwalService::class)->tugasAtasan($u));

        return response()->json(['sukses' => true, 'total' => $total]);
    }

    /**
     * GET /perubahan-jadwal/menunggu — daftar Menunggu untuk atasan langsung.
     */
    public function menungguDaftar(Request $req): JsonResponse
    {
        $u      = $req->get('user');
        $daftar = app(UbahJadwalService::class)->tugasAtasan($u);

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
                'shift_lama' => $r->shiftLama?->kategori,
                'shift_baru' => [
                    'id'       => $r->shiftBaru->id,
                    'kategori' => $r->shiftBaru->kategori,
                    'jam_masuk'  => \Carbon\Carbon::parse($r->shiftBaru->jam_masuk)->format('H:i'),
                    'jam_pulang' => \Carbon\Carbon::parse($r->shiftBaru->jam_pulang)->format('H:i'),
                ],
                'alasan' => $r->alasan,
                'diajukan_pada' => $r->created_at?->toISOString(),
            ], $daftar),
        ]);
    }

    /**
     * POST /perubahan-jadwal/proses — putuskan sebagai atasan langsung.
     * Body: id, putusan (setuju|tolak), catatan (opsional).
     */
    public function proses(Request $req): JsonResponse
    {
        $u       = $req->get('user');
        $svc     = app(UbahJadwalService::class);
        $id      = (int) $req->input('id');
        $putusan = (string) $req->input('putusan');
        $catatan = trim((string) $req->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return response()->json(['sukses' => false, 'pesan' => 'Putusan harus setuju atau tolak.'], 422);
        }

        $pj = PengajuanJadwal::find($id);
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
     * GET /perubahan-jadwal/riwayat-persetujuan — riwayat putusan yang dibuat user (sebagai atasan).
     */
    public function riwayatPersetujuan(Request $req): JsonResponse
    {
        $u = $req->get('user');
        $riwayat = PengajuanJadwal::with(['user:id,nama_lengkap', 'shiftLama:id,kategori', 'shiftBaru:id,kategori'])
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
                'shift_lama' => $r->shiftLama?->kategori,
                'shift_baru' => $r->shiftBaru?->kategori,
            ]);

        return response()->json(['sukses' => true, 'riwayat' => $riwayat]);
    }
}
