<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/FiturBioskop.php';

$userId = (int) ($_SESSION['pending_user_id'] ?? 0);
if (!$userId) {
    header('Location: login.php');
    exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = trim($_POST['kode'] ?? '');
    $pdo = Database::getInstance()->getKoneksi();
    $stmt = $pdo->prepare('SELECT 1 FROM verifikasi_email WHERE pengguna_id=? AND kode=? AND kedaluwarsa_pada > NOW()');
    $stmt->execute([$userId, $kode]);
    if ($stmt->fetchColumn()) {
        $pdo->prepare('UPDATE pengguna SET email_terverifikasi=1 WHERE id=?')->execute([$userId]);
        $pdo->prepare('DELETE FROM verifikasi_email WHERE pengguna_id=?')->execute([$userId]);
        unset($_SESSION['pending_user_id'], $_SESSION['demo_verification_code']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        FiturBioskop::audit($userId, 'verifikasi_email', 'pengguna', $userId);
        header('Location: index.php');
        exit();
    }
    $error = 'Kode salah atau sudah kedaluwarsa.';
}
$judulHalaman = 'Verifikasi Email — Sineverse';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell"><div class="auth-card glass" style="max-width:480px;margin:auto;">
    <span class="auth-kicker">Keamanan akun</span><h2>Verifikasi Email</h2>
    <p class="sub">Masukkan kode 6 digit. Untuk demo kampus, kode ditampilkan di bawah.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= amankan($error) ?></div><?php endif; ?>
    <?php if (
        !empty($_SESSION['demo_verification_code'])
    ): ?><div class="alert alert-success">Kode demo: <b><?= amankan(
    $_SESSION['demo_verification_code'],
) ?></b></div><?php endif; ?>
    <form method="POST"><?= inputCsrf() ?><div class="form-group"><label>Kode verifikasi</label><input class="form-control" name="kode" maxlength="6" required></div><button class="btn btn-primary btn-block">Verifikasi</button></form>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
