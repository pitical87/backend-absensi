<?php

namespace App\Models;

use CodeIgniter\Model;

class SubUnitModel extends Model
{
    protected $table         = 'sub_unit';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['unit_kerja_id','nama'];
}
