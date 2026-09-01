<?php
require_once __DIR__ . '/includes/helper.php';

// Wajib login (semua role boleh mengatur akunnya sendiri).
$user = wajibLogin();

$pdo = Database::getInstance()->getKoneksi();

// Folder upload foto profil
$profilDir = __DIR__ . '/assets/img/profil';
if (!is_dir($profilDir)) {
    mkdir($profilDir, 0755, true);
}

$pesan = '';
$tipePesan = ''; // success | error

function simpanFotoProfil(array $file, string $profilDir, ?string $fotoLama): ?string
{
    $maksUkuran = 2 * 1024 * 1024; // 2 MB
    $diizinkan = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null; // tidak ada file diunggah -> biarkan foto tetap
    }
    if ($file['size'] > $maksUkuran) {
        throw new RuntimeException('Ukuran foto maksimal 2 MB.');
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($diizinkan[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, WEBP, atau GIF.');
    }
    // Hapus foto lama bila ada
    if ($fotoLama && $fotoLama !== '' && file_exists(__DIR__ . '/' . $fotoLama)) {
        @unlink(__DIR__ . '/' . $fotoLama);
    }
    $ext = $diizinkan[$mime];
    $namaFile = 'u' . $GLOBALS['user']->getId() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $tujuan = $profilDir . '/' . $namaFile;
    if (!move_uploaded_file($file['tmp_name'], $tujuan)) {
        throw new RuntimeException('Gagal menyimpan foto.');
    }
    return 'assets/img/profil/' . $namaFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1) Upload foto (jika ada)
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $path = simpanFotoProfil($_FILES['foto'], $profilDir, $user->getFoto());
            if ($path !== null) {
                $stmt = $pdo->prepare('UPDATE pengguna SET foto = ? WHERE id = ?');
                $stmt->execute([$path, $user->getId()]);
                $user->setFoto($path);
            }
        }

        // 2) Hapus foto (jika diminta)
        if (isset($_POST['hapus_foto']) && $user->getFoto() !== '') {
            if (file_exists(__DIR__ . '/' . $user->getFoto())) {
                @unlink(__DIR__ . '/' . $user->getFoto());
            }
            $stmt = $pdo->prepare('UPDATE pengguna SET foto = ? WHERE id = ?');
            $stmt->execute(['', $user->getId()]);
            $user->setFoto('');
        }

        // 3) Ubah nama & no HP
        $namaBaru = trim($_POST['nama'] ?? '');
        $noHpBaru = trim($_POST['no_hp'] ?? '');
        if ($namaBaru === '') {
            throw new RuntimeException('Nama tidak boleh kosong.');
        }
        $stmt = $pdo->prepare('UPDATE pengguna SET nama = ?, no_hp = ? WHERE id = ?');
        $stmt->execute([$namaBaru, $noHpBaru, $user->getId()]);
        $user->updateNama($namaBaru);
        $user->setNoHp($noHpBaru);

        // 4) Ubah password (opsional)
        $pwLama = $_POST['password_lama'] ?? '';
        $pwBaru = $_POST['password_baru'] ?? '';
        $pwUlang = $_POST['password_ulangi'] ?? '';
        if ($pwBaru !== '') {
            if (!$user->verifikasiPassword($pwLama)) {
                throw new RuntimeException('Password lama salah.');
            }
            if (strlen($pwBaru) < 6) {
                throw new RuntimeException('Password baru minimal 6 karakter.');
            }
            if ($pwBaru !== $pwUlang) {
                throw new RuntimeException('Konfirmasi password baru tidak cocok.');
            }
            $hash = Pengguna::buatHash($pwBaru);
            $stmt = $pdo->prepare('UPDATE pengguna SET password = ? WHERE id = ?');
            $stmt->execute([$hash, $user->getId()]);
        }

        $pesan = 'Pengaturan akun berhasil disimpan.';
        $tipePesan = 'success';
    } catch (Throwable $e) {
        $pesan = $e->getMessage();
        $tipePesan = 'error';
    }
}

// Siapkan data untuk tampilan
$fotoProfil = $user->getFoto();
$init = mb_strtoupper(mb_substr($user->getNama(), 0, 1));
$base = urlDasar();

$judulHalaman = 'Pengaturan Akun — Sineverse Cinema';
require __DIR__ . '/includes/header.php';
?>

<div class="settings-shell">
    <div class="settings-header">
        <span class="auth-kicker">Pengaturan</span>
        <h1>Akun Saya</h1>
        <p class="sub">Kelola foto profil, nama, dan keamanan akunmu.</p>
    </div>

    <div class="settings-card glass">
        <?php if ($pesan): ?>
            <div class="alert alert-<?= $tipePesan === 'success'
                ? 'success'
                : 'error' ?>" data-autohide role="alert"><?= amankan($pesan) ?></div>
        <?php endif; ?>

        <!-- Bagian Foto Profil -->
        <section class="settings-section">
            <div class="profile-photo-block">
                <div class="profile-photo">
                    <?php if ($fotoProfil): ?>
                        <img src="<?= $base . amankan($fotoProfil) ?>?v=<?= time() ?>" alt="Foto profil <?= amankan(
    $user->getNama(),
) ?>" class="profile-photo-img">
                    <?php else: ?>
                        <span class="profile-photo-initial"><?= $init ?></span>
                    <?php endif; ?>
                    <button type="button" class="profile-photo-edit" id="btnGantiFoto" aria-label="Ubah foto profil" title="Ubah foto">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                    </button>
                </div>
                <div class="profile-photo-side">
                    <h3>Foto Profil</h3>
                    <p class="muted-sm">JPG, PNG, WEBP, atau GIF. Maksimal 2 MB.</p>
                    <?php if ($fotoProfil): ?>
                        <button type="submit" form="formHapusFoto" class="btn btn-ghost btn-sm" onclick="return confirm('Hapus foto profil?')">Hapus Foto</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form upload foto tersembunyi, dikirim bersama form utama -->
            <input type="file" id="inputFoto" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" form="formPengaturan" hidden>

            <form id="formHapusFoto" method="POST" hidden>
                <input type="hidden" name="hapus_foto" value="1">
            </form>
        </section>

        <!-- Modal Atur/Crop Foto Profil -->
        <div class="cropper-overlay" id="cropperOverlay" hidden>
            <div class="cropper-modal">
                <h3>Atur Foto Profil</h3>
                <p class="muted-sm">Geser gambar untuk memposisikan, lalu gunakan slider untuk memperbesar/memperkecil.</p>
                <div class="cropper-stage" id="cropperStage">
                    <img id="cropperImage" alt="Pratinjau foto profil" draggable="false">
                </div>
                <div class="cropper-zoom-row">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-3.6-3.6"/></svg>
                    <input type="range" id="cropperZoom" min="1" max="3" step="0.01" value="1" aria-label="Perbesar foto">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-3.6-3.6"/><path d="M11 8v6M8 11h6"/></svg>
                </div>
                <div class="cropper-actions">
                    <button type="button" class="btn btn-ghost" id="cropperCancel">Batal</button>
                    <button type="button" class="btn btn-primary" id="cropperApply">Gunakan Foto</button>
                </div>
            </div>
        </div>

        <hr class="settings-divider">

        <!-- Form Utama -->
        <form id="formPengaturan" method="POST" enctype="multipart/form-data" novalidate>
            <section class="settings-section">
                <h2 class="settings-title"><?= ikon('user', 18) ?><span>Data Diri</span></h2>
                <div class="field">
                    <label for="nama">Nama Lengkap</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('user', 18) ?></span>
                        <input id="nama" type="text" name="nama" class="form-control has-icon" value="<?= amankan(
                            $user->getNama(),
                        ) ?>" required autocomplete="name">
                    </div>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('mail', 18) ?></span>
                        <input id="email" type="email" class="form-control has-icon" value="<?= amankan(
                            $user->getEmail(),
                        ) ?>" disabled readonly>
                    </div>
                    <p class="muted-sm">Email tidak dapat diubah.</p>
                </div>
                <div class="field">
                    <label for="no_hp">No. HP</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('phone', 18) ?></span>
                        <input id="no_hp" type="text" name="no_hp" class="form-control has-icon" placeholder="08xxxxxxxxxx" value="<?= amankan(
                            $user->getNoHp(),
                        ) ?>" autocomplete="tel">
                    </div>
                </div>
            </section>

            <hr class="settings-divider">

            <section class="settings-section">
                <h2 class="settings-title"><?= ikon('lock', 18) ?><span>Ubah Password</span></h2>
                <div class="field">
                    <label for="password_lama">Password Lama</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                        <input id="password_lama" type="password" name="password_lama" class="form-control has-icon" placeholder="Isi bila ingin ganti password" autocomplete="current-password">
                    </div>
                </div>
                <div class="field">
                    <label for="password_baru">Password Baru</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                        <input id="password_baru" type="password" name="password_baru" class="form-control has-icon" placeholder="Minimal 6 karakter" autocomplete="new-password">
                    </div>
                </div>
                <div class="field">
                    <label for="password_ulangi">Ulangi Password Baru</label>
                    <div class="field-control">
                        <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                        <input id="password_ulangi" type="password" name="password_ulangi" class="form-control has-icon" placeholder="Ulangi password baru" autocomplete="new-password">
                    </div>
                </div>
            </section>

            <div class="settings-actions">
                <button type="submit" class="btn btn-primary btn-block">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var inputFoto   = document.getElementById('inputFoto');
    var btnGanti    = document.getElementById('btnGantiFoto');
    var overlay     = document.getElementById('cropperOverlay');
    var stage       = document.getElementById('cropperStage');
    var img         = document.getElementById('cropperImage');
    var zoomSlider  = document.getElementById('cropperZoom');
    var btnCancel   = document.getElementById('cropperCancel');
    var btnApply    = document.getElementById('cropperApply');
    var form        = document.getElementById('formPengaturan');

    var OUTPUT_SIZE = 512; // ukuran output foto profil (px)
    var objectUrl = null;

    var stageW = 0, stageH = 0;      // ukuran area crop (bulat)
    var naturalW = 0, naturalH = 0;  // ukuran asli gambar
    var coverScale = 1;              // skala minimum agar gambar menutupi stage
    var scale = 1;                   // skala aktif saat ini
    var tx = 0, ty = 0;              // posisi gambar (translate) dalam px

    // Tombol pensil -> buka dialog pilih file
    btnGanti?.addEventListener('click', function () {
        inputFoto.click();
    });

    // Begitu file dipilih -> buka modal crop, JANGAN langsung submit
    inputFoto?.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);

        img.onload = function () {
            naturalW = img.naturalWidth;
            naturalH = img.naturalHeight;

            // Tampilkan modal DULU, baru ukur stage (kalau masih hidden, ukurannya 0x0)
            overlay.hidden = false;

            var rect = stage.getBoundingClientRect();
            stageW = rect.width;
            stageH = rect.height;

            coverScale = Math.max(stageW / naturalW, stageH / naturalH);
            scale = coverScale;
            zoomSlider.value = '1';

            applySize();
            // Tengahkan gambar di dalam stage
            tx = (stageW - naturalW * scale) / 2;
            ty = (stageH - naturalH * scale) / 2;
            clampPosisi();
            applyTransform();
        };
        img.onerror = function () {
            alert('Gagal memuat gambar. Coba pilih file gambar lain (JPG/PNG/WEBP/GIF).');
            inputFoto.value = '';
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        };
        img.src = objectUrl;
    });

    function applySize() {
        img.style.width = (naturalW * scale) + 'px';
        img.style.height = (naturalH * scale) + 'px';
    }

    function applyTransform() {
        img.style.transform = 'translate(' + tx + 'px, ' + ty + 'px)';
    }

    function clampPosisi() {
        var dispW = naturalW * scale;
        var dispH = naturalH * scale;
        var minTx = stageW - dispW, maxTx = 0;
        var minTy = stageH - dispH, maxTy = 0;
        tx = Math.min(maxTx, Math.max(minTx, tx));
        ty = Math.min(maxTy, Math.max(minTy, ty));
    }

    // --- Zoom via slider (berpusat di tengah stage) ---
    zoomSlider?.addEventListener('input', function () {
        var faktor = parseFloat(this.value); // 1 .. 3
        var newScale = coverScale * faktor;

        // Titik tengah stage, dikonversi ke koordinat gambar asli sebelum zoom
        var cx = (stageW / 2 - tx) / scale;
        var cy = (stageH / 2 - ty) / scale;

        scale = newScale;
        tx = stageW / 2 - cx * scale;
        ty = stageH / 2 - cy * scale;

        applySize();
        clampPosisi();
        applyTransform();
    });

    // --- Geser (drag) dengan mouse / sentuhan ---
    var dragging = false, startX = 0, startY = 0, startTx = 0, startTy = 0;

    stage?.addEventListener('pointerdown', function (e) {
        dragging = true;
        stage.classList.add('dragging');
        stage.setPointerCapture(e.pointerId);
        startX = e.clientX;
        startY = e.clientY;
        startTx = tx;
        startTy = ty;
    });

    stage?.addEventListener('pointermove', function (e) {
        if (!dragging) return;
        tx = startTx + (e.clientX - startX);
        ty = startTy + (e.clientY - startY);
        clampPosisi();
        applyTransform();
    });

    function berhentiGeser(e) {
        dragging = false;
        stage.classList.remove('dragging');
        if (e && stage.hasPointerCapture && stage.hasPointerCapture(e.pointerId)) {
            stage.releasePointerCapture(e.pointerId);
        }
    }
    stage?.addEventListener('pointerup', berhentiGeser);
    stage?.addEventListener('pointercancel', berhentiGeser);
    stage?.addEventListener('pointerleave', function () { if (dragging) dragging = false; });

    // Zoom pakai scroll wheel (opsional, bonus)
    stage?.addEventListener('wheel', function (e) {
        e.preventDefault();
        var langkah = e.deltaY < 0 ? 0.05 : -0.05;
        var nilaiBaru = Math.min(3, Math.max(1, parseFloat(zoomSlider.value) + langkah));
        zoomSlider.value = nilaiBaru.toFixed(2);
        zoomSlider.dispatchEvent(new Event('input'));
    }, { passive: false });

    // --- Batal ---
    btnCancel?.addEventListener('click', tutupModal);

    function tutupModal() {
        overlay.hidden = true;
        inputFoto.value = '';
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    }

    // --- Gunakan Foto: render hasil crop ke canvas, ganti isi input file, submit ---
    btnApply?.addEventListener('click', function () {
        var sx = -tx / scale;
        var sy = -ty / scale;
        var sSize = stageW / scale; // stage bujursangkar, jadi lebar = tinggi

        var canvas = document.createElement('canvas');
        canvas.width = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, sx, sy, sSize, sSize, 0, 0, OUTPUT_SIZE, OUTPUT_SIZE);

        canvas.toBlob(function (blob) {
            if (!blob) { tutupModal(); return; }
            var fileHasil = new File([blob], 'foto-profil.jpg', { type: 'image/jpeg' });
            var dt = new DataTransfer();
            dt.items.add(fileHasil);
            inputFoto.files = dt.files;

            overlay.hidden = true;
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }

            form.submit();
        }, 'image/jpeg', 0.92);
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
