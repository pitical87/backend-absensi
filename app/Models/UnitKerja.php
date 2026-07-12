<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'unit_kerja';

    protected $fillable = ['nama', 'punya_sub'];

    protected function casts(): array
    {
        return ['punya_sub' => 'boolean'];
    }

    public function subUnits(): HasMany
    {
        return $this->hasMany(SubUnit::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
