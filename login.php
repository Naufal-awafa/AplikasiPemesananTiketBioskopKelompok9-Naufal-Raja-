<?php
require_once __DIR__ . '/includes/helper.php';

if (penggunaSaatIni()) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $pdo = Database::getInstance()->getKoneksi();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $pdo->exec('DELETE FROM login_attempt WHERE dibuat_pada < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $cekBatas = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempt WHERE email=? AND ip_address=? AND berhasil=0 AND dibuat_pada >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
    );
    $cekBatas->execute([$email, $ip]);
    if ((int) $cekBatas->fetchColumn() >= 5) {
        $error = 'Terlalu banyak percobaan login. Coba lagi dalam 15 menit.';
    }

    $stmt = $pdo->prepare('SELECT * FROM pengguna WHERE email = ?');
    $baris = null;
    if ($error === '') {
        $stmt->execute([$email]);
        $baris = $stmt->fetch();
    }

    if ($baris) {
        $objek = buatObjekPengguna($baris);
        if ($objek->verifikasiPassword($password)) {
            $pdo->prepare('INSERT INTO login_attempt (email,ip_address,berhasil) VALUES (?,?,1)')->execute([
                $email,
                $ip,
            ]);
            if (!$objek->isAktif()) {
                $error = 'Akun ini telah dinonaktifkan oleh Admin. Hubungi pihak bioskop untuk info lebih lanjut.';
            } elseif (!(bool) ($baris['email_terverifikasi'] ?? 1)) {
                $_SESSION['pending_user_id'] = $objek->getId();
                header('Location: verifikasi-email.php');
                exit();
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $objek->getId();
                require_once __DIR__ . '/core/FiturBioskop.php';
                FiturBioskop::audit($objek->getId(), 'login', 'pengguna', $objek->getId());
                header('Location: ' . $objek->getDashboardUrl());
                exit();
            }
        }
    }
    if ($error === '') {
        $pdo->prepare('INSERT INTO login_attempt (email,ip_address,berhasil) VALUES (?,?,0)')->execute([$email, $ip]);
        $error = 'Email atau password salah.';
    }
}

$judulHalaman = 'Masuk — Sineverse Cinema';
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
                    <span class="auth-kicker">Masuk ke akun</span>
                    <h2>Selamat Datang Kembali</h2>
                    <p class="sub">Masuk untuk melanjutkan pemesanan tiket bioskop.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error" data-autohide role="alert"><?= amankan($error) ?></div>
                <?php elseif (isset($_GET['diblokir'])): ?>
                    <div class="alert alert-error" data-autohide role="alert">Akun Anda telah dinonaktifkan oleh Admin.</div>
                <?php endif; ?>

                <form method="POST" novalidate>
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
                        <label for="password">Password</label>
                        <div class="field-control">
                            <span class="field-icon" aria-hidden="true"><?= ikon('lock', 18) ?></span>
                            <input id="password" type="password" name="password" class="form-control has-icon" placeholder="Password" required autocomplete="current-password">
                            <button type="button" class="field-toggle" id="togglePassword" aria-label="Tampilkan password" aria-pressed="false">
                                <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none"><path d="M3 3l18 18"/><path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/><path d="M9.4 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3 3.8"/><path d="M6 6.3A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 3.4-.6"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                </form>

                <p class="text-center mt-16" style="font-size:.82rem;"><a href="lupa-password.php" style="color:var(--silver);">Lupa password?</a></p>

                <p class="text-center mt-24" style="font-size:.87rem; color:var(--muted);">
                    Belum punya akun? <a href="register.php" style="color:var(--accent2); font-weight:700;">Daftar di sini</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    this.setAttribute('aria-pressed', String(show));
    this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    this.classList.toggle('is-visible', show);
    this.querySelector('.eye-open').style.display = show ? 'none' : '';
    this.querySelector('.eye-off').style.display  = show ? '' : 'none';
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
