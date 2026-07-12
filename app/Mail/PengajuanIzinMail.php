<?php

namespace App\Mail;

use App\Models\Izin;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PengajuanIzinMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Izin $izin,
        public User $pemohon,
        public string $tipe,
    ) {}

    public function envelope(): Envelope
    {
        $subjek = match ($this->tipe) {
            'baru' => 'Pengajuan ' . $this->izin->jenis . ' Baru — ' . $this->pemohon->nama_lengkap,
            'disetujui' => 'Pengajuan ' . $this->izin->jenis . ' Disetujui',
            'ditolak' => 'Pengajuan ' . $this->izin->jenis . ' Ditolak',
            default => 'Notifikasi Pengajuan Izin',
        };

        return new Envelope(subject: $subjek);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pengajuan-izin',
            with: [
                'izin' => $this->izin,
                'pemohon' => $this->pemohon,
                'tipe' => $this->tipe,
            ],
        );
    }
}
