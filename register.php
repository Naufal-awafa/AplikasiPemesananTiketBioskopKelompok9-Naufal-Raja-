<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Pengguna.php';

if (penggunaSaatIni()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $noHp = trim($_POST['no_hp'] ?? '');
    $password = $_POST['password'] ?? '';
    $ulangi = $_POST['ulangi_password'] ?? '';

    if ($nama === '' || $email === '' || $password === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $ulangi) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT id FROM pengguna WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar. Silakan masuk.';
        } else {
            $hash = Pengguna::buatHash($password);
            $stmt = $pdo->prepare(
                'INSERT INTO pengguna (nama, email, password, no_hp, role, email_terverifikasi) VALUES (?,?,?,?,?,0)',
            );
            $stmt->execute([$nama, $email, $hash, $noHp, 'customer']);
            $userId = (int) $pdo->lastInsertId();
            $kode = (string) random_int(100000, 999999);
            $pdo->prepare(
                'INSERT INTO verifikasi_email (pengguna_id,kode,kedaluwarsa_pada) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE kode=VALUES(kode), kedaluwarsa_pada=VALUES(kedaluwarsa_pada)',
            )->execute([$userId, $kode]);
            $_SESSION['pending_user_id'] = $userId;
            $_SESSION['demo_verification_code'] = $kode;
            header('Location: verifikasi-email.php');
            exit();
        }
    }
}

$judulHalaman = 'Daftar Akun — Sineverse Cinema';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="auth-split">
        <div class="auth-brand">
            <div class="aura-orb" aria-hidden="true"></div>
            <div class="brand-mark">
                <span class="dot"></span>
                <span class="brand-text">Sineverse<small>Cinema Experience</small></span>
            </div>
            <div class="brand-body">
                <p class="eyebrow">You can easily</p>
                <h1>Choose your movie, pick your seat, and enjoy the show.</h1>
            </div>
            <div class="brand-features">
                <div class="feature">
                    <span class="feature-ico"><?= ikon('film', 18) ?></span>
                    <span>Ribuan film terbaru setiap minggu</span>
                </div>
                <div class="feature">
                    <span class="feature-ico"><?= ikon('seat', 18) ?></span>
                    <span>Pilih kursi favoritmu sendiri</span>
                </div>
                <div class="feature">
                    <span class="feature-ico"><?= ikon('ticket', 18) ?></span>
                    <span>E-tiket langsung ke genggaman</span>
                </div>
            </div>
        </div>
        <div class="auth-form-wrap">
            <div class="auth-card glass">
                <div class="auth-card-head">
                    <span class="auth-kicker">Daftar akun</span>
                    <h2>Buat Akun Baru</h2>
                    <p class="sub">Daftar sebagai customer untuk mulai memesan tiket.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" data-autohide role="alert"><?= amankan($error) ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="field">
                        <label for="nama">Nama Lengkap</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('user', 18) ?></span>
                            <input id="nama" type="text" name="nama" class="form-control has-icon" placeholder="cth. Rangga Pratama" value="<?= amankan(
                                $_POST['nama'] ?? '',
                            ) ?>" required autocomplete="name">
                        </div>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('mail', 18) ?></span>
                            <input id="email" type="email" name="email" class="form-control has-icon" placeholder="nama@email.com" value="<?= amankan(
                                $_POST['email'] ?? '',
                            ) ?>" required autocomplete="email">
                        </div>
                    </div>
                    <div class="field">
                        <label for="no_hp">No. HP</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('phone', 18) ?></span>
                            <input id="no_hp" type="text" name="no_hp" class="form-control has-icon" placeholder="08xxxxxxxxxx" value="<?= amankan(
                                $_POST['no_hp'] ?? '',
                            ) ?>" autocomplete="tel">
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                            <input id="password" type="password" name="password" class="form-control has-icon" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                            <button type="button" class="field-toggle" data-toggle="password" aria-label="Tampilkan password" aria-pressed="false">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.4 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.8"/><path d="M6 6.3A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 3.4-.6"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="ulangi_password">Ulangi Password</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                            <input id="ulangi_password" type="password" name="ulangi_password" class="form-control has-icon" placeholder="Ulangi password" required autocomplete="new-password">
                            <button type="button" class="field-toggle" data-toggle="ulangi_password" aria-label="Tampilkan password" aria-pressed="false">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.4 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.8"/><path d="M6 6.3A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 3.4-.6"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Daftar Sekarang</button>
                </form>

                <p class="text-center mt-24" style="font-size:.87rem; color:var(--muted);">
                    Sudah punya akun? <a href="login.php" style="color:var(--accent2); font-weight:700;">Masuk di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.field-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.toggle);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        btn.querySelector('.eye-open').style.display = show ? 'none' : '';
        btn.querySelector('.eye-off').style.display  = show ? '' : 'none';
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
