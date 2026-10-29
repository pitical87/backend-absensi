<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosisiJabatan extends Model
{
    protected $table = 'posisi_jabatan';

    protected $fillable = ['nama', 'tingkat', 'urutan'];

    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'posisi', 'nama');
    }
}
