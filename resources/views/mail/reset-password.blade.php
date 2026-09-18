<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#eef2f7;padding:24px 12px;">
    <tr>
      <td align="center">
        <table width="620" cellpadding="0" cellspacing="0" style="width:100%;max-width:620px;background-color:#ffffff;border:1px solid #dde3ec;">
          <tr>
            <td>
              {{-- ============ KOP  ============ --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #0b3b66;">
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
                    <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.8;color:#334155;margin:0 0 12px;">
                      Kami menerima permintaan untuk mereset password akun Anda pada
                      <strong>{{ $appName }}</strong>. Klik tombol di bawah ini untuk membuat password baru:
                    </p>

                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td align="center" style="padding:18px 0 22px;">
                          <table cellpadding="0" cellspacing="0" border="0">
                            <tr>
                              <td bgcolor="#0b3b66" style="border-radius:4px;">
                                <a href="{{ $url }}" target="_blank"
                                   style="display:inline-block;padding:12px 26px;font-family:Arial,Helvetica,sans-serif;font-size:12.5px;font-weight:bold;color:#ffffff;text-decoration:none;">
                                  RESET PASSWORD
                                </a>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                    </table>

                    <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.8;color:#334155;margin:0 0 10px;">
                      Tautan berlaku selama <strong>{{ $masaBerlakuMenit }} menit</strong> dan hanya dapat digunakan
                      sekali. Bila tombol di atas tidak berfungsi, salin alamat berikut ke browser Anda:<br>
                      <span style="color:#0b3b66;word-break:break-all;">{{ $url }}</span>
                    </p>
                    <p style="font-family:Arial,Helvetica,sans-serif;font-size:11.5px;line-height:1.8;color:#334155;margin:0;">
                      Jika Anda tidak merasa meminta reset password, abaikan email ini — password Anda tidak akan berubah.
                    </p>
                  </td>
                </tr>
              </table>

              {{-- ============ FOOTER ============ --}}
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;border-top:1px solid #e2e8f0;">
                <tr>
                  <td align="center" style="padding:12px 16px;">
                    <span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#64748b;">
                      {{ $appName }} &copy; {{ $tahun }} • Email dibuat otomatis, jangan membalas email ini.
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