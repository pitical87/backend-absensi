<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DaftarAjax;
use App\Http\Controllers\Controller;
use App\Models\PengajuanJadwal;
use App\Services\UbahJadwalService;
use Illuminate\Http\Request;

class PengajuanJadwalController extends Controller
{
    use DaftarAjax;

    private const STATUS = ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'];

    public function index(Request $request)
    {
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return view('admin.jadwal.pengajuan', [
            'judulHalaman' => 'Pengajuan Perubahan Jadwal',
            'menuAktif' => 'jadwal_pengajuan',
            'daftar' => $daftar,
            'status' => $filter['status'],
            'q' => $filter['q'],
            'jumlah' => $this->jumlah(),
        ]);
    }

    public function data(Request $request)
    {
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');

        return $this->tabelAjax($filter);
    }

    public function proses(Request $request)
    {
        $id = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return $this->selesaiAjax($request, $filter, 'Pilih tindakan Setujui atau Tolak lebih dulu.', false);
        }

        $pj = PengajuanJadwal::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return $this->selesaiAjax($request, $filter, 'Pengajuan tidak ditemukan atau sudah diproses.', false);
        }

        $hasil = app(UbahJadwalService::class)->putuskan($pj, $putusan, (int) session('uid'), $catatan);

        return $this->selesaiAjax($request, $filter, $hasil['pesan'], $hasil['ok']);
    }

    private function query(array $filter)
    {
        $q = PengajuanJadwal::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama', 'user.subUnit:id,nama',
            'shiftLama:id,kategori,jam_masuk,jam_pulang', 'shiftBaru:id,kategori,jam_masuk,jam_pulang',
            'diprosesOlehUser:id,nama_lengkap',
        ])->orderByRaw("CASE status WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 ELSE 2 END")->orderByDesc('id');

        if ($filter['status'] !== 'Semua') {
            $q->where('status', $filter['status']);
        }

        if ($filter['q'] !== '') {
            $q->where(function ($sub) use ($filter) {
                $sub->where('alasan', 'like', "%{$filter['q']}%")
                    ->orWhereHas('user', function ($u) use ($filter) {
                        $u->where('nama_lengkap', 'like', "%{$filter['q']}%")
                            ->orWhere('nip', 'like', "%{$filter['q']}%");
                    });
            });
        }

        return $q;
    }

    private function jumlah(): array
    {
        $hitung = PengajuanJadwal::selectRaw('status, count(*) as jml')->groupBy('status')->pluck('jml', 'status');

        return [
            'Semua' => (int) $hitung->sum(),
            'Menunggu' => (int) $hitung->get('Menunggu', 0),
            'Disetujui' => (int) $hitung->get('Disetujui', 0),
            'Ditolak' => (int) $hitung->get('Ditolak', 0),
        ];
    }

    private function tabelAjax(array $filter, string $pesan = '', bool $sukses = true)
    {
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return $this->balasAjax(view('admin.jadwal.pengajuan_tabel', [
            'daftar' => $daftar,
            'status' => $filter['status'],
            'q' => $filter['q'],
        ])->render(), [
            'pesan' => $pesan,
            'status' => $filter['status'],
            'q' => $filter['q'],
            'hal' => $daftar->currentPage(),
            'total' => $daftar->total(),
            'totalHal' => $daftar->lastPage(),
            'jumlah' => $this->jumlah(),
        ], $sukses);
    }

    private function selesaiAjax(Request $request, array $filter, string $pesan, bool $sukses)
    {
        if (! $request->expectsJson()) {
            return redirect('admin/jadwal_pengajuan')
                ->with($sukses ? 'success' : 'error', $pesan);
        }

        return $this->tabelAjax($filter, $pesan, $sukses);
    }
}
