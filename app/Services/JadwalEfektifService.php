<?php

namespace App\Services;

use App\Models\HariLibur;
use App\Models\JadwalShift;
use App\Models\JadwalTemplateMingguan;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;

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
        $daftar = collect($users)->keyBy('id');
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
        $daftar = collect($users)->keyBy('id');
        if ($daftar->isEmpty()) {
            return [];
        }

        $peta = [];
        $tersimpan = JadwalShift::whereIn('user_id', $daftar->keys())
            ->whereBetween('tanggal_berlaku', [$mulai, $sampai])
            ->get(['user_id', 'tanggal_berlaku', 'shift_id']);
        foreach ($tersimpan as $jadwal) {
            $peta[(int) $jadwal->user_id][Carbon::parse($jadwal->tanggal_berlaku)->toDateString()]
                = (int) $jadwal->shift_id;
        }

        $subUnitIds = $daftar->pluck('sub_unit_id')->filter()->unique()->values();
        $template = $subUnitIds->isNotEmpty()
            ? JadwalTemplateMingguan::whereIn('sub_unit_id', $subUnitIds)
                ->get(['sub_unit_id', 'hari', 'shift_id'])
                ->groupBy('sub_unit_id')
                ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [(int) $row->hari => (int) $row->shift_id])->all())
                ->all()
            : [];
        if (! $template) {
            return $peta;
        }

        $libur = HariLibur::whereBetween('tanggal', [$mulai, $sampai])
            ->pluck('tanggal')
            ->map(fn ($tanggal) => Carbon::parse($tanggal)->toDateString())
            ->flip()
            ->all();

        for ($tanggal = Carbon::parse($mulai); $tanggal->lte(Carbon::parse($sampai)); $tanggal->addDay()) {
            $tgl = $tanggal->toDateString();
            if (isset($libur[$tgl])) {
                continue;
            }
            $hari = $tanggal->dayOfWeekIso;

            foreach ($daftar as $user) {
                $userId = (int) $user->id;
                if (! array_key_exists($tgl, $peta[$userId] ?? [])
                    && isset($template[$user->sub_unit_id][$hari])) {
                    $peta[$userId][$tgl] = $template[$user->sub_unit_id][$hari];
                }
            }
        }

        foreach ($peta as &$jadwalPegawai) {
            ksort($jadwalPegawai);
        }
        unset($jadwalPegawai);

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
