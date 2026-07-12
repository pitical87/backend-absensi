<?php

namespace App\Models;

use CodeIgniter\Model;

class LogLokasiModel extends Model
{
    protected $table         = 'log_lokasi';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id','absensi_id','tipe','latitude','longitude','akurasi','jarak_meter','ditolak','waktu'];
}
