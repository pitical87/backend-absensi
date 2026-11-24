<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ParafQrService
{
    public function tandaTangan(int $userId): string
    {
        return substr(
            hash_hmac('sha256', 'paraf-pegawai:'.$userId, (string) config('app.key')),
            0,
            12
        );
    }

    public function validasi(int $userId, string $sig): bool
    {
        return hash_equals($this->tandaTangan($userId), strtolower(trim($sig)));
    }

    public function urlPublik(int $userId): string
    {
        return route('publik.pegawai', [
            'id' => $userId,
            'sig' => $this->tandaTangan($userId),
        ]);
    }

    public function qrDataUri(int $userId): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::L,
            'scale' => 6,
            'quietzoneSize' => 1,
            'outputBase64' => true,
            'imageTransparent' => false,
        ]);

        return (new QRCode($options))->render($this->urlPublik($userId));
    }
}
