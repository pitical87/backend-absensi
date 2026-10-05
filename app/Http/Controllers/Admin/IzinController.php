<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use App\Models\IzinPersetujuan;
use App\Models\User;
use App\Services\AlurIzinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IzinController extends Controller
{
    /** Tab status yang tersedia. */
    private const STATUS = ['Menunggu', 'Disetujui', 'Ditolak', 'Semua'];

    public function index(Request $request)
    {
        $status = $this->statusValid($request->get('status'));

        return view('admin.izin.index', [
            'judulHalaman' => 'Persetujuan Izin & Cuti',
            'menuAktif' => 'izin',
            'status' => $status,
            'tabs' => $this->renderTabs($status),
            'isi' => $this->renderIsi($status),
        ]);
    }

    /**
     * Endpoint asinkron: isi tab status tanpa reload halaman.
     */
    public function data(Request $request): JsonResponse
    {
        $status = $this->statusValid($request->get('status'));

        return response()->json([
            'sukses' => true,
            'status' => $status,
            'tabs' => $this->renderTabs($status),
            'isi' => $this->renderIsi($status),
        ]);
    }

    public function proses(Request $request)
    {
        $id = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return $this->selesai($request, 'Pilih tindakan Setujui atau Tolak.', false);
        }

        $iz = Izin::select('pengajuan_izin.*', 'u.nama_lengkap')
            ->join('users as u', 'u.id', '=', 'pengajuan_izin.user_id')
            ->where('pengajuan_izin.id', $id)
            ->first();

        if (! $iz || $iz->status !== 'Menunggu' || in_array($iz->jenis, ['Izin', 'Cuti'], true)) {
            return $this->selesai($request,
                'Pengajuan tidak ditemukan, sudah diproses, atau memakai alur berjenjang.', false);
        }

        $statusBaru = $putusan === 'setuju' ? 'Disetujui' : 'Ditolak';
        $iz->update([
            'status' => $statusBaru,
            'diproses_oleh' => session('uid'),
            'catatan_admin' => $catatan,
            'processed_at' => now(),
        ]);
        catat_aktivitas('Proses Izin', $iz->nama_lengkap.' — '.$iz->jenis.' ('
            .$iz->tanggal_mulai.' s.d. '.$iz->tanggal_selesai.') → '.$statusBaru);

        return $this->selesai($request,
            'Pengajuan '.$iz->jenis.' atas nama '.$iz->nama_lengkap.' telah '.strtolower($statusBaru).'.', true);
    }

    public function ambilAlih(Request $request)
    {
        $id = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: 'Diambil alih oleh admin.';

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return $this->selesai($request, 'Pilih tindakan Ambil Alih: Setujui atau Tolak.', false);
        }

        $iz = Izin::find($id);
        if (! $iz || $iz->status !== 'Menunggu' || (int) $iz->tahap_aktif === 0) {
            return $this->selesai($request, 'Pengajuan tidak ditemukan atau sudah selesai diproses.', false);
        }
        $pemohon = User::find($iz->user_id);

        // AlurIzinService membaca atribut model, jadi harus lewat toArray(),
        // bukan (array) yang hanya menghasilkan properti internal model.
        $hasil = app(AlurIzinService::class)->proses(
            $iz->toArray(),
            $pemohon ? $pemohon->toArray() : [],
            (int) session('uid'),
            $putusan,
            $catatan
        );
        catat_aktivitas('Ambil Alih Persetujuan', $pemohon->nama_lengkap.' — '.$iz->jenis
            .' tahap '.label_tahap_izin((int) $iz->tahap_aktif).' → '.$hasil);

        return $this->selesai($request,
            'Tahap '.label_tahap_izin((int) $iz->tahap_aktif).' untuk '.$pemohon->nama_lengkap
            .' telah diproses admin ('.$hasil.').', true);
    }

    // ── BALASAN ───────────────────────────────────────────────────────

    /**
     * Aksi lewat AJAX dibalas JSON berisi tab yang sudah disegarkan, jadi
     * klien cukup menimpa isi tanpa memanggil endpoint data lagi.
     */
    private function selesai(Request $request, string $pesan, bool $sukses)
    {
        if ($request->expectsJson()) {
            $status = $this->statusValid($request->get('status_aktif'));

            return response()->json([
                'sukses' => $sukses,
                'pesan' => $pesan,
                'status' => $status,
                'tabs' => $this->renderTabs($status),
                'isi' => $this->renderIsi($status),
            ], $sukses ? 200 : 422);
        }

        return redirect('admin/izin')->with($sukses ? 'success' : 'error', $pesan);
    }

    // ── DATA & RENDER ─────────────────────────────────────────────────

    private function statusValid($status): string
    {
        $status = (string) $status;

        return in_array($status, self::STATUS, true) ? $status : 'Menunggu';
    }

    /**
     * @return array{daftar: array, tahapPer: array}
     */
    private function daftarPengajuan(string $status): array
    {
        $b = Izin::select('pengajuan_izin.*', 'u.nama_lengkap', 'u.posisi AS posisi_pemohon',
            'uk.nama AS unit_nama', 'su.nama AS sub_nama',
            'adm.nama_lengkap AS admin_nama')
            ->join('users as u', 'u.id', '=', 'pengajuan_izin.user_id')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'u.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'u.sub_unit_id')
            ->leftJoin('users as adm', 'adm.id', '=', 'pengajuan_izin.diproses_oleh');
        if ($status !== 'Semua') {
            $b->where('pengajuan_izin.status', $status);
        }
        $daftar = $b->orderBy('pengajuan_izin.id', 'DESC')->limit(200)->get()->all();

        $tahapPer = [];
        $idBerjenjang = array_column(array_filter($daftar,
            static fn ($r) => in_array($r->jenis, ['Izin', 'Cuti'], true)), 'id');
        if ($idBerjenjang) {
            $persetujuan = IzinPersetujuan::with('user:id,nama_lengkap')
                ->whereIn('pengajuan_id', $idBerjenjang)
                ->orderBy('tahap')
                ->get();
            foreach ($persetujuan as $p) {
                $tahapPer[(int) $p->pengajuan_id][] = $p;
            }
        }

        return ['daftar' => $daftar, 'tahapPer' => $tahapPer];
    }

    private function renderTabs(string $status): string
    {
        $jumlah = [];
        foreach (Izin::select('status', \DB::raw('COUNT(*) AS jml'))->groupBy('status')->get() as $r) {
            $jumlah[$r->status] = (int) $r->jml;
        }

        return view('admin.izin.tabs', [
            'status' => $status,
            'jumlah' => $jumlah,
            'daftarStatus' => self::STATUS,
        ])->render();
    }

    private function renderIsi(string $status): string
    {
        $tab = $this->daftarPengajuan($status);

        return view('admin.izin.tab', [
            'daftar' => $tab['daftar'],
            'tahapPer' => $tab['tahapPer'],
            'status' => $status,
        ])->render();
    }
}
