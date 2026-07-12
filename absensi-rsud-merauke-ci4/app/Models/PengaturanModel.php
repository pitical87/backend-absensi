<?php

namespace App\Models;

use CodeIgniter\Model;

class PengaturanModel extends Model
{
    protected $table         = 'pengaturan';
    protected $primaryKey    = 'kunci';
    protected $returnType    = 'array';
    protected $allowedFields = ['kunci','nilai'];
}
