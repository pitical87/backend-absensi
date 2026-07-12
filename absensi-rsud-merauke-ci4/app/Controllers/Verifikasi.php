<?php

namespace App\Controllers;

/**
 * Verifikasi — halaman publik (tanpa login) untuk mengecek keabsahan surat
 * izin/cuti berdasarkan kode verifikasi yang tercetak pada dokumen.
 * Hanya menampilkan fakta minimal yang relevan, bukan alasan/alamat pribadi.
 */
class Verifikasi extends BaseController
{
    public function index(?string $kode = null)
    {
        $kode = strtoupper(trim((string) ($kode ?: $this->request->getGet('kode'))));
        $hasil = null;

        if ($kode !== '') {
            $hasil = $this->db->table('pengajuan_izin i')
                ->select('i.nomor_surat, i.jenis, i.jenis_cuti, i.tanggal_mulai, i.tanggal_selesai,
                          i.lama_hari, i.status, i.ttd_digital, i.ttd_waktu,
                          u.nama_lengkap, u.nip, td.nama_lengkap AS ttd_nama')
                ->join('users as u', 'u.id = i.user_id')
                ->join('users td', 'td.id = i.ttd_oleh', 'left')
                ->where('i.kode_verifikasi', $kode)
                ->get()->getRowArray();
        }

        return view('publik/verifikasi', ['kode' => $kode, 'hasil' => $hasil]);
    }
}
