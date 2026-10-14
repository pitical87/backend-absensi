<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteDeleted extends Model
{
    protected $table = 'note_deleted';

    protected $fillable = ['user_id', 'nama', 'email', 'catatan', 'dihapus_oleh'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihapus_oleh')->withTrashed();
    }

    /** Catatan penghapusan terakhir milik seorang user. */
    public static function terakhirUntuk(int $userId): ?self
    {
        return static::where('user_id', $userId)->latest('id')->first();
    }

    /** Simpan catatan penghapusan user (dipanggil sebelum user di-soft-delete). */
    public static function catat(User $user, string $catatan, ?int $oleh = null): self
    {
        $oleh = $oleh ?: (session('uid') ?: null);

        if ($oleh && ! User::withTrashed()->whereKey($oleh)->exists()) {
            $oleh = null;
        }

        return static::create([
            'user_id' => $user->id,
            'nama' => $user->nama_lengkap,
            'email' => $user->email,
            'catatan' => $catatan,
            'dihapus_oleh' => $oleh,
        ]);
    }
}
