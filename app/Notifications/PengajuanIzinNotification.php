<?php

namespace App\Notifications;

use App\Models\Izin;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PengajuanIzinNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Izin $izin,
        public User $pemohon,
        public string $tipe,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = match ($this->tipe) {
            'baru' => (new MailMessage)
                ->subject('Pengajuan ' . $this->izin->jenis . ' Baru')
                ->line($this->pemohon->nama_lengkap . ' mengajukan ' . $this->izin->jenis)
                ->line('Periode: ' . $this->izin->tanggal_mulai->format('d M Y') . ' s.d. ' . $this->izin->tanggal_selesai->format('d M Y'))
                ->action('Lihat Pengajuan', route('persetujuan')),
            'disetujui' => (new MailMessage)
                ->subject('Pengajuan ' . $this->izin->jenis . ' Disetujui')
                ->line('Pengajuan ' . $this->izin->jenis . ' Anda telah disetujui.')
                ->action('Lihat Dokumen', route('izin.dokumen', $this->izin->id)),
            'ditolak' => (new MailMessage)
                ->subject('Pengajuan ' . $this->izin->jenis . ' Ditolak')
                ->line('Pengajuan ' . $this->izin->jenis . ' Anda telah ditolak.')
                ->line('Catatan: ' . ($this->izin->catatan_admin ?? '-')),
            default => (new MailMessage)->subject('Notifikasi Izin'),
        };

        return $pesan;
    }
}
