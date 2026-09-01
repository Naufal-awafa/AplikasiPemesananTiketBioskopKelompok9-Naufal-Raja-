<?php
/**
 * includes/header.php
 * Dipanggil di setiap halaman publik (bukan admin/kasir/manajer yang
 * punya header sendiri di includes/header-dashboard.php).
 * Variabel opsional yang bisa di-set sebelum include:
 *   $judulHalaman  (string) judul tab browser
 *   $hargaReguler / $hargaVip (int) dipakai body[data-*] utk seat picker
 */
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../core/FiturBioskop.php';
$user = penggunaSaatIni();
$base = urlDasar();
$judul = $judulHalaman ?? 'Sineverse Cinema';
$logo = logoFilename(); // null jika belum ada logo custom di assets/img/
$jmlNotif = $user ? FiturBioskop::notifikasiBelumDibaca($user->getId()) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= amankan($_SESSION['csrf_token']) ?>">
<title><?= amankan($judul) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Righteous&family=Bebas+Neue&family=Playfair+Display:wght@400;700&family=Caveat:wght@400;700&family=Pacifico&family=Orbitron:wght@400;700&family=Space+Grotesk:wght@300;700&family=Anton&family=Dancing+Script:wght@400;700&family=Abril+Fatface&family=Courier+Prime:wght@400;700&family=Permanent+Marker&display=swap" rel="stylesheet">
<?php
$cssPath = __DIR__ . '/../assets/css/style.css';
$cssVersi = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<link rel="stylesheet" href="<?= $base ?>assets/css/style.css?v=<?= $cssVersi ?>">
<?php if ($logo): ?>
<link rel="icon" href="<?= $base ?>assets/img/<?= $logo ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎬</text></svg>">
<?php endif; ?>
<body data-harga-reguler="<?= $hargaReguler ?? 0 ?>" data-harga-vip="<?= $hargaVip ?? 0 ?>"<?= isset($judulHalaman) &&
(str_contains($judulHalaman, 'Masuk') || str_contains($judulHalaman, 'Daftar'))
    ? ' class="auth-page"'
    : '' ?>>
<script>window.SINEVERSE_CSRF=<?= json_encode($_SESSION['csrf_token']) ?>;</script>

<?php if (!empty($tampilkanPreloader)): ?>
<div id="preloader">
    <div id="preloader-particles"></div>
    <div class="preloader-inner">
        <div class="preloader-logo-wrap" id="preloader-logo-wrap">
            <?php if ($logo): ?>
                <img src="<?= $base ?>assets/img/<?= $logo ?>" alt="Sineverse" class="preloader-logo-img">
            <?php else: ?>
                <span class="preloader-logo"><?= ikon('film', 34) ?></span>
            <?php endif; ?>
            <h2 class="preloader-title">SINEVERSE</h2>
        </div>
        <p class="preloader-sub" id="preloader-sub">Cinema Experience</p>
    </div>
</div>
<!-- Failsafe: sembunyikan preloader walau JS gagal total (tidak menutupi tombol) -->
<noscript><style>#preloader{display:none !important;}</style></noscript>
<?php endif; ?>

<div class="aurora"><span></span><span></span><span></span><span></span></div>
<div class="grain-overlay"></div>
<div class="mouse-glow"></div>
<div class="cursor-dot"></div>
<div class="cursor-ring"><span class="cursor-text"></span></div>

<header class="navbar glass<?= isset($judulHalaman) &&
(str_contains($judulHalaman, 'Masuk') || str_contains($judulHalaman, 'Daftar'))
    ? ' navbar-hidden'
    : '' ?>">
    <a href="<?= $base ?>index.php" class="brand">
        <?php if ($logo): ?>
            <img src="<?= $base ?>assets/img/<?= $logo ?>" alt="Sineverse" class="brand-logo-img">
        <?php else: ?>
            <span class="brand-dot"><?= ikon('film', 18) ?></span>
        <?php endif; ?>
        <span>Sineverse<span class="sub">Cinema Experience</span></span>
    </a>

    <nav class="nav-links">
        <a href="<?= $base ?>index.php">Beranda</a>
        <?php if ($user): ?>
            <a href="<?= $base ?>riwayat.php">Riwayat</a>
            <a href="<?= $base ?>favorit.php">Favorit</a>
        <?php endif; ?>
        <a href="<?= $base ?>menu.php">Snack &amp; Minuman</a>
        <a href="<?= $base ?>index.php#tentang">Tentang</a>
    </nav>

    <div class="nav-actions">
        <?php if ($user):

            $accInit = mb_strtoupper(mb_substr($user->getNama(), 0, 1));
            $accNama = amankan($user->getNama());
            $accRole = amankan($user->getLabelPeran());
            $accEmail = amankan($user->getEmail());
            $accFoto = $user->getFoto();
            ?>
        <a href="<?= $base ?>notifikasi.php" class="notification-trigger<?= str_contains($judul, 'Notifikasi')
    ? ' active'
    : '' ?>" aria-label="Notifikasi<?= $jmlNotif ? ': ' . $jmlNotif . ' belum dibaca' : '' ?>" title="Notifikasi">
            <?= ikon('bell', 21) ?>
            <?php if ($jmlNotif): ?><span class="notification-badge"><?= $jmlNotif > 99
    ? '99+'
    : $jmlNotif ?></span><?php endif; ?>
        </a>
        <div class="account-menu">
            <button type="button" class="account-trigger" aria-haspopup="true" aria-expanded="false" aria-label="Buka menu akun">
                <span class="account-avatar">
                    <?php if ($accFoto): ?>
                        <img src="<?= $base . amankan($accFoto) ?>" alt="<?= $accNama ?>">
                    <?php else: ?>
                        <?= $accInit ?>
                    <?php endif; ?>
                </span>
                <span class="account-meta">
                    <span class="account-name"><?= $accNama ?></span>
                    <span class="account-role"><?= $accRole ?></span>
                </span>
                <span class="account-caret" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                </span>
            </button>
            <div class="account-dropdown" role="menu">
                <span class="account-dropdown-label">Masuk sebagai</span>
                <div class="account-dropdown-head">
                    <span class="account-avatar lg">
                        <?php if ($accFoto): ?>
                            <img src="<?= $base . amankan($accFoto) ?>" alt="<?= $accNama ?>">
                        <?php else: ?>
                            <?= $accInit ?>
                        <?php endif; ?>
                    </span>
                    <span class="account-meta">
                        <span class="account-name"><?= $accNama ?></span>
                        <span class="account-role"><?= $accRole ?></span>
                        <span class="account-email"><?= $user->getPoin() ?> poin loyalitas</span>
                        <?php if ($accEmail): ?><span class="account-email"><?= $accEmail ?></span><?php endif; ?>
                    </span>
                </div>
                <div class="account-dropdown-items">
                    <a href="<?= $base ?>riwayat.php" class="account-item" role="menuitem"><?= ikon(
    'ticket',
    18,
) ?><span>Riwayat Tiket Pribadi</span></a>
                    <a href="<?= $base ?>favorit.php" class="account-item" role="menuitem"><?= ikon(
    'heart',
    18,
) ?><span>Film Favorit</span></a>
                    <a href="<?= $base ?>notifikasi.php" class="account-item" role="menuitem"><?= ikon(
    'bell',
    18,
) ?><span>Notifikasi<?= $jmlNotif ? ' (' . $jmlNotif . ')' : '' ?></span></a>
                    <?php if ($user->getRole() !== 'customer'): ?>
                        <a href="<?= $base . $user->getDashboardUrl() ?>" class="account-item" role="menuitem"><?= ikon(
    'chart',
    18,
) ?><span>Dashboard</span></a>
                    <?php endif; ?>
                    <a href="<?= $base ?>pengaturan.php" class="account-item" role="menuitem"><?= ikon(
    'user',
    18,
) ?><span>Pengaturan Akun</span></a>
                </div>
                <div class="account-dropdown-items account-dropdown-items-end">
                    <a href="<?= $base ?>logout.php" class="account-item account-item-logout" role="menuitem">
                        <svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                        <span>Keluar</span>
                    </a>
                </div>
            </div>
        </div>
        <?php
        else:
             ?>
            <a href="<?= $base ?>login.php" class="btn btn-ghost btn-sm">Masuk</a>
            <a href="<?= $base ?>register.php" class="btn btn-primary btn-sm">Daftar</a>
        <?php
        endif; ?>
        <button class="hamburger btn btn-ghost btn-sm" aria-label="Buka menu"><?= ikon('menu', 20) ?></button>
    </div>
</header>

<main>
