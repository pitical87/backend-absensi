<?php

namespace App\Controllers;

/**
 * Foto — menyajikan berkas dari writable/uploads dengan kontrol akses:
 * admin boleh melihat semua; pegawai hanya miliknya sendiri.
 */
class Foto extends BaseController
{
    /** Selfie absensi: /foto/{absensiId}/{datang|pulang} */
    public function tampil(int $absensiId, string $tipe)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return $this->response->setStatusCode(401)->setBody('Tidak berwenang.');
        }
        if (! in_array($tipe, ['datang', 'pulang'], true)) {
            return $this->response->setStatusCode(404)->setBody('Tidak ditemukan.');
        }

        $rec = $this->db->table('absensi')->where('id', $absensiId)->get()->getRowArray();
        if (! $rec || ($u['role'] !== 'admin' && (int) $rec['user_id'] !== (int) $u['id'])) {
            return $this->response->setStatusCode(404)->setBody('Tidak ditemukan.');
        }

        $relatif = $tipe === 'datang' ? $rec['foto_masuk'] : $rec['foto_pulang'];
        return $this->kirimBerkas($relatif);
    }

    /** Lampiran pengajuan izin: /lampiran-izin/{id} */
    public function lampiranIzin(int $id)
    {
        $u = $this->penggunaAktif();
        if (! $u) {
            return $this->response->setStatusCode(401)->setBody('Tidak berwenang.');
        }

        $iz = $this->db->table('pengajuan_izin')->where('id', $id)->get()->getRowArray();
        if (! $iz || ($u['role'] !== 'admin' && (int) $iz['user_id'] !== (int) $u['id'])) {
            return $this->response->setStatusCode(404)->setBody('Tidak ditemukan.');
        }
        return $this->kirimBerkas($iz['lampiran']);
    }

    private function kirimBerkas(?string $relatif)
    {
        // Cegah path traversal: hanya jalur relatif sederhana di dalam uploads/
        if (! $relatif || str_contains($relatif, '..') || ! preg_match('#^[a-z]+/\d{6}/[\w.\-]+$#', $relatif)) {
            return $this->response->setStatusCode(404)->setBody('Berkas tidak ditemukan.');
        }
        $jalur = WRITEPATH . 'uploads/' . $relatif;
        if (! is_file($jalur)) {
            return $this->response->setStatusCode(404)->setBody('Berkas tidak ditemukan.');
        }

        $mime = match (strtolower(pathinfo($jalur, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody(file_get_contents($jalur));
    }
}
