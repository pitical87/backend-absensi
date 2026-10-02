<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $table = 'login_attempts';

    public $timestamps = false;

    protected $fillable = ['email', 'ip', 'sumber', 'user_agent', 'sukses', 'waktu'];

    protected function casts(): array
    {
        return [
            'sukses' => 'boolean',
            'waktu' => 'datetime',
        ];
    }

    /** Hanya percobaan yang gagal. */
    public function scopeGagal($query)
    {
        return $query->where('sukses', 0);
    }

    /** Hanya percobaan dalam rentang waktu terakhir. */
    public function scopeSejak($query, int $jam)
    {
        return $query->where('waktu', '>=', now()->subHours($jam));
    }
}
