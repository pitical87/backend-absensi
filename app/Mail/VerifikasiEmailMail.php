<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifikasiEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tahun;

    public function __construct(
        public string $nama,
        public string $email,
        public string $url,
        public int $masaBerlakuMenit,
        public string $nomor,
    ) {
        $this->tahun = now()->format('Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verifikasi Email Akun Anda — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verifikasi-email',
            with: [
                'nama' => $this->nama,
                'email' => $this->email,
                'url' => $this->url,
                'masaBerlakuMenit' => $this->masaBerlakuMenit,
                'nomor' => $this->nomor,
                'tahun' => $this->tahun,
                'appName' => config('app.name'),
            ],
        );
    }
}
