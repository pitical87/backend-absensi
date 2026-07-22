<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Izin extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'user_id', 'jenis', 'jenis_cuti', 'tanggal_mulai', 'tanggal_selesai',
        'lama_hari', 'keterangan', 'alamat_izin', 'lampiran', 'status',
        'tahap_aktif', 'diproses_oleh', 'catatan_admin', 'nomor_surat',
        'kode_verifikasi', 'ttd_digital', 'ttd_oleh', 'ttd_waktu',
        'created_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'lama_hari' => 'integer',
            'tahap_aktif' => 'integer',
            'ttd_digital' => 'boolean',
            'ttd_waktu' => 'datetime',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persetujuan(): HasMany
    {
        return $this->hasMany(IzinPersetujuan::class, 'pengajuan_id');
    }

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function ttdOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ttd_oleh');
    }

    public function scopeDariBawahan($query, User $user){
        $query->where('status','Menunggu')
            ->where('tahap_aktif', '>',0)
            ->whereHas('user', function ($q) use ($user){
                $q->where('status','aktif');

                switch($user->posisi){
                    case 'Koordinator/Kepala Unit/Ruang/Instalasi':
                            $q->where('unit_kerja_id',$user->unit_kerja_id)
                                ->where('id', '!=',$user->id);
                            if(! empty($user->sub_unit_id)){
                                $q->where('sub_unit_id',$user->sub_unit_id);
                            }
                            break;
                    case 'Kepala Seksi/Sub Bagian':
                        $q->where('seksi_pembina_id', $user->jabatan_id)
                            ->where('id','!=',$user->id);
                            break;
                    case 'Kepala Bidang/Bagian':
                        $seksi = Jabatan::find($user->jabatan_id);
                        if($seksi && $seksi->induk_id){
                            $seksiAnak = Jabatan::where('induk_id', $seksi->induk_id)->pluck('id');
                            $q->whereIn('jabatan_id',$seksiAnak)
                                ->where('id', '!=',$user->id);
                        }else{
                            $q->whereRaw('0=1');
                        }
                        break;
                    case 'HRD':
                        break;
                    default:
                        $q->whereRaw('0=1');
                        break;
                }
            });
    }
}
