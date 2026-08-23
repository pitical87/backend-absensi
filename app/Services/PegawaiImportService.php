<?php

namespace App\Services;

use App\Models\Jabatan;
use App\Models\JadwalShift;
use App\Models\Profesi;
use App\Models\Shift;
use App\Models\SubUnit;
use App\Models\UnitKerja;
use App\Models\User;
use App\Services\AtasanLangsungService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PegawaiImportService
{
    private const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    public function impor(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $header = [];
        $barisAwal = 0;
        foreach ($sheet->getRowIterator() as $baris) {
            $barisAwal = $baris->getRowIndex();
            $kosong = true;
            $sel = $baris->getCellIterator();
            $sel->setIterateOnlyExistingCells(false);
            foreach ($sel as $c) {
                if (trim((string) $c->getValue()) !== '') {
                    $kosong = false;
                    break;
                }
            }
            if (! $kosong) break;
        }

        if (! $barisAwal) {
            return ['sukses' => 0, 'galat' => ['Berkas kosong atau tidak memiliki data.']];
        }

        $header = $this->barisKeArray($sheet, $barisAwal);
        $peta = $this->petakanKolom($header);
        $galat = [];
        if (! isset($peta['nama_lengkap'], $peta['email'])) {
            return ['sukses' => 0, 'galat' => ['Kolom "Nama Lengkap" dan "Email" wajib ada pada baris pertama.']];
        }

        $unitNama = UnitKerja::pluck('id', 'nama');
        $subNama  = SubUnit::pluck('id', 'nama');
        $profNama = Profesi::pluck('id', 'nama');
        $jabNama  = Jabatan::pluck('id', 'nama');
        $shiftNama = Shift::pluck('id', 'kategori');

        $sukses = 0;
        $galat = [];
        $struktur = app(StrukturService::class);

        for ($i = $barisAwal + 1; $i <= $sheet->getHighestRow(); $i++) {
            $d = $this->barisKeArray($sheet, $i, $peta);

            $nama = trim((string) ($d['nama_lengkap'] ?? ''));
            $email = strtolower(trim((string) ($d['email'] ?? '')));
            if ($nama === '' && $email === '') {
                continue;
            }

            $err = $this->validasiBaris($d, $email, $nama);
            if ($err) {
                $galat[] = 'Baris ' . $i . ': ' . $err;
                continue;
            }

            $unitId = null;
            if (! empty($d['unit_kerja'])) {
                $unitId = $unitNama->get($d['unit_kerja']);
                if (! $unitId) {
                    $galat[] = 'Baris ' . $i . ': Tempat kerja "' . $d['unit_kerja'] . '" tidak dikenal.';
                    continue;
                }
            }

            $subId = null;
            if (! empty($d['sub_unit'])) {
                $subId = $subNama->get($d['sub_unit']);
                if (! $subId) {
                    $galat[] = 'Baris ' . $i . ': Sub unit "' . $d['sub_unit'] . '" tidak dikenal.';
                    continue;
                }
            }

            $profId = null;
            if (! empty($d['profesi'])) {
                $profId = $profNama->get($d['profesi']);
                if (! $profId) {
                    $galat[] = 'Baris ' . $i . ': Profesi "' . $d['profesi'] . '" tidak dikenal.';
                    continue;
                }
            }

            $kategoriJab = $this->normalKategori($d['jabatan_kategori'] ?? null) ?? 'Staf/Pelaksana';
            $namaJab = trim((string) ($d['jabatan'] ?? ''));
            $jabatanId = null;
            if ($namaJab !== '') {
                $jabatanId = $jabNama->get($namaJab);
                if (! $jabatanId) {
                    $galat[] = 'Baris ' . $i . ': Jabatan "' . $namaJab . '" tidak dikenal pada struktur.';
                    continue;
                }
                if (! in_array($kategoriJab, ['Kepala Bidang', 'Kepala Bagian', 'Kepala Seksi', 'Kepala Sub Bagian'], true)) {
                    $galat[] = 'Baris ' . $i . ': Kategori "' . $kategoriJab . '" tidak memiliki nama jabatan.'
                        . ' Gunakan kategori Kepala Seksi/Kepala Sub Bagian/Kepala Bidang/Kepala Bagian'
                        . ' atau kosongkan kolom Jabatan.';
                    continue;
                }
            }

            [$kategoriJab, $jabatanId, $galatJab] = $struktur->resolusi($kategoriJab, $jabatanId);
            if ($galatJab !== '') {
                $galat[] = 'Baris ' . $i . ': ' . $galatJab;
                continue;
            }

            $statusPegawai = strtoupper(trim((string) ($d['status_pegawai'] ?? ''))) === 'PNS' ? 'PNS' : 'Non-PNS';
            $namaSeksi = trim((string) ($d['seksi_pembina'] ?? ''));
            $seksiPembinaId = null;
            if ($namaSeksi !== '') {
                $seksiPembinaId = $this->cariSeksiPembina($namaSeksi);
                if (! $seksiPembinaId) {
                    $galat[] = 'Baris ' . $i . ': Seksi/Sub Bagian pembina "' . $namaSeksi . '" tidak dikenal.';
                    continue;
                }
            }
            [$posisi, $seksiPembinaId, $galatPosisi] = $struktur->resolusiPosisi(
                (string) ($d['posisi'] ?? 'Staf'),
                $kategoriJab,
                $jabatanId,
                $seksiPembinaId
            );
            if ($galatPosisi !== '') {
                $galat[] = 'Baris ' . $i . ': ' . $galatPosisi;
                continue;
            }

            $shiftId = null;
            if (! empty($d['shift'])) {
                $shiftId = $shiftNama->get(ucfirst(strtolower((string) $d['shift'])));
                if (! $shiftId) {
                    $galat[] = 'Baris ' . $i . ': Shift "' . $d['shift'] . '" tidak dikenal (gunakan Pagi/Sore/Malam).';
                    continue;
                }
            }

            $pass = (string) ($d['password'] ?? '');
            $pass = $pass !== '' ? $pass : 'pegawai123';

            $baru = User::create([
                'nama_lengkap'    => $nama,
                'tempat_lahir'    => trim((string) ($d['tempat_lahir'] ?? '')) ?: null,
                'tanggal_lahir'   => $this->normalTanggal($d['tanggal_lahir'] ?? null),
                'jenis_kelamin'   => $this->normalJK((string) ($d['jenis_kelamin'] ?? '')),
                'agama'           => $this->normalAgama((string) ($d['agama'] ?? '')),
                'email'           => $email,
                'no_hp'           => trim((string) ($d['no_hp'] ?? '')) ?: null,
                'nip'             => trim((string) ($d['nip'] ?? '')) ?: null,
                'unit_kerja_id'   => $unitId,
                'sub_unit_id'     => $subId,
                'profesi_id'      => $profId,
                'jabatan_kategori'=> $kategoriJab,
                'jabatan_id'      => $jabatanId,
                'posisi'          => $posisi,
                'status_pegawai'  => $statusPegawai,
                'seksi_pembina_id'=> $seksiPembinaId,
                'password_hash'   => bcrypt($pass),
                'role'            => 'pegawai',
                'status'          => 'aktif',
                'created_at'      => now(),
            ]);

            if ($shiftId) {
                JadwalShift::create([
                    'user_id'         => $baru->id,
                    'shift_id'        => $shiftId,
                    'tanggal_berlaku' => now()->toDateString(),
                    'diubah_oleh'     => session('uid'),
                    'created_at'      => now(),
                ]);
            }

            app(AtasanLangsungService::class)->warisiOtomatis($baru);

            catat_aktivitas('Import Pegawai', $nama . ' (' . $email . ')');
            $sukses++;
        }

        return ['sukses' => $sukses, 'galat' => $galat];
    }

    private function petakanKolom(array $header): array
    {
        $alias = [
            'nama_lengkap'    => ['NAMA LENGKAP', 'NAMA', 'NIK', 'NAMA PEGAWAI'],
            'tempat_lahir'    => ['TEMPAT LAHIR', 'TTL'],
            'tanggal_lahir'   => ['TANGGAL LAHIR', 'TGL LAHIR'],
            'jenis_kelamin'   => ['JENIS KELAMIN', 'JK', 'KELAMIN'],
            'agama'           => ['AGAMA'],
            'email'           => ['EMAIL', 'E-MAIL'],
            'no_hp'           => ['NO HP', 'NOMOR HP', 'NO. HP', 'HP', 'TELEPON', 'TELP'],
            'nip'             => ['NIP', 'NO NIP', 'NOMOR INDUK PEGAWAI'],
            'unit_kerja'      => ['TEMPAT KERJA', 'UNIT', 'UNIT KERJA', 'INSTANSI'],
            'sub_unit'        => ['SUB UNIT', 'SUBUNIT', 'RUANGAN', 'RUANG', 'INSTALASI'],
            'profesi'         => ['PROFESI', 'JABATAN FUNGSIONAL'],
            'jabatan_kategori'=> ['JABATAN KATEGORI', 'KATEGORI JABATAN', 'JENIS JABATAN'],
            'jabatan'         => ['NAMA JABATAN', 'JABATAN STRUKTUR', 'STRUKTUR', 'JABATAN'],
            'posisi'          => ['POSISI'],
            'seksi_pembina'   => ['SEKSI PEMBINA', 'SUB BAGIAN PEMBINA', 'PEMBINA'],
            'status_pegawai'  => ['STATUS PEGAWAI', 'STATUS'],
            'shift'           => ['SHIFT', 'GOLONGAN SHIFT'],
            'password'        => ['PASSWORD', 'KATA SANDI'],
        ];

        $peta = [];
        foreach ($header as $idx => $kolom) {
            $kunci = $this->kunciKolom(strtoupper(trim((string) $kolom)));
            foreach ($alias as $lapangan => $varian) {
                if (in_array($kunci, $varian, true)) {
                    $peta[$lapangan] = $idx;
                    break;
                }
            }
        }
        return $peta;
    }

    private function kunciKolom(string $kolom): string
    {
        $k = str_replace(['"', "'"], '', trim($kolom));
        return preg_replace('/[\s_]+/', ' ', strtoupper($k)) ?? '';
    }

    private function barisKeArray($sheet, int $nomor, array $peta = []): array
    {
        $tinggi = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestColumn()
        );
        $nilai = [];
        for ($i = 1; $i <= $tinggi; $i++) {
            $huruf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $nilai[] = $sheet->getCell($huruf . $nomor)->getValue();
        }
        if (! $peta) {
            return $nilai;
        }

        $hasil = [];
        foreach ($peta as $lapangan => $idx) {
            $hasil[$lapangan] = $nilai[$idx] ?? null;
        }
        return $hasil;
    }

    private function validasiBaris(array $d, string $email, string $nama): string
    {
        if ($nama === '') {
            return 'Nama lengkap wajib diisi.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email tidak valid.';
        }
        if (User::where('email', $email)->exists()) {
            return 'Email ' . $email . ' sudah terdaftar.';
        }
        $jk = $this->normalJK((string) ($d['jenis_kelamin'] ?? ''));
        if ((string) ($d['jenis_kelamin'] ?? '') !== '' && ! $jk) {
            return 'Jenis kelamin tidak valid (gunakan Laki-Laki / Perempuan).';
        }
        $agama = $this->normalAgama((string) ($d['agama'] ?? ''));
        if ((string) ($d['agama'] ?? '') !== '' && ! $agama) {
            return 'Agama tidak valid.';
        }
        if (trim((string) ($d['tanggal_lahir'] ?? '')) !== '' && $this->normalTanggal($d['tanggal_lahir']) === null) {
            return 'Tanggal lahir tidak valid (gunakan format YYYY-MM-DD).';
        }
        $kategori = $this->normalKategori($d['jabatan_kategori'] ?? null);
        if ((string) ($d['jabatan_kategori'] ?? '') !== '' && $kategori === '') {
            return 'Kategori jabatan tidak valid. Gunakan: ' . implode(', ', kategori_jabatan_list()) . '.';
        }
        return '';
    }

    private function normalKategori(?string $kategori): ?string
    {
        $k = trim((string) $kategori);
        if ($k === '') {
            return null;
        }
        foreach (kategori_jabatan_list() as $valid) {
            if (strcasecmp($k, $valid) === 0) {
                return $valid;
            }
        }
        return '';
    }

    private function normalJK(string $jk): ?string
    {
        $u = strtolower($jk);
        if ($u === 'l' || $u === 'laki-laki' || $u === 'laki laki' || $u === 'laki2' || $u === 'pria') {
            return 'Laki-Laki';
        }
        if ($u === 'p' || $u === 'perempuan' || $u === 'wanita') {
            return 'Perempuan';
        }
        return $jk === '' ? null : '';
    }

    private function normalAgama(string $agama): ?string
    {
        $cari = mb_convert_case(trim($agama), MB_CASE_TITLE, 'UTF-8');
        foreach (self::AGAMA as $a) {
            if (strtolower($cari) === strtolower($a)) {
                return $a;
            }
        }
        return $agama === '' ? null : '';
    }

    private function normalTanggal($nilai): ?string
    {
        if ($nilai === null || (string) $nilai === '') {
            return null;
        }
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }
        if (is_numeric($nilai)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
        }
        $t = strtotime((string) $nilai);
        return $t ? date('Y-m-d', $t) : null;
    }

    private function cariSeksiPembina(string $nama): ?int
    {
        $nama = trim($nama);
        if ($nama === '') {
            return null;
        }
        return Jabatan::whereIn('kategori', ['Kepala Seksi', 'Kepala Sub Bagian'])
            ->where('nama', $nama)
            ->value('id');
    }
}