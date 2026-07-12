<?php

namespace App\Models;

use CodeIgniter\Model;

class IzinModel extends Model
{
    protected $table         = 'pengajuan_izin';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id','jenis','tanggal_mulai','tanggal_selesai','keterangan','lampiran','status','diproses_oleh','catatan_admin','created_at','processed_at'];
}
