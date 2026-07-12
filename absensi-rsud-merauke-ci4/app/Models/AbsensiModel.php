<?php

namespace App\Models;

use CodeIgniter\Model;

class AbsensiModel extends Model
{
    protected $table         = 'absensi';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id','tanggal','shift_id','waktu_masuk','waktu_pulang','lat_masuk','lng_masuk','lat_pulang','lng_pulang','foto_masuk','foto_pulang','status_masuk','menit_terlambat','total_menit_kerja','flag_anomali','catatan_anomali'];
}
