<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profesi extends Model
{
    use HasFactory;

    protected $table = 'profesi';

    protected $fillable = ['nama'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
