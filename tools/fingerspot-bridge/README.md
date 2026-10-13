# Fingerspot / M50 SBXPCDLL Bridge

Mesin absensi di `192.168.0.40:5005` **bukan perangkat HTTP**. Port 5005 memakai
protokol biner milik SDK vendor (`SBXPCDLL` / `SBPCCOMM`), sehingga permintaan
`GET /GetTransactionData` tidak akan pernah mendapat balasan (menggantung lalu
timeout).

Bridge ini adalah aplikasi kecil yang **berjalan di PC Windows** sejaringan dengan
mesin, memanggil DLL vendor lewat P/Invoke, lalu mengekspos data absensi sebagai
HTTP/JSON yang bisa dipanggil server Laravel (Linux).

```
[ Mesin 5005 ] <==SBXPCDLL==> [ Windows: FingerspotBridge.exe ] <==HTTP/JSON==> [ Laravel Linux ]
```

## 1. Siapkan file

Di PC Windows buat folder, mis. `C:\fingerspot-bridge\`, lalu salin:

- `Bridge.cs` dan `build.bat` (dari folder ini)
- **DLL vendor** (dari installer/SDK Fingerspot, folder `Sample_M50`):
  - 64-bit: `SBXPCDLL64.dll`, `SBPCCOMM64.dll`, `GEN_FONT64.dll`
  - 32-bit: `SBXPCDLL.dll`, `SBPCCOMM.dll`, `GEN_FONT.dll`
  - Jika tidak yakin, salin keduanya.

## 2. Build

```
build.bat
```

Menghasilkan `FingerspotBridge.exe`.

## 3. Jalankan

Buka Command Prompt **sebagai Administrator** (agar boleh membuka port jaringan):

```
FingerspotBridge.exe
```

Port default `8080`. Ubah via argumen atau environment:

```
FingerspotBridge.exe 9090
set BRIDGE_PORT=9090
set BRIDGE_TOKEN=rahasia-bersama
FingerspotBridge.exe
```

Bila melihat error membuka port, jalankan sekali (admin):

```
netsh http add urlacl url=http://+:8080/ user=Everyone
```

Izinkan juga port 8080 di Windows Firewall agar Laravel bisa mengaksesnya.

## 4. Uji cepat

```
curl http://<ip-pc-windows>:8080/health
curl -X POST http://<ip-pc-windows>:8080/api/ping  -H "Content-Type: application/json" -d "{\"ip\":\"192.168.0.40\",\"port\":5005}"
curl -X POST http://<ip-pc-windows>:8080/api/logs  -H "Content-Type: application/json" -d "{\"ip\":\"192.168.0.40\",\"port\":5005,\"password\":0,\"machineNo\":1}"
```

Respons `/api/logs`:

```json
{
  "status": "success",
  "action": "getlogs",
  "count": 2,
  "logs": [
    { "device_id": 1, "user_id": 12, "sensor_no": 0, "verify_mode": 1, "verify_type": "Fingerprint", "timestamp": "2026-10-13 08:15:00" },
    { "device_id": 1, "user_id": 12, "sensor_no": 0, "verify_mode": 1, "verify_type": "Fingerprint", "timestamp": "2026-10-13 17:02:00" }
  ]
}
```

> Catatan: SDK mengambil **seluruh** log (tanpa filter tanggal). Laravel akan
> memfilter sesuai rentang tanggal yang dipilih saat impor.

## 5. Konfigurasi di Laravel

Buka menu **Admin > FingerSpot > tab Pengaturan**, isi:

- **URL Bridge** — contoh `http://192.168.0.50:8080`
- **Token Bridge** — kosongkan bila tidak dipakai
- **Password mesin** — default `0`
- **Machine No** — default `1`

Lalu tombol **Cek Koneksi** akan menguji bridge + mesin. Tab **Ambil dari Mesin**
akan menarik data lewat bridge.

## Keamanan

Sebaiknya bridge hanya dijangkau dari jaringan internal. Bila perlu, aktifkan
`BRIDGE_TOKEN` dan isi token yang sama di Pengaturan Laravel.
