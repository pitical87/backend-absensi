<?php

namespace App\Notifications;

use App\Models\Notifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WebPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Notifikasi $notifikasi)
    {
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $url = $this->resolvePushUrl($this->notifikasi->url);

        return (new WebPushMessage)
            ->title($this->notifikasi->labelKategori() ?: 'Notifikasi')
            ->body($this->notifikasi->isi)
            ->icon('/favicon.svg')
            ->badge('/favicon.svg')
            ->tag('notif-' . $this->notifikasi->id)
            ->data([
                'url' => $url,
                'id' => $this->notifikasi->id,
                'kategori' => $this->notifikasi->kategori,
                'tipe' => $this->notifikasi->tipe,
            ])
            ->action('Buka', 'open')
            ->vibrate([200, 100, 200]);
    }

    /**
     * Map URL notifikasi ke route frontend agar kompatibel dengan SW + frontend.
     */
    private function resolvePushUrl(?string $url): string
    {
        $url = trim((string) $url);

        if (
            $url === ''
            || str_contains($url, '..')
            || str_starts_with($url, '//')
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)
        ) {
            return '/';
        }

        if ($url === 'lembur') {
            return '/lembur';
        }

        if ($url === 'ubah-jadwal') {
            return '/ubah-jadwal';
        }

        if ($url === 'persetujuan') {
            return '/persetujuan';
        }

        // Jangan pernah membuka route admin dari push notifikasi mobile
        if (str_starts_with($url, 'admin/')) {
            return '/';
        }

        return str_starts_with($url, '/') ? $url : '/' . $url;
    }
}
