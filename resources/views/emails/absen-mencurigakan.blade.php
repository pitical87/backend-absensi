<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>{{ $subjekEmail ?? 'Pemberitahuan absensi' }}</title>
</head>
<body>
  <p>Yth. {{ $namaPegawai }},</p>
  <div>{!! nl2br(e($isiPesan)) !!}</div>
  <p>Terima kasih.</p>
</body>
</html>
