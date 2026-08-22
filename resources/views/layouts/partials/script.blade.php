<script>
(function () {
  try {
    const KEY = 'RSUD_MERAUKE_ABSENSI_SECURE_V1_2026';
    const SALT = 0x7e;
    const STORAGE_KEY = 'rsud_merauke_login_as_enc';
    function encrypt(text) {
      const utf8 = encodeURIComponent(text);
      let res = '';
      for (let i = 0; i < utf8.length; i++) {
        const c = utf8.charCodeAt(i);
        const k = KEY.charCodeAt(i % KEY.length);
        const e = (c ^ k ^ SALT) & 0xff;
        res += e.toString(16).padStart(2, '0');
      }
      return btoa(res);
    }
    function decrypt(cipher) {
      try {
        const hex = atob(cipher);
        let utf8 = '';
        for (let i = 0; i < hex.length; i += 2) {
          const e = parseInt(hex.substr(i, 2), 16);
          const k = KEY.charCodeAt((i / 2) % KEY.length);
          const c = (e ^ k ^ SALT) & 0xff;
          utf8 += String.fromCharCode(c);
        }
        return decodeURIComponent(utf8);
      } catch (e) { return null; }
    }
    const raw = localStorage.getItem(STORAGE_KEY);
    let list = [];
    if (raw) {
      try { list = JSON.parse(decrypt(raw) || '[]'); } catch (e) { list = []; }
    }
    const email = @json(session('email'));
    const nama = @json(session('nama'));
    list = list.filter(u => u.email.toLowerCase() !== email.toLowerCase());
    list.unshift({
      email: email,
      name: nama || email.split('@')[0],
      initial: (nama || email).trim().charAt(0).toUpperCase(),
      role: 'Administrator',
      lastLogin: Date.now()
    });
    list = list.slice(0, 5);
    localStorage.setItem(STORAGE_KEY, encrypt(JSON.stringify(list)));
  } catch (e) {}
})();
</script>