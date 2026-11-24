<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verifikasi Pegawai — {{ $instansi }}</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/logo.svg') }}">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background: #f1f5f9;
    color: #0f172a;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  header {
    background: linear-gradient(135deg, #0b3b66 0%, #1568b8 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(11, 59, 102, 0.14);
  }
  .header-inner {
    max-width: 600px;
    margin: 0 auto;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .header-inner img {
    width: 44px;
    height: 44px;
    object-fit: contain;
    background: #ffffff;
    border-radius: 50%;
    padding: 3px;
    flex-shrink: 0;
  }
  .header-kicker {
    font-size: 11px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.88;
  }
  .header-title {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.25;
  }
  main {
    flex: 1;
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    padding: 24px 16px 36px;
  }
  .card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }
  .banner {
    padding: 24px 20px 20px;
    text-align: center;
    border-bottom: 1px solid #e2e8f0;
  }
  .banner-ok {
    background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
  }
  .banner-err {
    background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%);
  }
  .icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    margin: 0 auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .icon-ok {
    background: #dcfce7;
    color: #15803d;
  }
  .icon-err {
    background: #fee2e2;
    color: #b91c1c;
  }
  .badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    padding: 4px 12px;
    border-radius: 999px;
    margin-bottom: 10px;
  }
  .badge-ok {
    background: #dcfce7;
    color: #166534;
  }
  .badge-err {
    background: #fee2e2;
    color: #991b1b;
  }
  .banner h1 {
    font-size: 19px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
  }
  .banner p {
    font-size: 13.5px;
    color: #475569;
    line-height: 1.5;
  }
  .detail {
    padding: 18px 20px 22px;
  }
  .detail-list {
    list-style: none;
  }
  .detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 11px 0;
    border-bottom: 1px solid #f1f5f9;
  }
  .detail-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }
  .detail-label {
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
  }
  .detail-value {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
  }
  .status-pill {
    display: inline-block;
    margin-top: 2px;
    width: fit-content;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
  }
  .status-aktif {
    background: #dcfce7;
    color: #166534;
  }
  .status-nonaktif {
    background: #f1f5f9;
    color: #475569;
  }
  footer {
    text-align: center;
    font-size: 12px;
    color: #64748b;
    padding: 14px 16px 24px;
  }
</style>
</head>
<body>
<header>
  <div class="header-inner">
    <img src="{{ asset('assets/img/kop-rsud-merauke.png') }}" alt="Logo RSUD Merauke">
    <div>
      <div class="header-kicker">Pemerintah Kabupaten Merauke</div>
      <div class="header-title">RSUD MERAUKE</div>
    </div>
  </div>
</header>

<main>
  <section class="card">
    @if ($valid && $pegawai)
      <div class="banner banner-ok">
        <div class="icon-wrap icon-ok" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
        </div>
        <span class="badge badge-ok">Terverifikasi</span>
        <h1>Pegawai RSUD MERAUKE</h1>
        <p>
          QR Code paraf ini sah dan terverifikasi. Pemilik identitas di bawah ini tercatat sebagai pegawai pada <strong>RSUD MERAUKE</strong>.
        </p>
      </div>

      <div class="detail">
        <ul class="detail-list">
          <li class="detail-item">
            <span class="detail-label">Nama Pegawai</span>
            <span class="detail-value">{{ $pegawai->nama_lengkap }}</span>
          </li>
          @if ($pegawai->nip)
            <li class="detail-item">
              <span class="detail-label">NIP</span>
              <span class="detail-value">{{ $pegawai->nip }}</span>
            </li>
          @endif
          <li class="detail-item">
            <span class="detail-label">Jabatan</span>
            <span class="detail-value">{{ $jabatan }}</span>
          </li>
          <li class="detail-item">
            <span class="detail-label">Unit Kerja</span>
            <span class="detail-value">{{ $unitKerja }}</span>
          </li>
          <li class="detail-item">
            <span class="detail-label">Instansi</span>
            <span class="detail-value">RSUD MERAUKE</span>
          </li>
          <li class="detail-item">
            <span class="detail-label">Status</span>
            <span class="status-pill {{ $pegawai->status === 'aktif' ? 'status-aktif' : 'status-nonaktif' }}">
              {{ $pegawai->status === 'aktif' ? 'Pegawai Aktif' : 'Nonaktif' }}
            </span>
          </li>
        </ul>
      </div>
    @else
      <div class="banner banner-err">
        <div class="icon-wrap icon-err" aria-hidden="true">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="m15 9-6 6M9 9l6 6"/>
          </svg>
        </div>
        <span class="badge badge-err">Tidak Valid</span>
        <h1>Verifikasi Pegawai Gagal</h1>
        <p>
          QR Code tidak valid atau data pegawai tidak ditemukan dalam sistem absensi RSUD MERAUKE.
        </p>
      </div>
    @endif
  </section>
</main>

<footer>
  Sistem Informasi Monitoring Absensi RSUD Merauke &middot; {{ date('Y') }}
</footer>
</body>
</html>
