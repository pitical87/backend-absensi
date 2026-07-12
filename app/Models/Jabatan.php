<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'jabatan';

    protected $fillable = ['nama', 'kategori', 'induk_id', 'unit_label', 'urutan'];

    protected function casts(): array
    {
        return ['urutan' => 'integer'];
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'induk_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(Jabatan::class, 'induk_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
