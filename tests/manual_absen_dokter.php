<?php

/**
 * Test skrip untuk absensi fleksibel dokter.
 * Jalankan: php tests/manual_absen_dokter.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Absensi;
use App\Models\Profesi;
use App\Models\User;
use App\Models\Izin;
use App\Services\AbsenService;
use App\Services\RekapService;

$pass = 0;
$fail = 0;

function jsonResponse(\Illuminate\Http\JsonResponse $res): array
{
    return json_decode($res->getContent(), true);
}

function assert_eq($actual, $expected, string $label): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
    } else {
        $fail++;
        echo "  FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

function assert_true($val, string $label): void { assert_eq($val, true, $label); }
function assert_false($val, string $label): void { assert_eq($val, false, $label); }
function assert_null($val, string $label): void { assert_eq($val, null, $label); }

// ── Setup ──────────────────────────────────────────────
$profesiDokter = Profesi::firstOrCreate(['nama' => 'Dokter']);
$profesiPerawat = Profesi::firstOrCreate(['nama' => 'Perawat']);

$dokter = User::firstOrCreate(
    ['nip' => 'TEST_DOK_001'],
    [
        'nama_lengkap' => 'dr. Test Dokter',
        'email' => 'test_dokter_' . time() . '@test.com',
        'password_hash' => bcrypt('password'),
        'role' => 'pegawai',
        'status' => 'aktif',
        'profesi_id' => $profesiDokter->id,
    ]
);

$perawat = User::firstOrCreate(
    ['nip' => 'TEST_PRT_001'],
    [
        'nama_lengkap' => 'Ns. Test Perawat',
        'email' => 'test_perawat_' . time() . '@test.com',
        'password_hash' => bcrypt('password'),
        'role' => 'pegawai',
        'status' => 'aktif',
        'profesi_id' => $profesiPerawat->id,
    ]
);

Absensi::where('user_id', $dokter->id)->delete();
Absensi::where('user_id', $perawat->id)->delete();

function userData(User $user): array
{
    $shift = $user->shift;
    return [
        'id' => $user->id,
        'shift_id' => $shift?->id,
        'shift_kategori' => $shift?->kategori ?? null,
        'shift_jam_masuk' => $shift?->jam_masuk?->format('H:i'),
        'shift_jam_pulang' => $shift?->jam_pulang?->format('H:i'),
        'role' => $user->role,
        'profesi_nama' => $user->profesi?->nama,
    ];
}

function absenDatang(User $user, ?DateTime $now = null)
{
    $now ??= new DateTime();
    $u = userData($user);
    $lat = (float) pengaturan('lokasi_lat', '-8.508266');
    $lng = (float) pengaturan('lokasi_lng', '140.3992401');
    return app(AbsenService::class)->absenDatang(
        $u, $lat, $lng, 10.0, $now, null, false, []
    );
}

function absenPulang(User $user, ?DateTime $now = null)
{
    $now ??= new DateTime();
    $u = userData($user);
    $lat = (float) pengaturan('lokasi_lat', '-8.508266');
    $lng = (float) pengaturan('lokasi_lng', '140.3992401');
    return app(AbsenService::class)->absenPulang(
        $u, $lat, $lng, 10.0, $now, null, false, []
    );
}

// ── Test 1: Dokter absen tanpa shift ───────────────────
echo "Test 1: Dokter absen tanpa shift...\n";
$res = absenDatang($dokter);
$j = jsonResponse($res);
assert_true($j['sukses'], 'sukses');
assert_null($j['status'], 'status null');
assert_null($j['bintang'], 'bintang null');
assert_null($j['keterlambatan'], 'keterlambatan null');
assert_eq($j['menit'], 0, 'menit = 0');
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 2: Status & bintang semua null ────────────────
echo "Test 2: Status & bintang dokter semua null...\n";
absenDatang($dokter);
$res = absenPulang($dokter);
$j = jsonResponse($res);
assert_true($j['sukses'], 'sukses pulang');
$db = Absensi::where('user_id', $dokter->id)->first();
assert_null($db->status_masuk, 'status_masuk null');
assert_null($db->bintang_masuk, 'bintang_masuk null');
assert_null($db->bintang_pulang, 'bintang_pulang null');
assert_null($db->bintang_harian, 'bintang_harian null');
assert_null($db->total_menit_kerja, 'total_menit_kerja null');
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 3: Multi-sesi ─────────────────────────────────
echo "Test 3: Dokter multi-sesi...\n";
absenDatang($dokter, new DateTime('08:00:00'));
absenPulang($dokter, new DateTime('12:00:00'));
$res = absenDatang($dokter, new DateTime('13:00:00'));
$j = jsonResponse($res);
assert_true($j['sukses'], 'sesi 2 masuk sukses');
$res2 = absenPulang($dokter, new DateTime('17:00:00'));
$j2 = jsonResponse($res2);
assert_true($j2['sukses'], 'sesi 2 pulang sukses');
assert_eq(Absensi::where('user_id', $dokter->id)->count(), 2, 'ada 2 record');
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 4: Sesi counter ───────────────────────────────
echo "Test 4: Sesi counter...\n";
absenDatang($dokter, new DateTime('08:00:00'));
absenPulang($dokter, new DateTime('12:00:00'));
absenDatang($dokter, new DateTime('13:00:00'));
$records = Absensi::where('user_id', $dokter->id)->orderBy('sesi')->get();
assert_eq($records->count(), 2, '2 records');
assert_eq($records[0]->sesi, 1, 'sesi 1');
assert_eq($records[1]->sesi, 2, 'sesi 2');
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 5: Sesi setelah pulang ────────────────────────
echo "Test 5: Sesi setelah pulang...\n";
absenDatang($dokter, new DateTime('08:00:00'));
absenPulang($dokter, new DateTime('12:00:00'));
$res = absenDatang($dokter, new DateTime('13:00:00'));
$j = jsonResponse($res);
assert_true($j['sukses'], 'sesi 2 sukses');
assert_true(Absensi::where('user_id', $dokter->id)->where('sesi', 2)->first() !== null, 'sesi 2 exists');
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 6: Dokter diblokir jika izin aktif ────────────
echo "Test 6: Dokter diblokir jika izin aktif...\n";
Izin::create([
    'user_id' => $dokter->id,
    'jenis' => 'Sakit',
    'status' => 'Disetujui',
    'tanggal_mulai' => now()->toDateString(),
    'tanggal_selesai' => now()->addDay()->toDateString(),
    'keterangan' => 'Test sakit',
]);
$res = absenDatang($dokter);
$j = jsonResponse($res);
assert_false($j['sukses'], 'diblokir');
assert_true(str_contains($j['pesan'], 'Sakit'), 'pesan izin');
Izin::where('user_id', $dokter->id)->delete();
Absensi::where('user_id', $dokter->id)->delete();

// ── Test 7: Dokter pulang tanpa masuk diblokir ─────────
echo "Test 7: Dokter pulang tanpa masuk...\n";
$res = absenPulang($dokter);
$j = jsonResponse($res);
assert_false($j['sukses'], 'diblokir');
assert_true(str_contains($j['pesan'], 'absen masuk'), 'pesan error');

// ── Test 8: Perawat tanpa shift diblokir ───────────────
echo "Test 8: Perawat tanpa shift diblokir...\n";
$res = absenDatang($perawat);
$j = jsonResponse($res);
assert_false($j['sukses'], 'diblokir');
assert_true(str_contains($j['pesan'], 'Shift'), 'pesan shift');

// ── Test 9: Rekap dokter multi-sesi ────────────────────
echo "Test 9: Rekap dokter multi-sesi...\n";
absenDatang($dokter, new DateTime('08:00:00'));
absenPulang($dokter, new DateTime('12:00:00'));
absenDatang($dokter, new DateTime('13:00:00'));
absenPulang($dokter, new DateTime('17:00:00'));
$rekap = app(RekapService::class)->hitung(
    $dokter->id, (int) now()->format('n'), (int) now()->format('Y')
);
assert_true($rekap['is_dokter'], 'is_dokter');
assert_eq($rekap['hadir'], 1, 'hadir = 1');
assert_null($rekap['bintang_bulanan'], 'bintang null');
$tgl = now()->format('Y-m-d');
assert_eq($rekap['per_tanggal'][$tgl]['jumlah_sesi'], 2, 'jumlah_sesi = 2');
assert_eq($rekap['per_tanggal'][$tgl]['status'], 'Hadir', 'status Hadir');
Absensi::where('user_id', $dokter->id)->delete();

// ── Cleanup ────────────────────────────────────────────
$dokter->delete();
$perawat->delete();

// ── Summary ────────────────────────────────────────────
echo "\n" . str_repeat('=', 50) . "\n";
echo "Hasil: $pass passed, $fail failed\n";
echo str_repeat('=', 50) . "\n";

exit($fail > 0 ? 1 : 0);
