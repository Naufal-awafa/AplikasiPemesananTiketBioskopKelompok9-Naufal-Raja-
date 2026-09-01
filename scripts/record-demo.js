const { spawn, spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'demo-sineverse.mp4');
const FRAMES = path.join(ROOT, 'storage', 'demo-frames');
const BASE = 'http://localhost/aplikasiPemesananTiketBioskop/';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const PROFILE = path.join(ROOT, 'storage', 'chrome-demo-profile-recording');
const DEBUG_PORT = 9555;
const FPS = 1;

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
fs.mkdirSync(FRAMES, { recursive: true });
for (const f of fs.readdirSync(FRAMES)) if (f.endsWith('.jpg')) fs.unlinkSync(path.join(FRAMES, f));
fs.mkdirSync(PROFILE, { recursive: true });

const chrome = spawn(CHROME, [
  '--headless=new', '--disable-gpu', '--hide-scrollbars', '--mute-audio',
  `--remote-debugging-port=${DEBUG_PORT}`, '--no-first-run', '--no-default-browser-check', `--user-data-dir=${PROFILE}`,
  '--window-size=1440,900', '--force-device-scale-factor=1', BASE + 'index.php'
], { stdio: 'ignore' });

let ws;
let seq = 0;
const pending = new Map();
function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++seq;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`CDP timeout: ${method}`)); }, 7000);
    pending.set(id, { resolve, reject, timer });
    ws.send(JSON.stringify({ id, method, params }));
  });
}
async function connect() {
  let target;
  for (let i = 0; i < 80; i++) {
    try {
      const list = await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/list`).then(r => r.json());
      target = list.find(x => x.type === 'page');
      if (target) break;
    } catch (_) {}
    await sleep(250);
  }
  if (!target) throw new Error('Chrome DevTools tidak dapat dihubungi.');
  ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
  ws.onmessage = event => {
    const msg = JSON.parse(event.data);
    if (!msg.id || !pending.has(msg.id)) return;
    const p = pending.get(msg.id); pending.delete(msg.id); clearTimeout(p.timer);
    msg.error ? p.reject(new Error(msg.error.message)) : p.resolve(msg.result);
  };
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
}
async function evaluate(expression) {
  const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (r.exceptionDetails) throw new Error(r.exceptionDetails.text || 'JavaScript halaman gagal');
  return r.result.value;
}
async function go(url, wait = 1800) {
  await send('Page.navigate', { url: BASE + url });
  await sleep(wait);
}
async function caption(title, subtitle = '') {
  await evaluate(`(() => {
    document.getElementById('__demo_caption')?.remove();
    const el=document.createElement('div'); el.id='__demo_caption';
    el.style.cssText='position:fixed;left:32px;bottom:28px;z-index:2147483647;max-width:760px;padding:15px 20px;border-radius:14px;background:rgba(7,10,24,.88);border:1px solid rgba(139,92,246,.6);box-shadow:0 16px 45px rgba(0,0,0,.45);font-family:Segoe UI,Arial;color:white;pointer-events:none';
    el.innerHTML='<div style="font-size:22px;font-weight:800">'+${JSON.stringify(title)}+'</div><div style="margin-top:4px;color:#cbd5e1;font-size:14px">'+${JSON.stringify(subtitle)}+'</div>';
    document.body.appendChild(el);
  })()`);
}
async function titleCard() {
  await evaluate(`(() => { const el=document.createElement('div');el.id='__title_card';el.style.cssText='position:fixed;inset:0;z-index:2147483647;background:radial-gradient(circle at 20% 10%,#6d28d9 0,#11152b 38%,#050711 100%);display:grid;place-items:center;font-family:Segoe UI,Arial;color:white;text-align:center';el.innerHTML='<div><div style="font-size:20px;letter-spacing:7px;color:#c4b5fd">VIDEO DEMO PROGRAM</div><h1 style="font-size:68px;margin:18px 0 10px">SINEVERSE CINEMA</h1><p style="font-size:24px;color:#cbd5e1">Sistem Pemesanan Tiket Bioskop Berbasis Web</p><div style="margin-top:42px;padding:10px 18px;border:1px solid #8b5cf6;border-radius:999px;display:inline-block">PHP Native · MySQL · OOP</div></div>';document.body.appendChild(el)})()`);
  await sleep(3200);
  await evaluate(`document.getElementById('__title_card')?.remove()`);
}
async function scrollTo(y, ms = 1600) {
  await evaluate(`new Promise(r=>{const s=scrollY,d=${y}-s,t=performance.now();function a(n){const p=Math.min(1,(n-t)/${ms});scrollTo(0,s+d*(p<.5?2*p*p:1-Math.pow(-2*p+2,2)/2));p<1?requestAnimationFrame(a):r()}requestAnimationFrame(a)})`);
}
async function clickByText(text) {
  return evaluate(`(() => { const e=[...document.querySelectorAll('a,button')].find(x=>x.textContent.includes(${JSON.stringify(text)})); if(!e)return false;e.click();return true })()`);
}
async function login(email) {
  await go('logout.php', 500);
  await go('login.php');
  await caption('Autentikasi Pengguna', `Masuk sebagai ${email.split('@')[0]}`);
  await evaluate(`(() => {document.querySelector('[name=email]').value=${JSON.stringify(email)};document.querySelector('[name=password]').value='password123';})()`);
  await sleep(1000);
  await evaluate(`(() => {setTimeout(()=>document.querySelector('form').requestSubmit(),80);return true})()`);
  await sleep(2200);
}

let recording = true, frame = 0;
async function captureLoop() {
  while (recording) {
    try {
      const shot = await send('Page.captureScreenshot', { format: 'jpeg', quality: 84, fromSurface: true });
      fs.writeFileSync(path.join(FRAMES, `frame-${String(frame++).padStart(6, '0')}.jpg`), Buffer.from(shot.data, 'base64'));
    } catch (_) {}
    await sleep(1000 / FPS);
  }
}

(async () => {
  try {
    await connect();
    const capturing = captureLoop();
    await titleCard();
    await caption('Beranda Sineverse', 'Pencarian film, kategori tayang, dan katalog responsif');
    await sleep(2200); await scrollTo(650); await sleep(1800);
    await evaluate(`document.querySelector('a[href*="film-detail.php?id="]')?.click()`); await sleep(2200);
    await caption('Detail Film & Jadwal', 'Sinopsis, rating, harga, studio, serta pilihan jam tayang');
    await sleep(2500); await scrollTo(520); await sleep(1600);

    await login('customer@sineverse.id');
    await caption('Dashboard Customer', 'Akun customer siap melakukan pemesanan');
    await sleep(1800);
    await go('film-detail.php?id=15');
    await caption('Pilih Jadwal Tayang', 'Customer menentukan studio dan waktu menonton');
    await scrollTo(520, 1200); await sleep(1300);
    const booked = await evaluate(`(() => {const a=document.querySelector('a[href*="booking-kursi.php"]');if(!a)return false;a.click();return true})()`);
    if (!booked) throw new Error('Tidak ada jadwal yang dapat dipesan.');
    await sleep(2200);
    await caption('Pemilihan Kursi Interaktif', 'Kursi tersedia, terpilih, VIP, dan yang sudah terisi dibedakan secara visual');
    await sleep(1800);
    await evaluate(`(() => {const s=document.querySelectorAll('.seat:not(.taken)');s[0]?.click();s[1]?.click()})()`);
    await sleep(2200);
    await evaluate(`(() => {setTimeout(()=>document.querySelector('#form-booking').requestSubmit(),80);return true})()`);
    await sleep(2400);
    await caption('Checkout & Pembayaran', 'Ringkasan tiket, snack, poin, promo, dan beberapa metode pembayaran');
    await scrollTo(460, 1200); await sleep(1500);
    await evaluate(`document.querySelector('input[value=ewallet]')?.click()`); await sleep(1200);
    await scrollTo(900, 1200); await sleep(1200);
    await caption('Pembayaran Siap Diproses', 'Gateway pembayaran pada proyek ini menggunakan mode simulasi');
    await sleep(2600);

    await login('admin@sineverse.id');
    await caption('Dashboard Admin', 'Kelola film, jadwal, studio, promo, pengguna, dan laporan');
    await sleep(3000); await scrollTo(420, 1300); await sleep(1800);
    await login('kasir@sineverse.id');
    await caption('Dashboard Kasir', 'Validasi QR, transaksi walk-in, shift, riwayat, dan struk');
    await sleep(3000);
    await login('manajer@sineverse.id');
    await caption('Dashboard Manajer', 'Statistik penjualan dan pemantauan performa operasional');
    await sleep(3200);
    await evaluate(`(() => {const el=document.createElement('div');el.style.cssText='position:fixed;inset:0;z-index:2147483647;background:rgba(4,6,17,.96);display:grid;place-items:center;font-family:Segoe UI,Arial;color:white;text-align:center';el.innerHTML='<div><h1 style="font-size:54px;margin:0 0 14px">Terima Kasih</h1><p style="font-size:23px;color:#c4b5fd">Sineverse Cinema — Pesan tiket lebih mudah, cepat, dan aman.</p></div>';document.body.appendChild(el)})()`);
    await sleep(3200);
    recording = false; await Promise.race([capturing, sleep(1500)]);
  } finally {
    try { ws?.close(); } catch (_) {}
    chrome.kill();
  }
  const ff = spawnSync('ffmpeg', ['-y','-framerate',String(FPS),'-i',path.join(FRAMES,'frame-%06d.jpg'),'-c:v','libx264','-preset','medium','-crf','21','-pix_fmt','yuv420p','-movflags','+faststart',OUT], { stdio: 'inherit' });
  if (ff.status !== 0) process.exit(ff.status || 1);
  console.log(`VIDEO_OK=${OUT}`);
})().catch(err => { recording=false; chrome.kill(); console.error(err.stack); process.exit(1); });
