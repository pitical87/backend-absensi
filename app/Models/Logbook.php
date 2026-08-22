<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logbook extends Model
{
    protected $table = 'logbooks';

    protected $fillable = [
        'user_id',
        'tanggal',
        'jam',
        'isi',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
