<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aktivitas Mencurigakan — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background-color:#ffffff;border:1px solid #dde3ec;">
          <tr>
            <td>
              {{-- ============ KOP  ============ --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #b91c1c;">
                <tr>
                  <td width="64" align="center" valign="middle" style="padding:18px 4px 16px 22px;">
                    <img src="{{ asset('assets/img/logo.svg') }}" width="48" height="48" alt="Logo {{ $appName }}"
                         style="display:block;border:0;outline:none;">
                  </td>
                  <td align="left" valign="middle" style="padding:18px 22px 16px 4px;">
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.25;font-weight:bold;color:#0b3b66;">
                      {{ $appName }}
                    </div>
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.4;color:#64748b;">
                      {{ url('/') }}
                    </div>
                  </td>
                </tr>
              </table>

              {{-- ============ ISI ============ --}}
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:24px 28px 26px;">
                    <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.7;color:#334155;margin:0 0 12px;">
                      Halo <strong>{{ $nama }}</strong>,
                    </p>

                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #fecaca;background-color:#fef2f2;margin:0 0 14px;">
                      <tr>
                        <td style="padding:12px 14px;">
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:12.5px;line-height:1.5;font-weight:bold;color:#b91c1c;">
                            Percobaan login lebih dari 5 kali terdeteksi
                          </div>
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.7;color:#7f1d1d;margin-top:4px;">
                            Akun <strong>{{ $email }}</strong> gagal masuk sebanyak
                            <strong>{{ $jumlahGagal }}×</strong> dalam {{ $jendelaJam }} jam terakhir
                            dan sistem mencurigai percobaan masuk dari pihak lain.
                          </div>
                        </td>
                      </tr>
                    </table>

                    {{-- RINGKASAN --}}
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;margin:0 0 16px;">
                      <tr>
                        <td width="33%" align="center" style="padding:10px 4px;border-right:1px solid #e2e8f0;">
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#0b3b66;">{{ $jumlahGagal }}×</div>
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:10.5px;color:#64748b;">Percobaan gagal</div>
                        </td>
                        <td width="33%" align="center" style="padding:10px 4px;border-right:1px solid #e2e8f0;">
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#0b3b66;">{{ $jumlahIp }}</div>
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:10.5px;color:#64748b;">Alamat IP</div>
                        </td>
                        <td width="33%" align="center" style="padding:10px 4px;">
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;color:#0b3b66;">{{ $jumlahPerangkat }}</div>
                          <div style="font-family:Arial,Helvetica,sans-serif;font-size:10.5px;color:#64748b;">Perangkat</div>
                        </td>
                      </tr>
                    </table>

                    {{-- IP --}}
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#0f172a;margin:0 0 6px;">
                      Alamat IP yang dipakai
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;margin:0 0 16px;">
                      @forelse($daftarIp as $baris)
                        <tr>
                          <td style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11.5px;color:#334155;border-bottom:1px solid #f1f5f9;">
                            {{ $baris['ip'] }}
                          </td>
                          <td align="right" style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                            {{ $baris['jumlah'] }}× percobaan
                          </td>
                        </tr>
                      @empty
                        <tr><td style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11.5px;color:#64748b;">—</td></tr>
                      @endforelse
                    </table>

                    {{-- PERANGKAT --}}
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#0f172a;margin:0 0 6px;">
                      Perangkat yang dipakai
                    </div>
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;margin:0 0 16px;">
                      @forelse($daftarPerangkat as $baris)
                        <tr>
                          <td style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11.5px;color:#334155;border-bottom:1px solid #f1f5f9;">
                            <strong>{{ $baris['nama'] }}</strong>
                            @if($baris['contoh'])
                              <div style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#94a3b8;word-break:break-all;">{{ $baris['contoh'] }}</div>
                            @endif
                          </td>
                          <td align="right" style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                            {{ $baris['jumlah'] }}× percobaan
                          </td>
                        </tr>
                      @empty
                        <tr><td style="padding:7px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11.5px;color:#64748b;">—</td></tr>
                      @endforelse
                    </table>

                    {{-- RINCIAN PERCOBAAN TERAKHIR --}}
                    @if($percobaan)
                      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#0f172a;margin:0 0 6px;">
                        Percobaan terakhir
                      </div>
                      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;margin:0 0 16px;">
                        @foreach($percobaan as $baris)
                          <tr>
                            <td width="90" style="padding:6px 12px;font-family:Arial,Helvetica,sans-serif;font-size:10.5px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                              {{ $baris['waktu'] }}
                            </td>
                            <td style="padding:6px 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#334155;border-bottom:1px solid #f1f5f9;word-break:break-all;">
                              {{ $baris['ip'] }}
                            </td>
                            <td align="right" style="padding:6px 12px;font-family:Arial,Helvetica,sans-serif;font-size:10.5px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap;">
                              {{ $baris['perangkat'] }}{{ $baris['sumber'] !== '' ? ' • '.$baris['sumber'] : '' }}
                            </td>
                          </tr>
                        @endforeach
                      </table>
                    @endif

                    {{-- SARAN --}}
                    <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;color:#0f172a;margin:0 0 6px;">
                      Apa yang sebaiknya Anda lakukan
                    </div>
                    <ul style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.8;color:#334155;margin:0 0 12px;padding-left:18px;">
                      <li>Ubah password Anda dari menu <em>Lupa Password</em> bila percobaan ini bukan Anda.</li>
                      <li>Pastikan tidak ada orang lain yang mengetahui password Anda.</li>
                      <li>Bila perangkat di atas tidak pernah Anda gunakan, hubungi administrator {{ $appName }}.</li>
                    </ul>

                    <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.8;color:#334155;margin:0;">
                      Demi keamanan, email ini tidak meminta Anda membalas atau memasukkan password
                      ke dalam pesan mana pun.
                    </p>
                  </td>
                </tr>
              </table>

              {{-- ============ FOOTER ============ --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;border-top:1px solid #e2e8f0;">
                <tr>
                  <td align="center" style="padding:12px 16px;">
                    <span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#64748b;">
                      {{ $appName }} &copy; {{ $tahun }} • Nomor&nbsp;{{ $nomor }} • Email dibuat otomatis, jangan membalas email ini.
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>