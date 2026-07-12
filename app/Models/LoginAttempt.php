<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasFactory;

    protected $table = 'login_attempts';

    public $timestamps = false;

    protected $fillable = ['email', 'ip', 'sukses', 'waktu'];

    protected function casts(): array
    {
        return [
            'sukses' => 'boolean',
            'waktu' => 'datetime',
        ];
    }
}
