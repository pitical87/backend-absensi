<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PegawaiRequest;
use App\Models\JadwalShift;
use App\Models\Jabatan;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
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

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $fUnit = (int) $request->get('unit');

        $b = User::select('users.*', 'uk.nama AS unit_nama', 'su.nama AS sub_nama', 'p.nama AS profesi_nama',
            's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang',
            'j.nama AS jabatan_nama')
            ->where('role', '!=', 'admin')
            ->leftJoin('unit_kerja as uk', 'uk.id', '=', 'users.unit_kerja_id')
            ->leftJoin('sub_unit as su', 'su.id', '=', 'users.sub_unit_id')
            ->leftJoin('profesi as p', 'p.id', '=', 'users.profesi_id')
            ->leftJoin('shift as s', 's.id', '=', 'users.shift_id')
            ->leftJoin('jabatan as j', 'j.id', '=', 'users.jabatan_id');
        if ($q !== '') {
            $b->where(function ($qry) use ($q) {
                $qry->where('users.nama_lengkap', 'like', "%{$q}%")->orWhere('users.email', 'like', "%{$q}%");
            });
        }
        if ($fUnit) {
            $b->where('users.unit_kerja_id', $fUnit);
        }
        $pegawai = $b->orderBy('users.nama_lengkap')->get()->all();

        return view('admin.pegawai_index', [
            'judulHalaman' => 'Data Pegawai',
            'menuAktif' => 'pegawai',
            'pegawai' => $pegawai,
            'unitList' => UnitKerja::orderBy('id')->get()->all(),
            'q' => $q,
            'fUnit' => $fUnit,
        ]);
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

        return view('admin.pegawai_form', [
            'judulHalaman' => $edit ? 'Ubah Data Pegawai' : 'Tambah Pegawai',
            'menuAktif' => 'pegawai',
            'edit' => $edit,
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
        ]);
    }

    public function simpan(PegawaiRequest $request, StrukturService $struktur)
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

        $password = (string) $request->input('password');
        unset($data['password']);

        $pesan = DB::transaction(function () use ($id, $data, $password) {
            if ($id) {
                $user = User::findOrFail($id);
                $shiftLamaId = $user->shift_id;

                $user->fill($data);
                if ($password !== '') {
                    $user->password_hash = Hash::make($password);
                }
                $user->save();

                if (! empty($data['shift_id']) && (int) $shiftLamaId !== (int) $data['shift_id']) {
                    JadwalShift::create([
                        'user_id' => $user->id,
                        'shift_id' => $data['shift_id'],
                        'tanggal_berlaku' => now()->toDateString(),
                        'diubah_oleh' => session('uid'),
                        'created_at' => now(),
                    ]);
                }
                catat_aktivitas('Ubah Pegawai', $data['nama_lengkap'].' ('.$data['email'].')');

                return 'Data pegawai diperbarui.';
            }

            $data['password_hash'] = Hash::make($password);
            $user = User::create($data);
            catat_aktivitas('Tambah Pegawai', $data['nama_lengkap'].' ('.$data['email'].')');

            return 'Pegawai baru berhasil ditambahkan.';
        });

        return redirect('admin/pegawai')->with('success', $pesan);
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
