<?php

namespace App\Models;

use CodeIgniter\Model;

class HariLiburModel extends Model
{
    protected $table         = 'hari_libur';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['tanggal','keterangan'];
}
