<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\JadwalShift;
use App\Models\JadwalTemplateMingguan;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JadwalEfektifService
{
    public function shiftUntukTanggal(User $user, string $tanggal): ?Shift
    {
        $shiftId = $this->petaUntukPegawai(
            $user,
            $tanggal,
            $tanggal
        )[$tanggal] ?? null;

        return $shiftId ? Shift::find($shiftId) : null;
    }

    /**
     * Resolve jadwal untuk satu tanggal secara batch, misalnya untuk tabel absensi.
     *
     * @param  iterable<User>  $users
     * @return array<int, Shift|null>
     */
    public function shiftsUntukPegawai(iterable $users, string $tanggal): array
    {
        $daftar = collect($users)->filter()->keyBy('id');
        if ($daftar->isEmpty()) {
            return [];
        }

        $shiftPerUser = JadwalShift::whereIn('user_id', $daftar->keys())
            ->whereDate('tanggal_berlaku', $tanggal)
            ->pluck('shift_id', 'user_id')
            ->map(fn ($shiftId) => (int) $shiftId)
            ->all();

        $subUnitIds = $daftar->pluck('sub_unit_id')->filter()->unique()->values();
        $libur = HariLibur::whereDate('tanggal', $tanggal)->exists();
        if (! $libur && $subUnitIds->isNotEmpty()) {
            $hari = Carbon::parse($tanggal)->dayOfWeekIso;
            $template = JadwalTemplateMingguan::whereIn('sub_unit_id', $subUnitIds)
                ->where('hari', $hari)
                ->pluck('shift_id', 'sub_unit_id');

            foreach ($daftar as $user) {
                if (! array_key_exists((int) $user->id, $shiftPerUser)
                    && isset($template[$user->sub_unit_id])) {
                    $shiftPerUser[(int) $user->id] = (int) $template[$user->sub_unit_id];
                }
            }
        }

        $shifts = Shift::whereIn('id', array_values($shiftPerUser))->get()->keyBy('id');
        $hasil = [];
        foreach ($daftar as $user) {
            $shiftId = $shiftPerUser[(int) $user->id] ?? null;
            $hasil[(int) $user->id] = $shiftId ? $shifts->get($shiftId) : null;
        }

        return $hasil;
    }

    /**
     * Resolve jadwal rentang untuk banyak pegawai dengan beberapa query bersama.
     *
     * @param  iterable<User>  $users
     * @return array<int, array<string, int>>
     */
    public function petaUntukBanyakPegawai(iterable $users, string $mulai, string $sampai): array
    {
        $daftar = collect($users)->filter()->keyBy('id');
        if ($daftar->isEmpty()) {
            return [];
        }

        $peta = [];
        $userIds = $daftar->keys()->map(fn ($id) => (int) $id)->all();
        foreach (array_chunk($userIds, 500) as $chunkIds) {
            $tersimpan = DB::table('jadwal_shift')
                ->whereIn('user_id', $chunkIds)
                ->whereBetween('tanggal_berlaku', [$mulai, $sampai])
                ->get(['user_id', 'tanggal_berlaku', 'shift_id']);
            foreach ($tersimpan as $jadwal) {
                $peta[(int) $jadwal->user_id][substr((string) $jadwal->tanggal_berlaku, 0, 10)]
                    = (int) $jadwal->shift_id;
            }
        }

        $subUnitIds = $daftar->pluck('sub_unit_id')->filter()->unique()->values()->all();
        $template = [];
        if ($subUnitIds !== []) {
            $tplRows = DB::table('jadwal_template_mingguan')
                ->whereIn('sub_unit_id', $subUnitIds)
                ->get(['sub_unit_id', 'hari', 'shift_id']);
            foreach ($tplRows as $row) {
                $template[(int) $row->sub_unit_id][(int) $row->hari] = (int) $row->shift_id;
            }
        }
        if ($template === []) {
            return $peta;
        }

        $libur = [];
        foreach (DB::table('hari_libur')->whereBetween('tanggal', [$mulai, $sampai])->pluck('tanggal') as $tglLibur) {
            $libur[substr((string) $tglLibur, 0, 10)] = true;
        }

        $userSubMap = [];
        foreach ($daftar as $user) {
            $subId = (int) ($user->sub_unit_id ?? 0);
            if ($subId > 0 && isset($template[$subId])) {
                $userSubMap[(int) $user->id] = $subId;
            }
        }

        if ($userSubMap !== []) {
            $akhir = Carbon::parse($sampai);
            for ($tanggal = Carbon::parse($mulai); $tanggal->lte($akhir); $tanggal->addDay()) {
                $tgl = $tanggal->toDateString();
                if (isset($libur[$tgl])) {
                    continue;
                }
                $hari = $tanggal->dayOfWeekIso;

                foreach ($userSubMap as $userId => $subId) {
                    if (! isset($peta[$userId][$tgl]) && isset($template[$subId][$hari])) {
                        $peta[$userId][$tgl] = $template[$subId][$hari];
                    }
                }
            }
        }

        return $peta;
    }

    /**
     * Jadwal tersimpan mengalahkan template; tanggal libur hanya mengecualikan
     * jadwal template, bukan override yang diatur langsung untuk pegawai.
     *
     * @return array<string, int>
     */
    public function petaUntukPegawai(User $user, string $mulai, string $sampai): array
    {
        $tersimpan = JadwalShift::where('user_id', $user->id)
            ->whereBetween('tanggal_berlaku', [$mulai, $sampai])
            ->get(['tanggal_berlaku', 'shift_id'])
            ->mapWithKeys(fn ($jadwal) => [
                Carbon::parse($jadwal->tanggal_berlaku)->toDateString() => (int) $jadwal->shift_id,
            ])
            ->all();

        if (! $user->sub_unit_id) {
            return $tersimpan;
        }

        $template = JadwalTemplateMingguan::where('sub_unit_id', $user->sub_unit_id)
            ->pluck('shift_id', 'hari')
            ->map(fn ($shiftId) => (int) $shiftId)
            ->all();

        if (! $template) {
            return $tersimpan;
        }

        $libur = HariLibur::whereBetween('tanggal', [$mulai, $sampai])
            ->pluck('tanggal')
            ->map(fn ($tanggal) => Carbon::parse($tanggal)->toDateString())
            ->flip()
            ->all();

        for ($tanggal = Carbon::parse($mulai); $tanggal->lte(Carbon::parse($sampai)); $tanggal->addDay()) {
            $tgl = $tanggal->toDateString();
            $hari = $tanggal->dayOfWeekIso;

            if (! array_key_exists($tgl, $tersimpan)
                && ! array_key_exists($tgl, $libur)
                && isset($template[$hari])) {
                $tersimpan[$tgl] = $template[$hari];
            }
        }

        ksort($tersimpan);

        return $tersimpan;
    }
}
