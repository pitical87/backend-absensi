<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalTemplateMingguan extends Model
{
    protected $table = 'jadwal_template_mingguan';

    public $timestamps = false;

    protected $fillable = ['sub_unit_id', 'hari', 'shift_id'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
