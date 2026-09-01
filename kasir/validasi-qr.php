<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Kasir.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibLogin(['kasir']);

$hasil = null; // 'valid' | 'invalid' | 'terpakai' | 'batal'
$tiketDitemukan = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kodeInput = trim($_POST['kode_tiket'] ?? '');
    $tiketDitemukan = Tiket::cariByKode($kodeInput);

    if (!$tiketDitemukan) {
        $hasil = 'invalid';
    } elseif ($tiketDitemukan->getStatus() === 'batal') {
        $hasil = 'batal';
    } elseif ($tiketDitemukan->getStatus() === 'terpakai') {
        $hasil = 'terpakai';
    } elseif ($tiketDitemukan->getStatus() === 'lunas') {
        // >> Method khusus milik Kasir: validasiTiket melalui aksi tandaiTerpakai()
        if (isset($_POST['konfirmasi_masuk'])) {
            $tiketDitemukan->tandaiTerpakai();
            FiturBioskop::audit(
                $user->getId(),
                'validasi_masuk',
                'tiket',
                $tiketDitemukan->getId(),
                $tiketDitemukan->getKodeTiket(),
            );
            $hasil = 'terpakai_baru';
        } else {
            $hasil = 'valid';
        }
    } else {
        $hasil = 'pending';
    }
}

$judulHalaman = 'Validasi QR — Kasir';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'qr';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Validasi QR Tiket</h2><p>Masukkan / scan kode QR tiket penonton untuk memverifikasi keabsahannya.</p></div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 380px; align-items:flex-start;">
    <div class="card glass reveal">
        <form method="POST" id="qr-validation-form">
            <div class="form-group">
                <label>Kode Tiket / Kode QR</label>
                <input type="text" name="kode_tiket" class="form-control" placeholder="Contoh: TIX-A1B2C3D4E5" value="<?= amankan(
                    $_POST['kode_tiket'] ?? '',
                ) ?>" autofocus required style="font-family:'Courier New',monospace;">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Cek Tiket</button>
        </form>
        <div class="qr-scan-actions mt-16">
            <button type="button" id="btn-scan-camera" class="btn btn-outline"><?= ikon(
                'camera',
                18,
            ) ?> Scan dengan Kamera</button>
            <label for="qr-image-upload" class="btn btn-ghost"><?= ikon('download', 18) ?> Pilih Screenshot QR</label>
            <input type="file" id="qr-image-upload" accept="image/*" hidden>
        </div>
        <div id="qr-camera-wrap" class="mt-16" style="display:none;">
            <div class="qr-camera-frame" id="qr-camera-frame"><video id="qr-camera" playsinline muted></video><span class="qr-scan-guide" aria-hidden="true"></span></div>
            <canvas id="qr-camera-canvas" hidden></canvas>
            <p id="qr-camera-status" class="text-muted mt-8" style="font-size:.78rem;">Menyiapkan kamera...</p>
        </div>

        <?php if ($hasil): ?>
            <div class="mt-24">
                <?php if ($hasil === 'invalid'): ?>
                    <div class="alert alert-error" style="margin-bottom:0;"><?= ikon(
                        'alert-octagon',
                        16,
                    ) ?> Kode tiket tidak ditemukan dalam sistem.</div>
                <?php elseif ($hasil === 'batal'): ?>
                    <div class="alert alert-error" style="margin-bottom:0;"><?= ikon(
                        'ban',
                        16,
                    ) ?> Tiket ini sudah <b>dibatalkan</b> oleh customer.</div>
                <?php elseif ($hasil === 'terpakai'): ?>
                    <div class="alert alert-error" style="margin-bottom:0;"><?= ikon(
                        'alert',
                        16,
                    ) ?> Tiket ini <b>sudah pernah digunakan</b> sebelumnya.</div>
                <?php elseif ($hasil === 'pending'): ?>
                    <div class="alert alert-error" style="margin-bottom:0;"><?= ikon(
                        'clock',
                        16,
                    ) ?> Pembayaran tiket ini <b>belum lunas</b>.</div>
                <?php elseif ($hasil === 'valid'):

                    $jadwal = Jadwal::cariById($tiketDitemukan->getJadwalId());
                    $film = $jadwal?->getFilm();
                    ?>
                    <div class="alert alert-success" style="margin-bottom:16px;"><?= ikon(
                        'check',
                        16,
                    ) ?> Tiket VALID &mdash; <?= amankan($film?->getJudul() ?? '') ?></div>
                    <div class="summary-row"><span>Kode</span><span><?= amankan(
                        $tiketDitemukan->getKodeTiket(),
                    ) ?></span></div>
                    <div class="summary-row"><span>Jadwal</span><span><?= $jadwal
                        ? formatTanggalIndo($jadwal->getTanggal()) . ', ' . amankan($jadwal->getJam())
                        : '-' ?></span></div>
                    <form method="POST" class="mt-16">
                        <input type="hidden" name="kode_tiket" value="<?= amankan($tiketDitemukan->getKodeTiket()) ?>">
                        <button type="submit" name="konfirmasi_masuk" value="1" class="btn btn-primary btn-block">Konfirmasi &amp; Izinkan Masuk</button>
                    </form>
                <?php
                elseif ($hasil === 'terpakai_baru'): ?>
                    <div class="alert alert-success" style="margin-bottom:0;"><?= ikon(
                        'sparkles',
                        16,
                    ) ?> Penonton berhasil dikonfirmasi masuk studio!</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:.95rem; font-weight:800; margin-bottom:12px;"><?= ikon('info', 16) ?> Panduan Validasi</h3>
        <ul style="font-size:.85rem; color:var(--muted); line-height:2; list-style:disc; padding-left:18px;">
            <li>Status <b style="color:var(--success);">LUNAS</b> &rarr; boleh dikonfirmasi masuk.</li>
            <li>Status <b style="color:var(--warning);">PENDING</b> &rarr; arahkan ke loket pembayaran.</li>
            <li>Status <b style="color:var(--danger);">BATAL</b> / <b>TERPAKAI</b> &rarr; tolak akses masuk.</li>
        </ul>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
(() => {
    const button = document.getElementById('btn-scan-camera');
    const wrap = document.getElementById('qr-camera-wrap');
    const frame = document.getElementById('qr-camera-frame');
    const video = document.getElementById('qr-camera');
    const canvas = document.getElementById('qr-camera-canvas');
    const upload = document.getElementById('qr-image-upload');
    const status = document.getElementById('qr-camera-status');
    const form = document.getElementById('qr-validation-form');
    const input = form?.querySelector('input[name="kode_tiket"]');
    const context = canvas?.getContext('2d', {willReadFrequently:true});
    let stream = null;
    let scanning = false;
    let detector = null;

    const stopCamera = () => {
        scanning = false;
        if (stream) stream.getTracks().forEach(track => track.stop());
        stream = null;
        if (video) video.srcObject = null;
        if (button) button.innerHTML = <?= json_encode(ikon('camera', 18) . ' Scan dengan Kamera') ?>;
    };

    const submitCode = rawValue => {
        const code = String(rawValue || '').trim();
        if (!code || !input || !form) return false;
        input.value = code;
        status.textContent = 'QR terbaca. Memeriksa tiket...';
        stopCamera();
        form.requestSubmit();
        return true;
    };

    const scanFrame = async () => {
        if (!scanning || !video || video.readyState < 2) {
            if (scanning) requestAnimationFrame(scanFrame);
            return;
        }
        try {
            if (detector) {
                const codes = await detector.detect(video);
                if (codes.length && submitCode(codes[0].rawValue)) return;
            } else if (typeof window.jsQR === 'function' && context) {
                const width = video.videoWidth;
                const height = video.videoHeight;
                if (width && height) {
                    canvas.width = width;
                    canvas.height = height;
                    context.drawImage(video, 0, 0, width, height);
                    const pixels = context.getImageData(0, 0, width, height);
                    const result = window.jsQR(pixels.data, width, height, {inversionAttempts:'dontInvert'});
                    if (result && submitCode(result.data)) return;
                }
            }
        } catch (error) {
            console.warn('Pemindaian QR native gagal, beralih ke jsQR:', error);
            detector = null;
        }
        if (scanning) setTimeout(scanFrame, 90);
    };

    button?.addEventListener('click', async () => {
        if (scanning) {
            stopCamera();
            wrap.style.display = 'none';
            return;
        }
        wrap.style.display = 'block';
        frame.style.display = 'block';
        status.textContent = 'Meminta akses kamera...';
        if (!navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Akses kamera tidak tersedia. Buka aplikasi melalui localhost atau HTTPS.';
            return;
        }
        try {
            if ('BarcodeDetector' in window) {
                try { detector = new BarcodeDetector({formats:['qr_code']}); } catch (_) { detector = null; }
            }
            if (!detector && typeof window.jsQR !== 'function') {
                status.textContent = 'Komponen pemindai QR gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.';
                return;
            }
            try {
                stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false});
            } catch (firstError) {
                if (firstError.name === 'OverconstrainedError') stream = await navigator.mediaDevices.getUserMedia({video:true,audio:false});
                else throw firstError;
            }
            video.srcObject = stream;
            await video.play();
            scanning = true;
            button.textContent = 'Tutup Kamera';
            status.textContent = 'Kamera aktif. Arahkan QR ke dalam kotak.';
            scanFrame();
        } catch (error) {
            stopCamera();
            const messages = {
                NotAllowedError: 'Izin kamera ditolak. Klik ikon kamera di address bar lalu pilih Izinkan.',
                NotFoundError: 'Kamera tidak ditemukan pada perangkat ini.',
                NotReadableError: 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.',
                SecurityError: 'Akses kamera diblokir oleh pengaturan keamanan browser.'
            };
            status.textContent = messages[error.name] || `Kamera gagal dibuka: ${error.message || 'kesalahan tidak diketahui'}.`;
        }
    });

    upload?.addEventListener('change', async () => {
        const file = upload.files?.[0];
        if (!file || !context) return;
        stopCamera();
        wrap.style.display = 'block';
        frame.style.display = 'none';
        status.textContent = 'Membaca QR dari gambar...';
        try {
            const bitmap = await createImageBitmap(file);
            const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
            canvas.width = Math.max(1, Math.round(bitmap.width * scale));
            canvas.height = Math.max(1, Math.round(bitmap.height * scale));
            context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            let rawValue = '';
            if ('BarcodeDetector' in window) {
                try {
                    const imageDetector = new BarcodeDetector({formats:['qr_code']});
                    const codes = await imageDetector.detect(canvas);
                    rawValue = codes[0]?.rawValue || '';
                } catch (_) {}
            }
            if (!rawValue && typeof window.jsQR === 'function') {
                const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
                rawValue = window.jsQR(pixels.data, canvas.width, canvas.height, {inversionAttempts:'attemptBoth'})?.data || '';
            }
            if (!submitCode(rawValue)) status.textContent = 'QR tidak ditemukan pada gambar. Gunakan screenshot yang tajam dan tidak terpotong.';
            bitmap.close?.();
        } catch (error) {
            status.textContent = `Gambar gagal dibaca: ${error.message || 'format tidak didukung'}.`;
        } finally {
            upload.value = '';
        }
    });

    window.addEventListener('pagehide', stopCamera);
})();
</script>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
