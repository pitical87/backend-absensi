<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PegawaiRequest;
use App\Models\JadwalShift;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\PegawaiImportService;
use App\Services\StrukturService;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    public function index(Request $request)
    {
        $q     = trim((string) $request->get('q'));
        $fUnit = (int) $request->get('unit');

        $b = User::select('users.*', 'uk.nama AS unit_nama', 'su.nama AS sub_nama', 'p.nama AS profesi_nama',
                     's.kategori AS shift_kategori', 's.jam_masuk AS shift_masuk', 's.jam_pulang AS shift_pulang',
                     'j.nama AS jabatan_nama')
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
            'menuAktif'    => 'pegawai',
            'pegawai'      => $pegawai,
            'unitList'     => UnitKerja::orderBy('id')->get()->all(),
            'q'            => $q,
            'fUnit'        => $fUnit,
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
            'menuAktif'    => 'pegawai',
            'edit'         => $edit,
            'unitList'     => UnitKerja::orderBy('id')->get()->all(),
            'profList'     => Profesi::orderBy('id')->get()->all(),
            'subPerUnit'   => $sub,
            'shiftGrup'    => $shiftGrup,
            'agamaList'    => self::AGAMA,
            'jabPilihan'   => $struktur->pilihan(),
            'kategoriJab'  => kategori_jabatan_list(),
            'posisiList'   => posisi_list(),
            'seksiPembinaPilihan' => array_merge(
                $struktur->pilihan()['Kepala Seksi'] ?? [],
                $struktur->pilihan()['Kepala Sub Bagian'] ?? []
            ),
        ]);
    }

    public function simpan(PegawaiRequest $request, StrukturService $struktur)
    {
        $id   = (int) $request->input('id');
        $data = $request->validated();

        [$data['jabatan_kategori'], $data['jabatan_id'], $galatJab] = $struktur->resolusi(
            (string) $request->input('jabatan_kategori'),
            (int) $request->input('jabatan_id'),
            $id
        );
        if ($galatJab !== '') {
            return back()->with('error', $galatJab);
        }

        $statusPegawai = (string) $request->input('status_pegawai') === 'PNS' ? 'PNS' : 'Non-PNS';
        [$data['posisi'], $data['seksi_pembina_id'], $galatPosisi] = $struktur->resolusiPosisi(
            (string) $request->input('posisi'),
            $data['jabatan_kategori'],
            $data['jabatan_id'],
            (int) $request->input('seksi_pembina_id') ?: null
        );
        if ($galatPosisi !== '') {
            return back()->with('error', $galatPosisi);
        }
        $data['status_pegawai'] = $statusPegawai;

        if (! empty($data['sub_unit_id']) && ! empty($data['unit_kerja_id'])) {
            $sah = SubUnit::where('id', $data['sub_unit_id'])
                        ->where('unit_kerja_id', $data['unit_kerja_id'])->exists();
            if (! $sah) {
                $data['sub_unit_id'] = null;
            }
        }

        $pass = (string) $request->input('password');
        unset($data['password']);

        if ($id) {
            $user = User::findOrFail($id);
            $lamaShiftId = $user->shift_id;
            $user->fill($data);
            if ($pass !== '') {
                $user->password_hash = bcrypt($pass);
            }
            $user->save();

            if (! empty($data['shift_id']) && (int) $lamaShiftId !== (int) $data['shift_id']) {
                JadwalShift::create([
                    'user_id'          => $id,
                    'shift_id'         => $data['shift_id'],
                    'tanggal_berlaku'  => now()->format('Y-m-d'),
                    'diubah_oleh'      => session('uid'),
                    'created_at'       => now(),
                ]);
            }
            catat_aktivitas('Ubah Pegawai', $data['nama_lengkap'] . ' (' . $data['email'] . ')');
            $pesan = 'Data pegawai diperbarui.';
        } else {
            $data['password_hash'] = bcrypt($pass);
            $user = User::create($data);
            catat_aktivitas('Tambah Pegawai', $data['nama_lengkap'] . ' (' . $data['email'] . ')');
            $pesan = 'Pegawai baru berhasil ditambahkan.';
        }

        return redirect('admin/pegawai')->with('success', $pesan);
    }

    public function impor(Request $request, PegawaiImportService $import)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $hasil = $import->impor($request->file('file')->getRealPath());

        $pesan = $hasil['sukses'] . ' pegawai berhasil diimpor.';
        if ($hasil['galat']) {
            $pesan .= ' ' . count($hasil['galat']) . ' baris gagal: ' . implode(' | ', array_slice($hasil['galat'], 0, 8));
        }
        catat_aktivitas('Import Pegawai', $pesan);

        return redirect('admin/pegawai')->with(
            $hasil['sukses'] ? 'success' : 'error',
            $pesan
        );
    }

    public function template()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pegawai');

        $kolom = [
            'nama_lengkap', 'email', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'agama', 'no_hp', 'nip', 'unit_kerja', 'sub_unit', 'profesi',
            'jabatan_kategori', 'jabatan', 'posisi', 'seksi_pembina', 'status_pegawai',
            'shift', 'password',
        ];
        $contoh = [
            'Budi Santoso', 'budi@rsud-mrk.id', 'Merauke', '1990-05-14', 'Laki-Laki',
            'Islam', '081234567890', '198605142010011001', 'Bidang Pelayanan', 'Ruang Rawat Inap',
            'Perawat', 'Kepala Seksi', 'Kepala Seksi Keperawatan', 'Kepala Seksi/Sub Bagian',
            '', 'PNS', 'Pagi', 'pegawai123',
        ];

        foreach ($kolom as $i => $nama) {
            $huruf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($huruf . '1', $nama);
            $sheet->setCellValue($huruf . '2', $contoh[$i]);
        }
        $sheet->getStyle('A1:R1')->getFont()->setBold(true);
        foreach (range('A', 'R') as $huruf) {
            $sheet->getColumnDimension($huruf)->setAutoSize(true);
        }

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $temp = tempnam(sys_get_temp_dir(), 'tpl_') . '.xlsx';
        $writer->save($temp);

        return response()->download($temp, 'template_import_pegawai.xlsx')
            ->deleteFileAfterSend(true);
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
            catat_aktivitas('Status Pegawai', $u->nama_lengkap . ' → ' . $baru);
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
            catat_aktivitas('Hapus Pegawai', $u->nama_lengkap . ' (' . $u->email . ') beserta seluruh data absensinya');
        }
        return redirect('admin/pegawai')
            ->with('success', 'Pegawai beserta seluruh data absensinya telah dihapus.');
    }
}
