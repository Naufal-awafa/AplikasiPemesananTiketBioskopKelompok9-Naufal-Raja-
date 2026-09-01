<?php
require_once __DIR__ . '/includes/helper.php';
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$success = false;
$pdo = Database::getInstance()->getKoneksi();
$stmt = $pdo->prepare('SELECT * FROM reset_password WHERE token=? AND digunakan=0 AND kedaluwarsa_pada > NOW()');
$stmt->execute([$token]);
$reset = $stmt->fetch();
if (!$reset) {
    $error = 'Tautan reset tidak valid atau sudah kedaluwarsa.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== ($_POST['ulangi_password'] ?? '')) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $pdo->prepare('UPDATE pengguna SET password=? WHERE id=?')->execute([
            password_hash($password, PASSWORD_BCRYPT),
            $reset['pengguna_id'],
        ]);
        $pdo->prepare('UPDATE reset_password SET digunakan=1 WHERE id=?')->execute([$reset['id']]);
        unset($_SESSION['demo_reset_link']);
        $success = true;
    }
}
$judulHalaman = 'Password Baru — Sineverse';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell"><div class="auth-card glass" style="max-width:480px;margin:auto;"><h2>Password Baru</h2>
<?php if (
    $success
): ?><div class="alert alert-success">Password berhasil diperbarui.</div><a class="btn btn-primary btn-block" href="login.php">Masuk</a>
<?php elseif ($error && !$reset): ?><div class="alert alert-error"><?= amankan($error) ?></div>
<?php else:if ($error): ?><div class="alert alert-error"><?= amankan(
    $error,
) ?></div><?php endif; ?><form method="POST"><?= inputCsrf() ?><input type="hidden" name="token" value="<?= amankan(
    $token,
) ?>"><div class="form-group"><label>Password baru</label><input type="password" name="password" class="form-control" required></div><div class="form-group"><label>Ulangi password</label><input type="password" name="ulangi_password" class="form-control" required></div><button class="btn btn-primary btn-block">Simpan Password</button></form><?php endif; ?></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
