<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbsenMencurigakanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $namaPegawai,
        public string $isiPesan,
        public string $subjekEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjekEmail);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.absen-mencurigakan',
            with: [
                'namaPegawai' => $this->namaPegawai,
                'isiPesan' => $this->isiPesan,
                'subjekEmail' => $this->subjekEmail,
            ],
        );
    }
}
