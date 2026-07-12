<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubUnit extends Model
{
    use HasFactory;

    protected $table = 'sub_unit';

    protected $fillable = ['unit_kerja_id', 'nama'];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
