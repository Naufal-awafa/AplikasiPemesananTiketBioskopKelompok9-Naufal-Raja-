<?php
require_once __DIR__ . '/includes/helper.php';
$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pdo = Database::getInstance()->getKoneksi();
    $stmt = $pdo->prepare('SELECT id FROM pengguna WHERE email=?');
    $stmt->execute([$email]);
    if ($id = $stmt->fetchColumn()) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare(
            'INSERT INTO reset_password (pengguna_id,token,kedaluwarsa_pada) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
        )->execute([(int) $id, $token]);
        $_SESSION['demo_reset_link'] = 'reset-password.php?token=' . $token;
    }
    $pesan = 'Jika email terdaftar, tautan reset telah dibuat.';
}
$judulHalaman = 'Lupa Password — Sineverse';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell"><div class="auth-card glass" style="max-width:480px;margin:auto;"><h2>Reset Password</h2><p class="sub">Masukkan email akunmu.</p>
<?php if ($pesan): ?><div class="alert alert-success"><?= amankan($pesan) ?></div><?php endif; ?>
<?php if (!empty($_SESSION['demo_reset_link'])): ?><a class="btn btn-outline btn-block mb-24" href="<?= amankan(
    $_SESSION['demo_reset_link'],
) ?>">Buka tautan reset demo</a><?php endif; ?>
<form method="POST"><?= inputCsrf() ?><div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div><button class="btn btn-primary btn-block">Buat Tautan Reset</button></form></div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
