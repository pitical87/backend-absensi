<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalShift extends Model
{
    use HasFactory;

    protected $table = 'jadwal_shift';

    protected $fillable = ['user_id', 'shift_id', 'tanggal_berlaku', 'diubah_oleh', 'created_at'];

    protected function casts(): array
    {
        return [
            'tanggal_berlaku' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
