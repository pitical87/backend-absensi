<?php

namespace App\Models;

use CodeIgniter\Model;

class RekapBulananModel extends Model
{
    protected $table         = 'rekap_bulanan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id','bulan','tahun','total_hari_efektif','total_hadir','total_tepat_waktu','total_terlambat','total_alpa','total_izin','total_sakit','total_cuti','total_dinas_luar','total_libur','total_menit_kerja','persentase','generated_at'];
}
