<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Modul Struktur Organisasi RSUD Merauke.
 * Menambahkan tabel `jabatan` (hierarki Direktur → Kabid/Kabag → Kasi/Kasubag)
 * serta kolom `nip`, `jabatan_kategori`, `jabatan_id` pada tabel `users`.
 *
 * Aman untuk instalasi lama: cukup buka /install sekali setelah pembaruan berkas.
 */
class TambahStrukturOrganisasi extends Migration
{
    public function up()
    {
        $attr = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        // ---------- jabatan (pohon struktur organisasi) ----------
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'kategori'   => ['type' => 'ENUM', 'constraint' => [
                                'Direktur', 'Kepala Bidang', 'Kepala Bagian',
                                'Kepala Seksi', 'Kepala Sub Bagian',
                             ]],
            'induk_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unit_label' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true,
                             'comment' => 'Nama unit organisasi (Bidang/Bagian) untuk tampilan'],
            'urutan'     => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('induk_id');
        $this->forge->addForeignKey('induk_id', 'jabatan', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('jabatan', true, $attr);

        // ---------- kolom baru pada users ----------
        $ada = array_map(
            static fn ($f) => $f->name,
            $this->db->getFieldData('users')
        );

        $kolomBaru = [];
        if (! in_array('nip', $ada, true)) {
            $kolomBaru['nip'] = [
                'type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'no_hp',
            ];
        }
        if (! in_array('jabatan_kategori', $ada, true)) {
            $kolomBaru['jabatan_kategori'] = [
                'type' => 'VARCHAR', 'constraint' => 30, 'null' => false,
                'default' => 'Staf/Pelaksana', 'after' => 'profesi_id',
            ];
        }
        if (! in_array('jabatan_id', $ada, true)) {
            $kolomBaru['jabatan_id'] = [
                'type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'jabatan_kategori',
            ];
        }
        if ($kolomBaru) {
            $this->forge->addColumn('users', $kolomBaru);
        }
        if (! in_array('jabatan_id', $ada, true)) {
            $this->db->query(
                'ALTER TABLE users ADD CONSTRAINT fk_users_jabatan
                 FOREIGN KEY (jabatan_id) REFERENCES jabatan (id) ON DELETE SET NULL'
            );
        }
    }

    public function down()
    {
        $this->db->query('ALTER TABLE users DROP FOREIGN KEY fk_users_jabatan');
        $this->forge->dropColumn('users', ['nip', 'jabatan_kategori', 'jabatan_id']);
        $this->forge->dropTable('jabatan', true);
    }
}
