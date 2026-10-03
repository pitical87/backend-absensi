<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Peringatan aktivitas mencurigakan: akun milik penerima dikenai percobaan
 * login gagal lebih dari batas. Isinya merinci IP dan perangkat yang dipakai.
 */
class PeringatanLoginMencurigakanMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tahun;

    /**
     * @param  array<int, array{ip: string, jumlah: int}>  $daftarIp
     * @param  array<int, array{nama: string, jumlah: int, contoh: ?string}>  $daftarPerangkat
     * @param  array<int, array{ip: string, perangkat: string, sumber: string, waktu: string}>  $percobaan
     */
    public function __construct(
        public string $nama,
        public string $email,
        public int $jumlahGagal,
        public int $jumlahIp,
        public int $jumlahPerangkat,
        public array $daftarIp,
        public array $daftarPerangkat,
        public array $percobaan,
        public int $jendelaJam,
        public string $nomor,
    ) {
        $this->tahun = now()->format('Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Aktivitas Mencurigakan pada Akun Anda — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.peringatan-login',
            with: [
                'nama' => $this->nama,
                'email' => $this->email,
                'jumlahGagal' => $this->jumlahGagal,
                'jumlahIp' => $this->jumlahIp,
                'jumlahPerangkat' => $this->jumlahPerangkat,
                'daftarIp' => $this->daftarIp,
                'daftarPerangkat' => $this->daftarPerangkat,
                'percobaan' => $this->percobaan,
                'jendelaJam' => $this->jendelaJam,
                'nomor' => $this->nomor,
                'tahun' => $this->tahun,
                'appName' => config('app.name'),
            ],
        );
    }
}