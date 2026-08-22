<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateLogbook extends Model
{
    protected $table = 'template_logbooks';

    protected $fillable = ['user_id', 'type', 'isi'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
