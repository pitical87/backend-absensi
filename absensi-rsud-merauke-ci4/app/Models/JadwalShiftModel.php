<?php

namespace App\Models;

use CodeIgniter\Model;

class JadwalShiftModel extends Model
{
    protected $table         = 'jadwal_shift';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id','shift_id','tanggal_berlaku','diubah_oleh','created_at'];
}
