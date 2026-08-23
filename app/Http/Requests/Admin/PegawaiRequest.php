<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

class PegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->input('id');

        return [
            'nama_lengkap'   => ['required', 'string', 'max:255'],
            'tempat_lahir'   => ['nullable', 'string', 'max:255'],
            'tanggal_lahir'  => ['nullable', 'date'],
            'jenis_kelamin'  => ['nullable', 'in:Laki-Laki,Perempuan'],
            'agama'          => ['nullable', 'in:Katolik,Kristen,Islam,Hindu,Budha,Lainnya'],
            'email'          => array_merge(
                ['required', 'email:rfc,dns', 'max:255'],
                $id ? ["unique:users,email,{$id}"] : ['unique:users,email']
            ),
            'no_hp'          => ['nullable', 'string', 'max:20'],
            'nip'            => ['nullable', 'string', 'max:30'],
            'unit_kerja_id'  => ['required', 'integer', 'exists:unit_kerja,id'],
            'sub_unit_id'    => ['nullable', Rule::exists('sub_unit', 'id')->where(
                fn (Builder $q) => $q->where('unit_kerja_id', (int) $this->input('unit_kerja_id'))
            )],
            'profesi_id'     => ['required', 'integer', 'exists:profesi,id'],
            'shift_id'       => ['nullable', 'integer', 'exists:shift,id'],
            'role'           => ['in:admin,pegawai'],            'status'         => ['in:aktif,nonaktif'],
            'password'       => $id
                ? ['nullable', 'string', 'min:6']
                : ['required', 'string', 'min:6'],
            'jabatan_kategori'  => ['nullable', 'string'],
            'jabatan_id'        => ['nullable', 'integer'],
            'posisi'            => ['nullable', 'string'],
            'status_pegawai'    => ['nullable', 'string'],
            'seksi_pembina_id'  => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.required'        => 'Email wajib diisi.',
            'email.email'           => 'Format email tidak valid.',
            'email.unique'          => 'Email sudah digunakan pegawai lain.',
            'jenis_kelamin.in'      => 'Jenis kelamin tidak valid.',
            'password.required'     => 'Password wajib diisi untuk pegawai baru.',
            'password.min'          => 'Password minimal 6 karakter.',
            'unit_kerja_id.required' => 'Tempat kerja wajib dipilih.',
            'unit_kerja_id.exists'  => 'Unit kerja tidak valid.',
            'sub_unit_id.exists'    => 'Sub unit tidak sesuai dengan unit kerja yang dipilih.',
            'profesi_id.required'   => 'Profesi wajib dipilih.',
            'profesi_id.exists'     => 'Profesi tidak valid.',
        ];
    }
}
