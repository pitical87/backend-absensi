<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';

    public $timestamps = false;

    protected $fillable = ['tanggal', 'keterangan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date:Y-m-d'];
    }
}
