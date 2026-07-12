<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama_lengkap','tempat_lahir','tanggal_lahir','jenis_kelamin','agama','email','no_hp','nip','posisi','status_pegawai','seksi_pembina_id','unit_kerja_id','sub_unit_id','profesi_id','jabatan_kategori','jabatan_id','shift_id','password_hash','role','status','created_at'];
}
