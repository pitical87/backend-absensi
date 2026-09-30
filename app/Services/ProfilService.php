<?php

namespace App\Services;

use App\Models\User;

class ProfilService
{
    public const AGAMA = ['Katolik', 'Kristen', 'Islam', 'Hindu', 'Budha', 'Lainnya'];

    public const JENIS_KELAMIN = ['Laki-Laki', 'Perempuan'];

    /**
     * Validasi dan simpan data profil milik pengguna sendiri.
     *
     * Field `nama_lengkap` dan `email` wajib ada pada input. Field lainnya
     * hanya disentuh bila kuncinya ada di request, sehingga form yang tidak
     * mengirim field tersebut (mis. `nip` di halaman web) tidak ikut terosong.
     * Nilai `null` eksplisit berarti mengosongkan field.
     *
     * @param  array<string, mixed>  $input
     * @return array{sukses: bool, pesan: string, galat: array<string, string>}
     */
    public function perbarui(User $user, array $input): array
    {
        $galat = [];
        $data = [];

        $nama = trim((string) ($input['nama_lengkap'] ?? ''));
        if ($nama === '') {
            $galat['nama_lengkap'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($nama) > 150) {
            $galat['nama_lengkap'] = 'Nama lengkap maksimal 150 karakter.';
        } else {
            $data['nama_lengkap'] = mb_substr($nama, 0, 150);
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $galat['email'] = 'Email wajib diisi.';
        } elseif (mb_strlen($email) > 150) {
            $galat['email'] = 'Email maksimal 150 karakter.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $galat['email'] = 'Format email tidak valid.';
        } elseif (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
            $galat['email'] = 'Email sudah digunakan oleh akun lain.';
        } else {
            $data['email'] = mb_substr($email, 0, 150);
        }

        if (array_key_exists('tempat_lahir', $input)) {
            $nilai = trim((string) $input['tempat_lahir']);
            if (mb_strlen($nilai) > 100) {
                $galat['tempat_lahir'] = 'Tempat lahir maksimal 100 karakter.';
            } else {
                $data['tempat_lahir'] = $nilai !== '' ? mb_substr($nilai, 0, 100) : null;
            }
        }

        if (array_key_exists('tanggal_lahir', $input)) {
            $nilai = trim((string) $input['tanggal_lahir']);
            if ($nilai === '') {
                $data['tanggal_lahir'] = null;
            } elseif (
                ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)
                || ! checkdate((int) substr($nilai, 5, 2), (int) substr($nilai, 8, 2), (int) substr($nilai, 0, 4))
            ) {
                $galat['tanggal_lahir'] = 'Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.';
            } else {
                $data['tanggal_lahir'] = $nilai;
            }
        }

        if (array_key_exists('jenis_kelamin', $input)) {
            $nilai = trim((string) $input['jenis_kelamin']);
            if ($nilai === '') {
                $data['jenis_kelamin'] = null;
            } elseif (! in_array($nilai, self::JENIS_KELAMIN, true)) {
                $galat['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
            } else {
                $data['jenis_kelamin'] = $nilai;
            }
        }

        if (array_key_exists('agama', $input)) {
            $nilai = trim((string) $input['agama']);
            if ($nilai === '') {
                $data['agama'] = null;
            } elseif (! in_array($nilai, self::AGAMA, true)) {
                $galat['agama'] = 'Agama tidak valid.';
            } else {
                $data['agama'] = $nilai;
            }
        }

        if (array_key_exists('no_hp', $input)) {
            $nilai = trim((string) $input['no_hp']);
            if (mb_strlen($nilai) > 30) {
                $galat['no_hp'] = 'Nomor HP maksimal 30 karakter.';
            } else {
                $data['no_hp'] = $nilai !== '' ? mb_substr($nilai, 0, 30) : null;
            }
        }

        if (array_key_exists('nip', $input)) {
            $nilai = trim((string) $input['nip']);
            if (mb_strlen($nilai) > 30) {
                $galat['nip'] = 'NIP maksimal 30 karakter.';
            } else {
                $data['nip'] = $nilai !== '' ? mb_substr($nilai, 0, 30) : null;
            }
        }

        if ($galat) {
            return [
                'sukses' => false,
                'pesan'  => implode(' ', $galat),
                'galat'  => $galat,
            ];
        }

        if (isset($data['email']) && $data['email'] !== strtolower((string) $user->email)) {
            $data['email_verified_at'] = null;
        }

        $user->update($data);

        catat_aktivitas('Update Data', $user->nama_lengkap.' memperbarui data akunnya', (int) $user->id);

        return [
            'sukses' => true,
            'pesan'  => 'Data profil berhasil diperbarui.',
            'galat'  => [],
        ];
    }
}
