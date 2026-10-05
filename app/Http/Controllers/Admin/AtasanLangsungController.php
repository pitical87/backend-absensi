<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtasanLangsung;
use App\Models\User;
use App\Services\AtasanLangsungService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AtasanLangsungController extends Controller
{
    private const PER_HALAMAN = 15;

    public function index(Request $request)
    {
        $filter = $this->filter($request);
        $rows = $this->kueri($filter)->paginate(self::PER_HALAMAN);

        return view('admin.atasan_langsung.index', [
            'judulHalaman' => 'Atasan Langsung',
            'menuAktif' => 'atasan_langsung',
            'rows' => $rows,
            'peta' => $this->petaAtasan($rows),
            'q' => $filter['q'],
            'status' => $filter['status'],
            'statistik' => $this->statistik(),
        ]);
    }

    /**
     * Endpoint asinkron: daftar pegawai dengan pencarian, filter sudah/belum
     * diatur, dan pagination tanpa refresh halaman.
     */
    public function data(Request $request): JsonResponse
    {
        $filter = $this->filter($request);
        $rows = $this->kueri($filter)->paginate(self::PER_HALAMAN);
        $peta = $this->petaAtasan($rows);

        return response()->json([
            'sukses' => true,
            'total' => $rows->total(),
            'dari' => $rows->firstItem(),
            'sampai' => $rows->lastItem(),
            'halaman' => $rows->currentPage(),
            'totalHal' => $rows->lastPage(),
            'statistik' => $this->statistik(),
            'tbody' => view('admin.atasan_langsung.rows', ['rows' => $rows, 'peta' => $peta])->render(),
            'paginasi' => view('admin.atasan_langsung.paginasi', ['rows' => $rows])->render(),
        ]);
    }

    /**
     * Endpoint asinkron: kandidat atasan untuk pencarian di modal.
     */
    public function pilihan(Request $request): JsonResponse
    {
        $opsi = AtasanLangsungService::cariPilihan((string) $request->get('q', ''));

        return response()->json([
            'sukses' => true,
            'total' => $opsi->count(),
            'rows' => view('admin.atasan_langsung.pilihan_rows', ['opsi' => $opsi])->render(),
        ]);
    }

    /**
     * Simpan atasan langsung untuk satu atau beberapa pegawai. Menerima
     * user_ids[] (bulk) dan tetap mendukung user_id tunggal.
     */
    public function aksi(Request $request, AtasanLangsungService $servis)
    {
        $data = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'user_id' => ['nullable', 'integer'],
            'atasan' => ['nullable', 'array'],
            'atasan.*' => ['integer'],
            'mode' => ['nullable', 'in:ganti,tambah'],
        ]);

        $ids = $data['user_ids'] ?? (isset($data['user_id']) ? [$data['user_id']] : []);
        $ids = collect($ids)->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->unique()->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu pegawai.');
        }

        $atasan = $data['atasan'] ?? [];
        if (collect($atasan)->filter(fn ($v) => (int) $v > 0)->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu atasan.');
        }

        $mode = $data['mode'] ?? 'ganti';
        $hasil = $servis->sinkronBanyak($ids->all(), $atasan, $mode);

        if ($hasil['diproses'] === 0) {
            return back()->with('error', 'Tidak ada pegawai valid yang diperbarui.');
        }

        $nama = User::whereIn('id', $ids->all())->pluck('nama_lengkap')->all();
        $sembunyi = count($nama) > 3 ? implode(', ', array_slice($nama, 0, 3)).' dan '.($hasil['diproses'] - 3).' lainnya' : implode(', ', $nama);
        $kata = $mode === 'tambah' ? 'ditambahkan' : 'diperbarui';

        catat_aktivitas(
            'Atasan Langsung',
            $hasil['diproses'].' pegawai ('.$sembunyi.') — atasan '.$kata.' dengan '.$hasil['diterapkan'].' relasi'
        );

        return back()->with('success', 'Atasan langsung untuk '.$hasil['diproses'].' pegawai '.$kata.' ('.$hasil['diterapkan'].' relasi).');
    }

    /**
     * @return array{q: string, status: string}
     */
    private function filter(Request $request): array
    {
        $status = (string) $request->get('status', '');
        if (! in_array($status, ['sudah', 'belum'], true)) {
            $status = '';
        }

        return [
            'q' => trim((string) $request->get('q')),
            'status' => $status,
        ];
    }

    /**
     * @param  array{q: string, status: string}  $filter
     */
    private function kueri(array $filter)
    {
        $b = User::query()
            ->select('users.id', 'users.nama_lengkap', 'users.email', 'uk.nama AS unit_nama', 'su.nama AS sub_nama')
            ->where('users.role', '!=', 'admin')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->orderBy('users.nama_lengkap');

        if ($filter['q'] !== '') {
            $q = $filter['q'];
            $b->where(function (Builder $w) use ($q) {
                $w->where('users.nama_lengkap', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")
                    ->orWhere('uk.nama', 'like', "%{$q}%")
                    ->orWhere('su.nama', 'like', "%{$q}%");
            });
        }

        if ($filter['status'] === 'sudah') {
            $b->whereExists($this->subRelasi());
        } elseif ($filter['status'] === 'belum') {
            $b->whereNotExists($this->subRelasi());
        }

        return $b;
    }

    private function subRelasi(): \Closure
    {
        return fn ($w) => $w->selectRaw('1')
            ->from('atasan_langsung')
            ->whereColumn('atasan_langsung.user_id', 'users.id');
    }

    /**
     * Peta id → nama untuk atasan yang tampil di halaman ini, diambil dalam
     * dua query supaya tidak memuat seluruh tabel.
     */
    private function petaAtasan($rows): array
    {
        $ids = $rows->pluck('id')->all();
        if ($ids === []) {
            return ['relasi' => [], 'nama' => []];
        }

        $relasi = AtasanLangsung::whereIn('user_id', $ids)
            ->get(['user_id', 'atasan_id'])
            ->groupBy('user_id');

        $idAtasan = $relasi->collapse()->pluck('atasan_id')->unique()->all();
        $nama = User::whereIn('id', $idAtasan)->pluck('nama_lengkap', 'id');

        return ['relasi' => $relasi, 'nama' => $nama];
    }

    private function statistik(): array
    {
        $total = (int) User::where('role', '!=', 'admin')->count();
        $sudah = (int) AtasanLangsung::whereIn('user_id', User::query()->where('role', '!=', 'admin')->select('id'))
            ->distinct()->count('user_id');

        return ['total' => $total, 'sudah' => $sudah, 'belum' => max(0, $total - $sudah)];
    }
}
