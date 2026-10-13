// Fingerspot / M50 (SBXPCDLL) HTTP Bridge
// ---------------------------------------------------------------------------
// Menjembatani mesin absensi yang memakai protokol biner SBXPCDLL (port 5005)
// menjadi API HTTP/JSON sederhana yang dapat dipanggil oleh server Laravel.
//
// Jalankan HANYA di Windows dan letakkan DLL vendor di folder yang sama:
//   SBXPCDLL64.dll, SBPCCOMM64.dll, GEN_FONT64.dll  (untuk proses 64-bit)
//   SBXPCDLL.dll,   SBPCCOMM.dll,   GEN_FONT.dll    (untuk proses 32-bit)
//
// Endpoint:
//   GET  /                 -> info service
//   GET  /health           -> { status: "success" }
//   POST /api/ping         -> { ip, port }                       -> cek TCP
//   POST /api/logs         -> { ip, port, password, machineNo }  -> tarik absen
//   POST /api/sysinfo      -> { ip, port, password, machineNo }  -> info mesin
//
// Lihat README.md untuk cara build & menjalankan.

using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Net;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading;

namespace FingerspotBridge
{
    internal static class Program
    {
        private static int _port = 8080;
        private static string _token = "";

        private static void Main(string[] args)
        {
            _port = EnvInt("BRIDGE_PORT", ArgInt(args, 0, 8080));
            _token = Environment.GetEnvironmentVariable("BRIDGE_TOKEN") ?? "";

            var listener = new HttpListener();
            listener.Prefixes.Add("http://+:" + _port + "/");

            try
            {
                listener.Start();
            }
            catch (Exception ex)
            {
                Console.WriteLine("[ERROR] Gagal membuka port " + _port + ": " + ex.Message);
                Console.WriteLine("        Jalankan sebagai Administrator, atau daftarkan URL ACL:");
                Console.WriteLine("        netsh http add urlacl url=http://+:" + _port + "/ user=Everyone");
                return;
            }

            Console.WriteLine("=================================================");
            Console.WriteLine(" Fingerspot / M50 SBXPCDLL Bridge");
            Console.WriteLine(" Listening : http://0.0.0.0:" + _port + "/");
            Console.WriteLine(" Token     : " + (_token == "" ? "(tidak diaktifkan)" : "aktif"));
            Console.WriteLine(" DLL dir   : " + AppDomain.CurrentDomain.BaseDirectory);
            Console.WriteLine("=================================================");

            while (true)
            {
                HttpListenerContext ctx;
                try
                {
                    ctx = listener.GetContext();
                }
                catch (Exception)
                {
                    break;
                }

                ThreadPool.QueueUserWorkItem(_ => Handle(ctx));
            }
        }

        private static void Handle(HttpListenerContext ctx)
        {
            try
            {
                string path = (ctx.Request.Url == null ? "/" : ctx.Request.Url.AbsolutePath).ToLowerInvariant();
                string method = ctx.Request.HttpMethod.ToUpperInvariant();

                if (!Authorized(ctx))
                {
                    Write(ctx, 401, "{\"status\":\"failed\",\"message\":\"Token tidak valid.\"}");
                    return;
                }

                if (method == "GET" && (path == "/" || path == "/health"))
                {
                    Write(ctx, 200, "{\"status\":\"success\",\"service\":\"fingerspot-bridge\",\"version\":\"1.0\"}");
                    return;
                }

                if (method == "POST" && path == "/api/ping")
                {
                    HandlePing(ctx);
                    return;
                }

                if (method == "POST" && path == "/api/logs")
                {
                    HandleLogs(ctx);
                    return;
                }

                if (method == "POST" && path == "/api/sysinfo")
                {
                    HandleSysInfo(ctx);
                    return;
                }

                Write(ctx, 404, "{\"status\":\"failed\",\"message\":\"Endpoint tidak ditemukan.\"}");
            }
            catch (Exception ex)
            {
                try { Write(ctx, 500, "{\"status\":\"failed\",\"message\":\"" + Esc(ex.Message) + "\"}"); }
                catch { }
            }
        }

        private static void HandlePing(HttpListenerContext ctx)
        {
            var body = ReadBody(ctx);
            string ip = Field(body, "ip");
            int port = FieldInt(body, "port", 5005);

            if (ip == "")
            {
                Write(ctx, 400, "{\"status\":\"failed\",\"action\":\"ping\",\"message\":\"Parameter ip wajib diisi.\"}");
                return;
            }

            bool ok = false;
            string message;
            var sw = System.Diagnostics.Stopwatch.StartNew();
            try
            {
                using (var client = new TcpClient())
                {
                    var res = client.BeginConnect(ip, port, null, null);
                    ok = res.AsyncWaitHandle.WaitOne(5000, false);
                    if (ok)
                    {
                        client.EndConnect(res);
                    }
                    else
                    {
                        message = "Koneksi timeout ke " + ip + ":" + port + ".";
                        sw.Stop();
                        Write(ctx, 200, PingJson(false, ip, port, sw.Elapsed.TotalMilliseconds, message));
                        return;
                    }
                }
                message = "Port " + port + " pada " + ip + " terbuka dan dapat dijangkau.";
            }
            catch (Exception ex)
            {
                message = "Gagal terhubung ke " + ip + ":" + port + " - " + ex.Message;
                ok = false;
            }
            sw.Stop();

            Write(ctx, 200, PingJson(ok, ip, port, sw.Elapsed.TotalMilliseconds, message));
        }

        private static void HandleLogs(HttpListenerContext ctx)
        {
            var body = ReadBody(ctx);
            string ip = Field(body, "ip");
            int port = FieldInt(body, "port", 5005);
            int password = FieldInt(body, "password", 0);
            int machineNo = FieldInt(body, "machineNo", 1);

            if (ip == "")
            {
                Write(ctx, 400, "{\"status\":\"failed\",\"action\":\"getlogs\",\"message\":\"Parameter ip wajib diisi.\"}");
                return;
            }

            Sbxpc.DotNET();
            if (!Sbxpc.ConnectTcpip(machineNo, ip, port, password))
            {
                Write(ctx, 200, "{\"status\":\"failed\",\"action\":\"getlogs\",\"message\":\"Gagal terhubung ke mesin " + Esc(ip) + ":" + port + " via SBXPCDLL.\"}");
                return;
            }

            try
            {
                Sbxpc.EnableDevice(machineNo, 0);
                var logs = new List<string>();
                if (Sbxpc.ReadAllGLogData(machineNo))
                {
                    int tmno, enroll, emach, vmode, yr, mon, day, hr, min, sec;
                    while (Sbxpc.GetAllGLogData(machineNo, out tmno, out enroll, out emach, out vmode,
                                                out yr, out mon, out day, out hr, out min, out sec))
                    {
                        string timestamp = string.Format(CultureInfo.InvariantCulture,
                            "{0:D4}-{1:D2}-{2:D2} {3:D2}:{4:D2}:{5:D2}", yr, mon, day, hr, min, sec);
                        logs.Add("{" +
                            "\"device_id\":" + tmno + "," +
                            "\"user_id\":" + enroll + "," +
                            "\"sensor_no\":" + emach + "," +
                            "\"verify_mode\":" + vmode + "," +
                            "\"verify_type\":\"" + VerifyName(vmode) + "\"," +
                            "\"timestamp\":\"" + timestamp + "\"" +
                        "}");
                    }
                }
                Sbxpc.EnableDevice(machineNo, 1);

                Write(ctx, 200, "{\"status\":\"success\",\"action\":\"getlogs\",\"count\":" + logs.Count +
                                ",\"logs\":[" + string.Join(",", logs.ToArray()) + "]}");
            }
            finally
            {
                Sbxpc.Disconnect(machineNo);
            }
        }

        private static void HandleSysInfo(HttpListenerContext ctx)
        {
            var body = ReadBody(ctx);
            string ip = Field(body, "ip");
            int port = FieldInt(body, "port", 5005);
            int password = FieldInt(body, "password", 0);
            int machineNo = FieldInt(body, "machineNo", 1);

            Sbxpc.DotNET();
            if (!Sbxpc.ConnectTcpip(machineNo, ip, port, password))
            {
                Write(ctx, 200, "{\"status\":\"failed\",\"action\":\"sysinfo\",\"message\":\"Gagal terhubung ke mesin " + Esc(ip) + ":" + port + ".\"}");
                return;
            }
            try
            {
                Write(ctx, 200, "{\"status\":\"success\",\"action\":\"sysinfo\",\"ip\":\"" + Esc(ip) + "\",\"port\":" + port + "}");
            }
            finally
            {
                Sbxpc.Disconnect(machineNo);
            }
        }

        // ── util ──────────────────────────────────────────────────────────

        private static string PingJson(bool ok, string ip, int port, double ms, string message)
        {
            return "{\"status\":\"" + (ok ? "success" : "failed") + "\"," +
                   "\"action\":\"ping\"," +
                   "\"ip\":\"" + Esc(ip) + "\"," +
                   "\"port\":" + port + "," +
                   "\"latency_ms\":" + Math.Round(ms, 2).ToString(CultureInfo.InvariantCulture) + "," +
                   "\"message\":\"" + Esc(message) + "\"}";
        }

        private static string VerifyName(int vmode)
        {
            switch (vmode)
            {
                case 1: return "Fingerprint";
                case 2: return "Password";
                case 3: return "Card";
                case 4: return "Face";
                case 5: return "FP+Card";
                default: return "Unknown";
            }
        }

        private static bool Authorized(HttpListenerContext ctx)
        {
            if (_token == "")
            {
                return true;
            }
            string given = ctx.Request.Headers["X-Bridge-Token"];
            if (string.IsNullOrEmpty(given))
            {
                string auth = ctx.Request.Headers["Authorization"];
                if (!string.IsNullOrEmpty(auth) && auth.StartsWith("Bearer ", StringComparison.OrdinalIgnoreCase))
                {
                    given = auth.Substring(7).Trim();
                }
            }
            return given == _token;
        }

        private static string ReadBody(HttpListenerContext ctx)
        {
            using (var reader = new StreamReader(ctx.Request.InputStream, ctx.Request.ContentEncoding))
            {
                return reader.ReadToEnd();
            }
        }

        private static string Field(string json, string name)
        {
            if (string.IsNullOrEmpty(json))
            {
                return "";
            }
            string key = "\"" + name + "\"";
            int i = json.IndexOf(key, StringComparison.OrdinalIgnoreCase);
            if (i < 0)
            {
                return "";
            }
            i = json.IndexOf(':', i + key.Length);
            if (i < 0)
            {
                return "";
            }
            i++;
            while (i < json.Length && char.IsWhiteSpace(json[i]))
            {
                i++;
            }
            if (i >= json.Length)
            {
                return "";
            }
            if (json[i] == '"')
            {
                i++;
                var sb = new StringBuilder();
                while (i < json.Length && json[i] != '"')
                {
                    if (json[i] == '\\' && i + 1 < json.Length)
                    {
                        i++;
                    }
                    sb.Append(json[i]);
                    i++;
                }
                return sb.ToString();
            }
            int start = i;
            while (i < json.Length && json[i] != ',' && json[i] != '}' && !char.IsWhiteSpace(json[i]))
            {
                i++;
            }
            return json.Substring(start, i - start);
        }

        private static int FieldInt(string json, string name, int fallback)
        {
            string v = Field(json, name);
            int n;
            return int.TryParse(v, NumberStyles.Integer, CultureInfo.InvariantCulture, out n) ? n : fallback;
        }

        private static void Write(HttpListenerContext ctx, int status, string json)
        {
            byte[] buf = Encoding.UTF8.GetBytes(json);
            ctx.Response.StatusCode = status;
            ctx.Response.ContentType = "application/json; charset=utf-8";
            ctx.Response.ContentLength64 = buf.Length;
            ctx.Response.OutputStream.Write(buf, 0, buf.Length);
            ctx.Response.OutputStream.Close();
        }

        private static string Esc(string s)
        {
            if (s == null)
            {
                return "";
            }
            var sb = new StringBuilder(s.Length + 8);
            foreach (char c in s)
            {
                switch (c)
                {
                    case '"': sb.Append("\\\""); break;
                    case '\\': sb.Append("\\\\"); break;
                    case '\n': sb.Append("\\n"); break;
                    case '\r': sb.Append("\\r"); break;
                    case '\t': sb.Append("\\t"); break;
                    default:
                        if (c < 0x20)
                        {
                            sb.Append("\\u").Append(((int)c).ToString("x4"));
                        }
                        else
                        {
                            sb.Append(c);
                        }
                        break;
                }
            }
            return sb.ToString();
        }

        private static int EnvInt(string name, int fallback)
        {
            int n;
            var v = Environment.GetEnvironmentVariable(name);
            return int.TryParse(v, out n) ? n : fallback;
        }

        private static int ArgInt(string[] args, int index, int fallback)
        {
            if (args != null && args.Length > index)
            {
                int n;
                if (int.TryParse(args[index], out n))
                {
                    return n;
                }
            }
            return fallback;
        }
    }

    // P/Invoke ke DLL vendor SBXPCDLL. Memilih DLL 64/32 bit saat runtime
    // (keduanya harus ada di folder aplikasi).
    internal static class Sbxpc
    {
        private const string Dll32 = "SBXPCDLL.dll";
        private const string Dll64 = "SBXPCDLL64.dll";
        private static readonly bool Is64 = Environment.Is64BitProcess;

        [DllImport(Dll32, EntryPoint = "_DotNET", CallingConvention = CallingConvention.Winapi)]
        private static extern void _DotNET_32();
        [DllImport(Dll64, EntryPoint = "_DotNET", CallingConvention = CallingConvention.Winapi)]
        private static extern void _DotNET_64();

        [DllImport(Dll32, EntryPoint = "_ConnectTcpip", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _ConnectTcpip_32(Int32 dwMachineNumber, ref IntPtr lpszIPAddress, Int32 dwPortNumber, Int32 dwPassWord);
        [DllImport(Dll64, EntryPoint = "_ConnectTcpip", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _ConnectTcpip_64(Int32 dwMachineNumber, ref IntPtr lpszIPAddress, Int32 dwPortNumber, Int32 dwPassWord);

        [DllImport(Dll32, EntryPoint = "_Disconnect", CallingConvention = CallingConvention.Winapi)]
        private static extern void _Disconnect_32(Int32 dwMachineNumber);
        [DllImport(Dll64, EntryPoint = "_Disconnect", CallingConvention = CallingConvention.Winapi)]
        private static extern void _Disconnect_64(Int32 dwMachineNumber);

        [DllImport(Dll32, EntryPoint = "_EnableDevice", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _EnableDevice_32(Int32 dwMachineNumber, byte bFlag);
        [DllImport(Dll64, EntryPoint = "_EnableDevice", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _EnableDevice_64(Int32 dwMachineNumber, byte bFlag);

        [DllImport(Dll32, EntryPoint = "_ReadAllGLogData", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _ReadAllGLogData_32(Int32 dwMachineNumber);
        [DllImport(Dll64, EntryPoint = "_ReadAllGLogData", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _ReadAllGLogData_64(Int32 dwMachineNumber);

        [DllImport(Dll32, EntryPoint = "_GetAllGLogData", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _GetAllGLogData_32(Int32 dwMachineNumber, IntPtr a, IntPtr b, IntPtr c, IntPtr d,
            IntPtr e, IntPtr f, IntPtr g, IntPtr h, IntPtr i, IntPtr j);
        [DllImport(Dll64, EntryPoint = "_GetAllGLogData", CallingConvention = CallingConvention.Winapi)]
        private static extern byte _GetAllGLogData_64(Int32 dwMachineNumber, IntPtr a, IntPtr b, IntPtr c, IntPtr d,
            IntPtr e, IntPtr f, IntPtr g, IntPtr h, IntPtr i, IntPtr j);

        public static void DotNET()
        {
            if (Is64) { _DotNET_64(); } else { _DotNET_32(); }
        }

        public static bool ConnectTcpip(Int32 machineNo, string ip, Int32 port, Int32 password)
        {
            IntPtr ptr = Marshal.StringToBSTR(ip);
            try
            {
                byte ret = Is64
                    ? _ConnectTcpip_64(machineNo, ref ptr, port, password)
                    : _ConnectTcpip_32(machineNo, ref ptr, port, password);
                return ret > 0;
            }
            catch
            {
                return false;
            }
            finally
            {
                Marshal.FreeBSTR(ptr);
            }
        }

        public static void Disconnect(Int32 machineNo)
        {
            if (Is64) { _Disconnect_64(machineNo); } else { _Disconnect_32(machineNo); }
        }

        public static bool EnableDevice(Int32 machineNo, byte flag)
        {
            byte ret = Is64 ? _EnableDevice_64(machineNo, flag) : _EnableDevice_32(machineNo, flag);
            return ret > 0;
        }

        public static bool ReadAllGLogData(Int32 machineNo)
        {
            byte ret = Is64 ? _ReadAllGLogData_64(machineNo) : _ReadAllGLogData_32(machineNo);
            return ret > 0;
        }

        public static bool GetAllGLogData(Int32 machineNo, out Int32 tm, out Int32 enroll, out Int32 emach,
            out Int32 vmode, out Int32 yr, out Int32 mon, out Int32 day, out Int32 hr, out Int32 min, out Int32 sec)
        {
            tm = enroll = emach = vmode = yr = mon = day = hr = min = sec = 0;

            var handles = new GCHandle[10];
            var buffers = new byte[10][];
            for (int k = 0; k < 10; k++)
            {
                buffers[k] = new byte[4];
                handles[k] = GCHandle.Alloc(buffers[k], GCHandleType.Pinned);
            }

            try
            {
                byte ret = Is64
                    ? _GetAllGLogData_64(machineNo,
                        handles[0].AddrOfPinnedObject(),
                        handles[1].AddrOfPinnedObject(),
                        handles[2].AddrOfPinnedObject(),
                        handles[3].AddrOfPinnedObject(),
                        handles[4].AddrOfPinnedObject(),
                        handles[5].AddrOfPinnedObject(),
                        handles[6].AddrOfPinnedObject(),
                        handles[7].AddrOfPinnedObject(),
                        handles[8].AddrOfPinnedObject(),
                        handles[9].AddrOfPinnedObject())
                    : _GetAllGLogData_32(machineNo,
                        handles[0].AddrOfPinnedObject(),
                        handles[1].AddrOfPinnedObject(),
                        handles[2].AddrOfPinnedObject(),
                        handles[3].AddrOfPinnedObject(),
                        handles[4].AddrOfPinnedObject(),
                        handles[5].AddrOfPinnedObject(),
                        handles[6].AddrOfPinnedObject(),
                        handles[7].AddrOfPinnedObject(),
                        handles[8].AddrOfPinnedObject(),
                        handles[9].AddrOfPinnedObject());

                if (ret == 0)
                {
                    return false;
                }

                tm = BitConverter.ToInt32(buffers[0], 0);
                enroll = BitConverter.ToInt32(buffers[1], 0);
                emach = BitConverter.ToInt32(buffers[2], 0);
                vmode = BitConverter.ToInt32(buffers[3], 0);
                yr = BitConverter.ToInt32(buffers[4], 0);
                mon = BitConverter.ToInt32(buffers[5], 0);
                day = BitConverter.ToInt32(buffers[6], 0);
                hr = BitConverter.ToInt32(buffers[7], 0);
                min = BitConverter.ToInt32(buffers[8], 0);
                sec = BitConverter.ToInt32(buffers[9], 0);
                return true;
            }
            finally
            {
                for (int k = 0; k < 10; k++)
                {
                    handles[k].Free();
                }
            }
        }
    }
}
