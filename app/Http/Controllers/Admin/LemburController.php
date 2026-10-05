<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DaftarAjax;
use App\Http\Controllers\Controller;
use App\Models\PengajuanLembur;
use App\Services\PengajuanLemburService;
use Illuminate\Http\Request;

class LemburController extends Controller
{
    use DaftarAjax;

    private const STATUS = ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'];

    public function index(Request $request)
    {
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');
        $daftar = $this->paginateAjax($this->query($filter), $filter);

        return view('admin.lembur.index', [
            'judulHalaman' => 'Pengajuan Lembur',
            'menuAktif' => 'lembur',
            'daftar' => $daftar,
            'status' => $filter['status'],
            'q' => $filter['q'],
            'jumlah' => $this->jumlah(),
            'lemburAktif' => $this->lemburAktif(),
        ]);
    }

    public function data(Request $request)
    {
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');

        return $this->tabelAjax($filter, '', $this->lemburAktif());
    }

    public function proses(Request $request)
    {
        $filter = $this->filterAjax($request, self::STATUS, 'Semua');

        if (! $this->lemburAktif()) {
            return $this->selesaiAjax($request, $filter, 'Modul lembur sedang tidak aktif. Nyalakan lewat Pengaturan terlebih dahulu.', false);
        }

        $id = (int) $request->input('id');
        $putusan = (string) $request->input('putusan');
        $catatan = trim((string) $request->input('catatan')) ?: null;

        if (! in_array($putusan, ['setuju', 'tolak'], true)) {
            return $this->selesaiAjax($request, $filter, 'Pilih tindakan Setujui atau Tolak lebih dulu.', false);
        }

        $pj = PengajuanLembur::find($id);
        if (! $pj || $pj->status !== 'Menunggu') {
            return $this->selesaiAjax($request, $filter, 'Pengajuan tidak ditemukan atau sudah diproses.', false);
        }

        $hasil = app(PengajuanLemburService::class)->putuskan($pj, $putusan, (int) session('uid'), $catatan);

        return $this->selesaiAjax($request, $filter, $hasil['pesan'], $hasil['ok']);
    }

    private function lemburAktif(): bool
    {
        return pengaturan('aktifkan_lembur', '1') === '1';
    }

    private function query(array $filter)
    {
        $q = PengajuanLembur::with([
            'user:id,nama_lengkap,nip,unit_kerja_id,sub_unit_id',
            'user.unitKerja:id,nama', 'user.subUnit:id,nama',
            'diprosesOlehUser:id,nama_lengkap',
        ])->orderByRaw("CASE status WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 ELSE 2 END")->orderByDesc('id');

        if ($filter['status'] !== 'Semua') {
            $q->where('status', $filter['status']);
        }

        if ($filter['q'] !== '') {
            $q->where(function ($sub) use ($filter) {
                $sub->where('keterangan', 'like', "%{$filter['q']}%")
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
        $hitung = PengajuanLembur::selectRaw('status, count(*) as jml')->groupBy('status')->pluck('jml', 'status');

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

        return $this->balasAjax(view('admin.lembur.tabel', [
            'daftar' => $daftar,
            'status' => $filter['status'],
            'q' => $filter['q'],
            'lemburAktif' => $this->lemburAktif(),
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
            return redirect('admin/lembur')->with($sukses ? 'success' : 'error', $pesan);
        }

        return $this->tabelAjax($filter, $pesan, $sukses);
    }
}
