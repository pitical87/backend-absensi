<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PegawaiRequest;
use App\Models\JadwalShift;
use App\Models\AtasanLangsung;
use App\Models\Jabatan;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AtasanLangsungService;
use App\Services\PegawaiImportService;
use App\Services\StrukturService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PegawaiController extends Controller
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    private const PER_HALAMAN = 20;

    public function index(Request $request)
    {
        $pegawai = $this->kueriPegawai($request)->paginate(self::PER_HALAMAN);

        return view('admin.pegawai.index', [
            'judulHalaman' => 'Data Pegawai',
            'menuAktif' => 'pegawai',
            'pegawai' => $pegawai,
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'subPerUnit' => $this->subPerUnit(),
            'jabatanList' => Jabatan::orderBy('kategori')->orderBy('nama')->get()->all(),
            'q' => $this->q($request),
            'fUnit' => (int) $request->get('unit'),
            'fSub' => (int) $request->get('sub'),
            'fJab' => (int) $request->get('jabatan'),
        ]);
    }

    /**
     * Endpoint asinkron untuk pencarian, filter & pagination tanpa refresh halaman.
     * Mengembalikan JSON berisi fragment HTML <tbody> dan <div class="paginasi">.
     */
    public function data(Request $request)
    {
        $pegawai = $this->kueriPegawai($request)->paginate(self::PER_HALAMAN);

        return response()->json([
            'sukses'    => true,
            'total'     => $pegawai->total(),
            'dari'      => $pegawai->firstItem(),
            'sampai'    => $pegawai->lastItem(),
            'halaman'   => $pegawai->currentPage(),
            'totalHal'  => $pegawai->lastPage(),
            'tbody'     => view('admin.pegawai.rows', ['pegawai' => $pegawai])->render(),
            'paginasi'  => view('admin.pegawai.paginasi', ['pegawai' => $pegawai])->render(),
        ]);
    }

    private function q(Request $request): string
    {
        return trim((string) $request->get('q'));
    }

    private function subPerUnit(): array
    {
        $peta = [];
        foreach (SubUnit::orderBy('unit_kerja_id')->orderBy('nama')->get() as $s) {
            $peta[(int) $s->unit_kerja_id][] = ['id' => (int) $s->id, 'nama' => $s->nama];
        }

        return $peta;
    }

    private function kueriPegawai(Request $request)
    {
        $q = $this->q($request);
        $fUnit = (int) $request->get('unit');
        $fSub = (int) $request->get('sub');
        $fJab = (int) $request->get('jabatan');

        $b = User::select('users.*', 'uk.nama AS unit_nama', 'su.nama AS sub_nama', 'p.nama AS profesi_nama',
            's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang',
            'j.nama AS jabatan_nama')
            ->where('role', '!=', 'admin')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->leftJoin('jadwal_shift as js', function ($join) {
                $join->on('js.user_id', '=', 'users.id')
                    ->where('js.tanggal_berlaku', now()->toDateString());
            })
            ->leftJoin('shift as s', 's.id', '=', 'js.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'users.jabatan_id');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('users.nama_lengkap', 'like', "%{$q}%")->orWhere('users.email', 'like', "%{$q}%");
            });
        }
        if ($fUnit) {
            $b->where('users.unit_kerja_id', $fUnit);
        }
        if ($fSub) {
            $b->where('users.sub_unit_id', $fSub);
        }
        if ($fJab) {
            $b->where('users.jabatan_id', $fJab);
        }

        return $b->orderBy('users.nama_lengkap');
    }

    /**
     * Seluruh data pegawai sesuai filter (tanpa pagination) untuk keperluan ekspor.
     */
    private function daftarEkspor(Request $request)
    {
        return $this->kueriPegawai($request)->get();
    }

    private function labelFilter(Request $request): string
    {
        $bagian = [];
        $fUnit = (int) $request->get('unit');
        $fSub = (int) $request->get('sub');
        $fJab = (int) $request->get('jabatan');
        $q = $this->q($request);

        if ($fUnit) {
            $bagian[] = 'Bidang '.((UnitKerja::find($fUnit))?->nama ?? '#'.$fUnit);
        }
        if ($fSub) {
            $bagian[] = 'Sub Bidang '.((SubUnit::find($fSub))?->nama ?? '#'.$fSub);
        }
        if ($fJab) {
            $bagian[] = 'Jabatan '.((Jabatan::find($fJab))?->nama ?? '#'.$fJab);
        }
        if ($q !== '') {
            $bagian[] = 'Cari "'.$q.'"';
        }

        return $bagian ? implode(' · ', $bagian) : 'Seluruh Pegawai';
    }

    /**
     * Halaman cetak (print) — dibuka lewat window.print().
     */
    public function cetak(Request $request)
    {
        $daftar = $this->daftarEkspor($request);

        return view('admin.pegawai.cetak', [
            'daftar' => $daftar,
            'label' => $this->labelFilter($request),
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
        ]);
    }

    /**
     * Ekspor PDF via dompdf.
     */
    public function pdf(Request $request)
    {
        $daftar = $this->daftarEkspor($request);
        $html = view('admin.pegawai.pdf', [
            'daftar' => $daftar,
            'label' => $this->labelFilter($request),
            'namaInstansi' => pengaturan('nama_instansi', 'RSUD Merauke'),
            'tanggalCetak' => now()->format('d-m-Y H:i'),
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');

        ini_set('memory_limit', '512M');
        $dompdf->render();

        catat_aktivitas('Ekspor PDF Pegawai', 'total '.$daftar->count().' pegawai');

        return $dompdf->stream('data-pegawai-'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Ekspor Excel (.xlsx) via PhpSpreadsheet.
     */
    public function excel(Request $request)
    {
        $daftar = $this->daftarEkspor($request);

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->setTitle('Data Pegawai');

        $judul = ['No', 'Nama Lengkap', 'Email', 'NIP', 'Bidang', 'Sub Bidang', 'Profesi', 'Jabatan', 'Status Pegawai', 'Status Akun'];
        $ws->fromArray($judul, null, 'A1');
        $ws->getStyle('A1:J1')->getFont()->setBold(true);
        $ws->getStyle('A1:J1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFDCE9FA');

        $no = 1;
        $baris = 2;
        foreach ($daftar as $p) {
            $ws->fromArray([
                $no++,
                $p->nama_lengkap,
                $p->email,
                $p->nip,
                $p->unit_nama,
                $p->sub_nama,
                $p->profesi_nama,
                $p->jabatan_nama,
                $p->status_pegawai,
                $p->status === 'aktif' ? 'Aktif' : 'Nonaktif',
            ], null, 'A'.$baris);
            $baris++;
        }

        foreach (range('A', 'J') as $huruf) {
            $ws->getColumnDimension($huruf)->setAutoSize(true);
        }

        catat_aktivitas('Ekspor Excel Pegawai', 'total '.$daftar->count().' pegawai');

        $writer = IOFactory::createWriter($sheet, 'Xlsx');
        $temp = tempnam(sys_get_temp_dir(), 'pgw_').'.xlsx';
        $writer->save($temp);

        return response()->download($temp, 'data-pegawai-'.now()->format('Y-m-d').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function form(StrukturService $struktur, int $id = 0)
    {
        $edit = null;
        if ($id) {
            $edit = User::find($id);
            if (! $edit) {
                return redirect('admin/pegawai')->with('error', 'Pegawai tidak ditemukan.');
            }
        }

        $sub = [];
        foreach (SubUnit::orderBy('unit_kerja_id')->orderBy('id')->get() as $s) {
            $sub[(int) $s->unit_kerja_id][] = ['id' => (int) $s->id, 'nama' => $s->nama];
        }
        $shiftGrup = [];
        foreach (Shift::where('aktif', 1)->orderBy('jam_masuk')->get() as $s) {
            $shiftGrup[$s->kategori][] = $s;
        }

        $shiftAktifId = null;
        if ($edit) {
            $shiftAktifId = JadwalShift::where('user_id', $edit->id)
                ->where('tanggal_berlaku', now()->toDateString())
                ->value('shift_id');
        }

        return view('admin.pegawai.form', [
            'judulHalaman' => $edit ? 'Ubah Data Pegawai' : 'Tambah Pegawai',
            'menuAktif' => 'pegawai',
            'edit' => $edit,
            'shiftAktifId' => $shiftAktifId,
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'profList' => Profesi::orderBy('id')->get()->all(),
            'subPerUnit' => $sub,
            'shiftGrup' => $shiftGrup,
            'agamaList' => self::AGAMA,
            'jabPilihan' => $struktur->pilihan(),
            'kategoriJab' => kategori_jabatan_list(),
            'posisiList' => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
            'atasanPilihan' => User::where('role', '!=', 'admin')
                ->when($edit, fn ($q) => $q->where('id', '!=', $edit->id))
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'atasanTerpilih' => $edit
                ? AtasanLangsung::where('user_id', $edit->id)->pluck('atasan_id')->map(fn ($v) => (int) $v)->all()
                : [],
            'atasanUnitMap' => UnitKerja::pluck('atasan_id', 'id'),
            'atasanSubMap' => SubUnit::pluck('atasan_id', 'id'),
        ]);
    }

    public function simpan(PegawaiRequest $request, StrukturService $struktur, AtasanLangsungService $atasanServis)
    {
        $id = (int) $request->input('id');
        $data = $request->validated();

        [$data['jabatan_kategori'], $data['jabatan_id'], $galatJabatan] = $struktur->resolusi(
            (string) $request->input('jabatan_kategori'),
            (int) $request->input('jabatan_id'),
            $id
        );
        if ($galatJabatan !== '') {
            throw ValidationException::withMessages(['jabatan_id' => $galatJabatan]);
        }

        [$data['posisi'], $data['seksi_pembina_id'], $galatPosisi] = $struktur->resolusiPosisi(
            (string) $request->input('posisi'),
            $data['jabatan_kategori'],
            $data['jabatan_id'],
            (int) $request->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') {
            throw ValidationException::withMessages(['posisi' => $galatPosisi]);
        }

        $data['status_pegawai'] = $request->boolean('status_pegawai') ? 'PNS' : 'Non-PNS';
        $data['role'] = 'pegawai';

        $password = (string) $request->input('password');
        $shiftHariIni = (int) ($data['shift_id'] ?? 0) ?: null;
        unset($data['password'], $data['shift_id']);

        $pesan = DB::transaction(function () use ($request, $id, $data, $password, $shiftHariIni, $atasanServis) {
            if ($id) {
                $user = User::findOrFail($id);

                $user->fill($data);
                if ($password !== '') {
                    $user->password_hash = Hash::make($password);
                }
                $user->save();

                self::aturJadwalHariIni($user->id, $shiftHariIni);
                if ($request->has('atasan')) {
                    $atasanServis->sinkron($user->id, (array) $request->input('atasan'));
                } else {
                    $atasanServis->warisiOtomatis($user);
                }
                catat_aktivitas('Ubah Pegawai', $data['nama_lengkap'].' ('.$data['email'].')');

                return 'Data pegawai diperbarui.';
            }

            $data['password_hash'] = Hash::make($password);
            $user = User::create($data);

            self::aturJadwalHariIni($user->id, $shiftHariIni);
            if ($request->has('atasan')) {
                $atasanServis->sinkron($user->id, (array) $request->input('atasan'));
            } else {
                $atasanServis->warisiOtomatis($user);
            }
            catat_aktivitas('Tambah Pegawai', $data['nama_lengkap'].' ('.$data['email'].')');

            return 'Pegawai baru berhasil ditambahkan.';
        });

        return redirect('admin/pegawai')->with('success', $pesan);
    }

    /**
     * Set ulang baris jadwal_shift milik pegawai untuk tanggal hari ini.
     * Jadwal hari lain tetap diatur lewat menu Atur Jadwal Shift.
     */
    private static function aturJadwalHariIni(int $userId, ?int $shiftId): void
    {
        JadwalShift::where('user_id', $userId)
            ->where('tanggal_berlaku', now()->toDateString())
            ->delete();

        if ($shiftId) {
            JadwalShift::create([
                'user_id' => $userId,
                'shift_id' => $shiftId,
                'tanggal_berlaku' => now()->toDateString(),
                'diubah_oleh' => session('uid'),
                'created_at' => now(),
            ]);
        }
    }

    public function impor(Request $request, PegawaiImportService $import)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $hasil = $import->impor($request->file('file')->getRealPath());

        $pesan = $hasil['sukses'].' pegawai berhasil diimpor.';
        if ($hasil['galat']) {
            $pesan .= ' '.count($hasil['galat']).' baris gagal: '.implode(' | ', array_slice($hasil['galat'], 0, 8));
        }
        catat_aktivitas('Import Pegawai', $pesan);

        return redirect('admin/pegawai')->with(
            $hasil['sukses'] ? 'success' : 'error',
            $pesan
        );
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pegawai');

        $kolom = [
            'nama_lengkap', 'email', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'agama', 'no_hp', 'nip', 'unit_kerja', 'sub_unit', 'profesi',
            'jabatan_kategori', 'jabatan', 'posisi', 'seksi_pembina', 'status_pegawai',
            'shift', 'password',
        ];

        $unitPertama  = UnitKerja::orderBy('id')->first();
        $subPertama   = $unitPertama
            ? SubUnit::where('unit_kerja_id', $unitPertama->id)->orderBy('id')->first()
            : null;
        $contoh = [
            'Budi Santoso', 'budi@rsud-mrk.id', 'Merauke', '1990-05-14', 'Laki-Laki',
            'Islam', '081234567890', '198605142010011001',
            (string) ($unitPertama?->nama ?? ''),
            (string) ($subPertama?->nama ?? ''),
            (string) (Profesi::orderBy('id')->value('nama') ?? ''),
            'Kepala Seksi', 'Kasi Keperawatan', 'Kepala Seksi/Sub Bagian',
            '', 'PNS', 'Pagi', 'pegawai123',
        ];

        foreach ($kolom as $i => $nama) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($huruf.'1', $nama);
            $sheet->setCellValue($huruf.'2', $contoh[$i]);
        }
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        foreach (range('A', 'R') as $huruf) {
            $sheet->getColumnDimension($huruf)->setAutoSize(true);
        }

        $this->tambahDaftarDropdown($spreadsheet, $sheet);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $temp = tempnam(sys_get_temp_dir(), 'tpl_').'.xlsx';
        $writer->save($temp);

        return response()->download($temp, 'template_import_pegawai.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function tambahDaftarDropdown(Spreadsheet $spreadsheet, Worksheet $pegawai): void
    {
        $daftarKolom = [
            'A' => UnitKerja::orderBy('nama')->pluck('nama')->all(),
            'B' => SubUnit::orderBy('unit_kerja_id')->orderBy('nama')->pluck('nama')->all(),
            'C' => Profesi::orderBy('nama')->pluck('nama')->all(),
            'D' => kategori_jabatan_list(),
            'E' => Jabatan::whereIn('kategori', ['Kepala Bidang', 'Kepala Bagian', 'Kepala Seksi', 'Kepala Sub Bagian'])
                ->orderBy('kategori')->orderBy('nama')->pluck('nama')->all(),
            'F' => posisi_list(),
            'G' => Jabatan::whereIn('kategori', ['Kepala Seksi', 'Kepala Sub Bagian'])
                ->orderBy('nama')->pluck('nama')->all(),
            'H' => ['PNS', 'Non-PNS'],
            'I' => Shift::where('aktif', 1)->distinct()->orderBy('kategori')->pluck('kategori')->all(),
            'J' => ['Laki-Laki', 'Perempuan'],
            'K' => self::AGAMA,
        ];

        $daftar = $spreadsheet->createSheet();
        $daftar->setTitle('Daftar');
        foreach ($daftarKolom as $huruf => $nilai) {
            $daftar->setCellValue($huruf.'1', 'Daftar '.$huruf);
            $baris = 2;
            foreach ($nilai as $item) {
                $daftar->setCellValue($huruf.$baris++, $item);
            }
        }
        $daftar->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $pemetaanDv = [
            'E' => 'J', 'F' => 'K',
            'I' => 'A', 'J' => 'B', 'K' => 'C',
            'L' => 'D', 'M' => 'E', 'N' => 'F', 'O' => 'G',
            'P' => 'H', 'Q' => 'I',
        ];
        foreach ($pemetaanDv as $kolomTpl => $kolomDaftar) {
            $jumlah = count($daftarKolom[$kolomDaftar]);
            if ($jumlah < 1) {
                continue;
            }
            $dv = new DataValidation();
            $dv->setType(DataValidation::TYPE_LIST);
            $dv->setFormula1('Daftar!$'.$kolomDaftar.'$2:$'.$kolomDaftar.'$'.($jumlah + 1));
            $dv->setAllowBlank(true);
            $dv->setShowDropDown(false);
            $dv->setShowErrorMessage(true);
            $dv->setErrorTitle('Nilai tidak valid');
            $dv->setError('Pilih nilai dari daftar dropdown pada kolom ini.');
            $pegawai->setDataValidation($kolomTpl.'2:'.$kolomTpl.'500', $dv);
        }
    }

    public function ubahStatus(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id === (int) session('uid')) {
            return redirect('admin/pegawai')
                ->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $u = User::find($id);
        if ($u) {
            $baru = $u->status === 'aktif' ? 'nonaktif' : 'aktif';
            $u->update(['status' => $baru]);
            catat_aktivitas('Status Pegawai', $u->nama_lengkap.' → '.$baru);
        }

        return redirect('admin/pegawai')->with('success', 'Status pegawai diperbarui.');
    }

    public function hapus(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id === (int) session('uid')) {
            return redirect('admin/pegawai')
                ->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }
        $u = User::find($id);
        if ($u) {
            $u->delete();
            catat_aktivitas('Hapus Pegawai', $u->nama_lengkap.' ('.$u->email.') beserta seluruh data absensinya');
        }

        return redirect('admin/pegawai')
            ->with('success', 'Pegawai beserta seluruh data absensinya telah dihapus.');
    }
}
