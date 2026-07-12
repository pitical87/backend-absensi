<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Skema awal Sistem Absensi Pegawai RSUD Merauke (versi CodeIgniter 4).
 * Aman dijalankan pada database kosong; dipanggil otomatis oleh /install.
 */
class InitSchema extends Migration
{
    public function up()
    {
        $attr = ['ENGINE' => 'InnoDB', 'CHARSET' => 'utf8mb4', 'COLLATE' => 'utf8mb4_unicode_ci'];

        // ---------- unit_kerja ----------
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'punya_sub' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('unit_kerja', true, $attr);

        // ---------- sub_unit ----------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'unit_kerja_id' => ['type' => 'INT', 'unsigned' => true],
            'nama'          => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('unit_kerja_id');
        $this->forge->addForeignKey('unit_kerja_id', 'unit_kerja', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sub_unit', true, $attr);

        // ---------- profesi ----------
        $this->forge->addField([
            'id'   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('profesi', true, $attr);

        // ---------- shift ----------
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'kategori'    => ['type' => 'ENUM', 'constraint' => ['Pagi', 'Sore', 'Malam']],
            'jam_masuk'   => ['type' => 'TIME'],
            'jam_pulang'  => ['type' => 'TIME'],
            'lintas_hari' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'aktif'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('shift', true, $attr);

        // ---------- users ----------
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nama_lengkap'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'tempat_lahir'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'tanggal_lahir' => ['type' => 'DATE', 'null' => true],
            'jenis_kelamin' => ['type' => 'ENUM', 'constraint' => ['Laki-Laki', 'Perempuan'], 'null' => true],
            'agama'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150],
            'no_hp'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'unit_kerja_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'sub_unit_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'profesi_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'shift_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'          => ['type' => 'ENUM', 'constraint' => ['admin', 'pegawai'], 'default' => 'pegawai'],
            'status'        => ['type' => 'ENUM', 'constraint' => ['aktif', 'nonaktif'], 'default' => 'aktif'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addForeignKey('unit_kerja_id', 'unit_kerja', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('sub_unit_id', 'sub_unit', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('profesi_id', 'profesi', 'id', 'SET NULL', 'SET NULL');
        $this->forge->addForeignKey('shift_id', 'shift', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('users', true, $attr);

        // ---------- jadwal_shift ----------
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'shift_id'        => ['type' => 'INT', 'unsigned' => true],
            'tanggal_berlaku' => ['type' => 'DATE'],
            'diubah_oleh'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'tanggal_berlaku']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shift_id', 'shift', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('jadwal_shift', true, $attr);

        // ---------- absensi (kini dengan foto selfie & penanda anomali) ----------
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'unsigned' => true],
            'tanggal'           => ['type' => 'DATE'],
            'shift_id'          => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'waktu_masuk'       => ['type' => 'DATETIME', 'null' => true],
            'waktu_pulang'      => ['type' => 'DATETIME', 'null' => true],
            'lat_masuk'         => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng_masuk'         => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lat_pulang'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'lng_pulang'        => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'foto_masuk'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'foto_pulang'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'status_masuk'      => ['type' => 'ENUM', 'constraint' => ['Tepat Waktu', 'Terlambat'], 'null' => true],
            'menit_terlambat'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_menit_kerja' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'flag_anomali'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'catatan_anomali'   => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'tanggal']);
        $this->forge->addKey('tanggal');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shift_id', 'shift', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('absensi', true, $attr);

        // ---------- log_lokasi ----------
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'absensi_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'tipe'        => ['type' => 'ENUM', 'constraint' => ['datang', 'pulang']],
            'latitude'    => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'longitude'   => ['type' => 'DECIMAL', 'constraint' => '10,7'],
            'akurasi'     => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'jarak_meter' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
            'ditolak'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'waktu'       => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'waktu']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('absensi_id', 'absensi', 'id', 'SET NULL', 'SET NULL');
        $this->forge->createTable('log_lokasi', true, $attr);

        // ---------- pengajuan_izin ----------
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'jenis'           => ['type' => 'ENUM', 'constraint' => ['Izin', 'Sakit', 'Cuti', 'Dinas Luar']],
            'tanggal_mulai'   => ['type' => 'DATE'],
            'tanggal_selesai' => ['type' => 'DATE'],
            'keterangan'      => ['type' => 'TEXT', 'null' => true],
            'lampiran'        => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'status'          => ['type' => 'ENUM', 'constraint' => ['Menunggu', 'Disetujui', 'Ditolak'], 'default' => 'Menunggu'],
            'diproses_oleh'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'catatan_admin'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'processed_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'status']);
        $this->forge->addKey(['tanggal_mulai', 'tanggal_selesai']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('pengajuan_izin', true, $attr);

        // ---------- hari_libur ----------
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tanggal'    => ['type' => 'DATE'],
            'keterangan' => ['type' => 'VARCHAR', 'constraint' => 150],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tanggal');
        $this->forge->createTable('hari_libur', true, $attr);

        // ---------- login_attempts (pembatas percobaan masuk) ----------
        $this->forge->addField([
            'id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'email'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'ip'     => ['type' => 'VARCHAR', 'constraint' => 45],
            'sukses' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'waktu'  => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['email', 'waktu']);
        $this->forge->addKey(['ip', 'waktu']);
        $this->forge->createTable('login_attempts', true, $attr);

        // ---------- aktivitas_log ----------
        $this->forge->addField([
            'id'      => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'aksi'    => ['type' => 'VARCHAR', 'constraint' => 60],
            'detail'  => ['type' => 'TEXT', 'null' => true],
            'ip'      => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'waktu'   => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'waktu']);
        $this->forge->addKey('waktu');
        $this->forge->createTable('aktivitas_log', true, $attr);

        // ---------- rekap_bulanan (arsip) ----------
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'           => ['type' => 'INT', 'unsigned' => true],
            'bulan'             => ['type' => 'TINYINT', 'unsigned' => true],
            'tahun'             => ['type' => 'SMALLINT', 'unsigned' => true],
            'total_hari_efektif'=> ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_hadir'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_tepat_waktu' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_terlambat'   => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_alpa'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_izin'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_sakit'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_cuti'        => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_dinas_luar'  => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_libur'       => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'total_menit_kerja' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'persentase'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'generated_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'bulan', 'tahun']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('rekap_bulanan', true, $attr);

        // ---------- pengaturan ----------
        $this->forge->addField([
            'kunci' => ['type' => 'VARCHAR', 'constraint' => 50],
            'nilai' => ['type' => 'TEXT', 'null' => true],
        ]);
        $this->forge->addKey('kunci', true);
        $this->forge->createTable('pengaturan', true, $attr);
    }

    public function down()
    {
        foreach (['rekap_bulanan', 'aktivitas_log', 'login_attempts', 'hari_libur',
                  'pengajuan_izin', 'log_lokasi', 'absensi', 'jadwal_shift', 'users',
                  'shift', 'profesi', 'sub_unit', 'unit_kerja', 'pengaturan'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
