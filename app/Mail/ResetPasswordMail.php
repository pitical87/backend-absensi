<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tahun;

    public function __construct(
        public string $nama,
        public string $url,
        public int $masaBerlakuMenit,
        public string $nomor,
    ) {
        $this->tahun = now()->format('Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset Password Akun Anda — ' . config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.reset-password',
            with: [
                'nama' => $this->nama,
                'url' => $this->url,
                'masaBerlakuMenit' => $this->masaBerlakuMenit,
                'nomor' => $this->nomor,
                'tahun' => $this->tahun,
                'appName' => config('app.name'),
            ],
        );
    }
}