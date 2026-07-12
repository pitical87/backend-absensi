<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Posisi & Alur Persetujuan Izin/Cuti.
 * - users: posisi (peran alur persetujuan) + status_pegawai (PNS/PPPK/dst).
 * - pengajuan_izin: jenis cuti, alamat selama izin, lama hari kerja, tahapan alur,
 *   nomor surat, kode verifikasi, dan tanda tangan digital Direktur.
 * - izin_persetujuan: jejak persetujuan berjenjang per tahap.
 *
 * Aman untuk instalasi lama: buka /install sekali setelah pembaruan berkas.
 */
class TambahPosisiDanAlurIzin extends Migration
{
    public function up()
    {
        $attr = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        // ---------- users ----------
        $adaU = array_map(static fn ($f) => $f->name, $this->db->getFieldData('users'));
        $baruU = [];
        if (! in_array('posisi', $adaU, true)) {
            $baruU['posisi'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => false,
                                'default' => 'Staf', 'after' => 'jabatan_id'];
        }
        if (! in_array('status_pegawai', $adaU, true)) {
            $baruU['status_pegawai'] = ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false,
                                        'default' => 'Non-PNS', 'after' => 'posisi'];
        }
        if (! in_array('seksi_pembina_id', $adaU, true)) {
            $baruU['seksi_pembina_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true,
                'after' => 'status_pegawai',
                'comment' => 'Seksi/Sub Bagian pembina - jalur persetujuan Staf & Koordinator'];
        }
        if ($baruU) {
            $this->forge->addColumn('users', $baruU);
        }
        if (! in_array('seksi_pembina_id', $adaU, true)) {
            $this->db->query(
                'ALTER TABLE users ADD CONSTRAINT fk_users_seksi_pembina
                 FOREIGN KEY (seksi_pembina_id) REFERENCES jabatan (id) ON DELETE SET NULL'
            );
        }

        // Backfill posisi dari kategori jabatan struktural yang sudah ada
        if (! in_array('posisi', $adaU, true)) {
            $this->db->query("UPDATE users SET posisi = 'Direktur' WHERE jabatan_kategori = 'Direktur'");
            $this->db->query("UPDATE users SET posisi = 'Kepala Bidang/Bagian'
                              WHERE jabatan_kategori IN ('Kepala Bidang','Kepala Bagian')");
            $this->db->query("UPDATE users SET posisi = 'Kepala Seksi/Sub Bagian'
                              WHERE jabatan_kategori IN ('Kepala Seksi','Kepala Sub Bagian')");
        }

        // ---------- pengajuan_izin ----------
        $adaI = array_map(static fn ($f) => $f->name, $this->db->getFieldData('pengajuan_izin'));
        $baruI = [];
        $tambah = static function (string $kolom, array $def) use (&$baruI, $adaI) {
            if (! in_array($kolom, $adaI, true)) $baruI[$kolom] = $def;
        };
        $tambah('jenis_cuti',      ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'jenis']);
        $tambah('lama_hari',       ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'tanggal_selesai']);
        $tambah('alamat_izin',     ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'keterangan']);
        $tambah('tahap_aktif',     ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0, 'after' => 'status']);
        $tambah('nomor_surat',     ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'catatan_admin']);
        $tambah('kode_verifikasi', ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true, 'after' => 'nomor_surat']);
        $tambah('ttd_digital',     ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0, 'after' => 'kode_verifikasi']);
        $tambah('ttd_oleh',        ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'ttd_digital']);
        $tambah('ttd_waktu',       ['type' => 'DATETIME', 'null' => true, 'after' => 'ttd_oleh']);
        if ($baruI) {
            $this->forge->addColumn('pengajuan_izin', $baruI);
        }

        // ---------- izin_persetujuan ----------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'pengajuan_id'  => ['type' => 'INT', 'unsigned' => true],
            'tahap'         => ['type' => 'TINYINT', 'unsigned' => true],
            'posisi_tahap'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'status'        => ['type' => 'ENUM',
                                'constraint' => ['Menunggu', 'Disetujui', 'Ditolak', 'Dilewati'],
                                'default' => 'Menunggu'],
            'oleh_user_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'catatan'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'waktu'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pengajuan_id', 'tahap']);
        $this->forge->addForeignKey('pengajuan_id', 'pengajuan_izin', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('izin_persetujuan', true, $attr);

        // ---------- penyelarasan nama node dengan istilah resmi terbaru ----------
        foreach ([
            ['Kasi Sarana dan Prasarana',              'Kasi Sarpras',              null],
            ['Kasi Penunjang Medis',                   'Kasi Penunjang Medik',      null],
            ['Kabid Sarana, Prasarana dan Penunjang',  'Kabid Sarpras dan Penunjang',
             'Bidang Sarpras dan Penunjang'],
        ] as [$lama, $baru, $unit]) {
            $data = ['nama' => $baru];
            if ($unit !== null) $data['unit_label'] = $unit;
            $this->db->table('jabatan')->where('nama', $lama)->update($data);
        }
    }

    public function down()
    {
        $this->forge->dropTable('izin_persetujuan', true);
        $this->forge->dropColumn('pengajuan_izin', [
            'jenis_cuti', 'lama_hari', 'alamat_izin', 'tahap_aktif',
            'nomor_surat', 'kode_verifikasi', 'ttd_digital', 'ttd_oleh', 'ttd_waktu',
        ]);
        $this->db->query('ALTER TABLE users DROP FOREIGN KEY fk_users_seksi_pembina');
        $this->forge->dropColumn('users', ['posisi', 'status_pegawai', 'seksi_pembina_id']);
    }
}
