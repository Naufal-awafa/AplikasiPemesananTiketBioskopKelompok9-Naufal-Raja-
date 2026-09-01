<?php
/**
 * includes/dash-open.php
 * Dipakai oleh halaman admin/kasir/manajer setelah include header.php.
 * Variabel yang harus di-set sebelum include:
 *   $menuAktif  (string) key menu yang sedang aktif
 * $user sudah tersedia dari header.php (variabel global $user).
 */
$menuSidebar = [
    'admin' => [
        'index' => [ikon('home', 18), 'Dashboard', 'index.php'],
        'films' => [ikon('film', 18), 'Kelola Film', 'films.php'],
        'tmdb' => [ikon('download', 18), 'Impor dari TMDB', 'import-tmdb.php'],
        'jadwal' => [ikon('calendar', 18), 'Kelola Jadwal', 'jadwal.php'],
        'studio' => [ikon('seat', 18), 'Studio & Kursi', 'studio.php'],
        'promo' => [ikon('ticket', 18), 'Promo & Diskon', 'promo.php'],
        'laporan' => [ikon('chart', 18), 'Laporan Penjualan', 'laporan.php'],
        'operasional' => [ikon('sparkles', 18), 'Pusat Operasional', 'operasional.php'],
        'pengguna' => [ikon('user', 18), 'Kelola Pengguna', 'pengguna.php'],
    ],
    'kasir' => [
        'index' => [ikon('home', 18), 'Dashboard', 'index.php'],
        'qr' => [ikon('qrcode', 18), 'Validasi QR Tiket', 'validasi-qr.php'],
        'walkin' => [ikon('ticket', 18), 'Kasir Tiket & Snack', 'walkin.php'],
        'riwayat' => [ikon('clock', 18), 'Riwayat Transaksi', 'riwayat.php'],
        'shift' => [ikon('clock', 18), 'Shift Kasir', 'shift.php'],
    ],
    'manajer' => [
        'index' => [ikon('home', 18), 'Dashboard', 'index.php'],
    ],
];

$role = $user->getRole();
$menus = $menuSidebar[$role] ?? [];
if ($role === 'admin') {
    $stmtHak = Database::getInstance()->getKoneksi()->prepare('SELECT hak_akses FROM pengguna WHERE id=?');
    $stmtHak->execute([$user->getId()]);
    $hak = trim((string) $stmtHak->fetchColumn());
    if ($hak !== '') {
        $izin = array_map('trim', explode(',', $hak));
        $peta = [
            'films' => 'film',
            'tmdb' => 'film',
            'jadwal' => 'jadwal',
            'studio' => 'studio',
            'promo' => 'promo',
            'laporan' => 'laporan',
            'pengguna' => 'pengguna',
            'operasional' => 'operasional',
        ];
        foreach ($peta as $menu => $butuh) {
            if (!in_array($butuh, $izin, true)) {
                unset($menus[$menu]);
            }
        }
    }
}
?>
<section class="container section" style="padding-top:24px;">
<div class="dash-shell">
    <aside class="dash-sidebar glass">
        <div class="side-title">Menu <?= amankan($user->getLabelPeran()) ?></div>
        <?php foreach ($menus as $key => [$icon, $label, $link]): ?>
            <a href="<?= $link ?>" class="<?= ($menuAktif ?? '') === $key ? 'active' : '' ?>"><?= $icon ?> <?= amankan(
     $label,
 ) ?></a>
        <?php endforeach; ?>
    </aside>
    <div class="dash-content">
