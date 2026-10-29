<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatans extends Model
{
    protected $table = 'jabatans';

    protected $fillable = ['nama', 'untuk_struktur', 'urutan'];

    protected function casts(): array
    {
        return [
            'untuk_struktur' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function jabatan(): HasMany
    {
        return $this->hasMany(Jabatan::class, 'kategori', 'nama');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'jabatan_kategori', 'nama');
    }
}
