<?php

namespace App\Models;

use CodeIgniter\Model;

class ProfesiModel extends Model
{
    protected $table         = 'profesi';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama'];
}
