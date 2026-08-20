<?php

namespace App\Http\Requests;

use App\Http\Controllers\Concerns\HasPenggunaAktif;
use App\Http\Controllers\IzinController;
use App\Models\HariLibur;
use App\Models\Izin;
use App\Services\CutiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AjukanIzinRequest extends FormRequest
{
    use HasPenggunaAktif;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jenis'           => ['required', 'string', Rule::in(IzinController::JENIS)],
            'jenis_cuti'      => ['nullable', 'string', Rule::in(jenis_cuti_list())],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'alamat_izin'     => ['nullable', 'string', 'max:255'],
            'keterangan'      => ['required', 'string', 'max:1000'],
            'lampiran'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $jenis     = (string) $this->input('jenis');
            $mulai     = (string) $this->input('tanggal_mulai');
            $selesai   = (string) $this->input('tanggal_selesai') ?: $mulai;
            $alamat    = trim((string) $this->input('alamat_izin')) ?: null;
            $jenisCuti = trim((string) $this->input('jenis_cuti')) ?: null;
            $userObj   = (object) $this->penggunaAktif();

            if ($jenis === 'Cuti' && $jenisCuti === null) {
                $validator->errors()->add('jenis_cuti', 'Jenis cuti wajib dipilih.');
            }

            if ($jenis === 'Izin' && $alamat === null) {
                $validator->errors()->add('alamat_izin', 'Alamat selama izin wajib diisi.');
            }

            if ($jenis === 'Cuti' && $alamat === null) {
                $validator->errors()->add('alamat_izin', 'Alamat selama cuti wajib diisi.');
            }

            if ($validator->errors()->isEmpty() && (strtotime($selesai) - strtotime($mulai)) / 86400 > 60) {
                $validator->errors()->add('tanggal_selesai', 'Rentang pengajuan maksimal 60 hari.');
            }

            if ($jenis === 'Cuti' && ! is_pns($userObj)) {
                $validator->errors()->add('jenis',
                    'Cuti hanya dapat diajukan oleh pegawai berstatus PNS. '
                    . 'Gunakan jenis "Izin" untuk keperluan non-cuti.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $userId = (int) ($this->session()->get('uid') ?? 0);

            $tindih = Izin::where('user_id', $userId)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->where('tanggal_mulai', '<=', $selesai)
                ->where('tanggal_selesai', '>=', $mulai)
                ->exists();
            if ($tindih) {
                $validator->errors()->add('tanggal_mulai',
                    'Rentang tanggal tersebut bertumpang-tindih dengan pengajuan lain yang masih Menunggu/Disetujui.');

                return;
            }

            if (in_array($jenis, IzinController::BERJENJANG, true)) {
                pastikan_libur_tetap((int) date('Y', strtotime($mulai)));
                pastikan_libur_tetap((int) date('Y', strtotime($selesai)));

                $liburSet = [];
                foreach (HariLibur::all() as $h) {
                    $liburSet[$h->tanggal->format('Y-m-d')] = true;
                }
                $mingguLibur = pengaturan('minggu_libur', '0') === '1';
                $lamaHari = hari_kerja_antara($mulai, $selesai, $liburSet, $mingguLibur);
                if ($lamaHari < 1) $lamaHari = 1;
                $this->merge(['lama_hari' => $lamaHari]);

                $motongKuota = $jenis === 'Izin' || ($jenis === 'Cuti' && $jenisCuti === 'Cuti Tahunan');
                if ($motongKuota && is_pns($userObj)) {
                    $sisa = app(CutiService::class)->rekap($userId, (int) date('Y', strtotime($mulai)))['sisa'];
                    if ($lamaHari > $sisa) {
                        $validator->errors()->add('tanggal_mulai',
                            "Sisa hak cuti tahun ini hanya {$sisa} hari kerja, "
                            . "sedangkan pengajuan ini memerlukan {$lamaHari} hari kerja.");
                    }
                }
            }
        });
    }
}