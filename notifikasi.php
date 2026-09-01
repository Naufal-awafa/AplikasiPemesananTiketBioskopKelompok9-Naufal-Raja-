<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/FiturBioskop.php';
$user = wajibLogin();
$pdo = Database::getInstance()->getKoneksi();
// Buat pengingat sekali untuk tiket yang tayang dalam 24 jam.
$stmt = $pdo->prepare(
    "SELECT t.id,t.kode_tiket,f.judul,j.tanggal,j.jam FROM tiket t JOIN jadwal j ON j.id=t.jadwal_id JOIN film f ON f.id=j.film_id WHERE t.customer_id=? AND t.status='lunas' AND TIMESTAMP(j.tanggal,j.jam) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY)",
);
$stmt->execute([$user->getId()]);
foreach ($stmt->fetchAll() as $t) {
    $cek = $pdo->prepare('SELECT 1 FROM notifikasi WHERE pengguna_id=? AND pesan LIKE ?');
    $cek->execute([$user->getId(), '%' . $t['kode_tiket'] . '%']);
    if (!$cek->fetchColumn()) {
        FiturBioskop::notifikasi(
            $user->getId(),
            'Film segera dimulai',
            "{$t['judul']} ({$t['kode_tiket']}) tayang pada {$t['tanggal']} {$t['jam']}.",
            'e-tiket.php?id=' . $t['id'],
        );
    }
}
if (isset($_GET['baca'])) {
    $pdo->prepare('UPDATE notifikasi SET dibaca=1 WHERE id=? AND pengguna_id=?')->execute([
        (int) $_GET['baca'],
        $user->getId(),
    ]);
    if (!empty($_GET['lanjut']) && !str_contains($_GET['lanjut'], '://')) {
        header('Location: ' . $_GET['lanjut']);
        exit();
    }
}
if (isset($_GET['semua'])) {
    $pdo->prepare('UPDATE notifikasi SET dibaca=1 WHERE pengguna_id=?')->execute([$user->getId()]);
}
$stmt = $pdo->prepare('SELECT * FROM notifikasi WHERE pengguna_id=? ORDER BY id DESC LIMIT 100');
$stmt->execute([$user->getId()]);
$list = $stmt->fetchAll();
$judulHalaman = 'Notifikasi — Sineverse';
require __DIR__ . '/includes/header.php';
?>
<section class="section container notification-page">
    <div class="section-head notification-head">
        <div><span class="section-kicker">Kotak masuk</span><h2>Notifikasi</h2><p>Status transaksi dan pengingat jadwalmu.</p></div>
        <?php if ($list): ?><a class="btn btn-ghost btn-sm" href="?semua=1"><?= ikon(
    'check',
    16,
) ?> Tandai semua dibaca</a><?php endif; ?>
    </div>
    <div class="notification-list">
        <?php if (!$list): ?><div class="empty-state glass"><div class="icon"><?= ikon(
    'bell',
    32,
) ?></div><p>Belum ada notifikasi.</p></div><?php endif; ?>
        <?php foreach ($list as $n):
            $belumDibaca = !$n['dibaca'];
            $urlNotifikasi = '?baca=' . (int) $n['id'];
            if ($n['tautan']) {
                $urlNotifikasi .= '&lanjut=' . urlencode($n['tautan']);
            }
            ?>
            <a
                class="notification-item<?= $belumDibaca ? ' unread' : '' ?>"
                href="<?= amankan($urlNotifikasi) ?>"
            >
                <span class="notification-item-icon"><?= ikon('bell', 20) ?></span>
                <span class="notification-item-content">
                    <span class="notification-item-title"><b><?= amankan($n['judul']) ?></b><?php if (
    $belumDibaca
): ?><em>Baru</em><?php endif; ?></span>
                    <span class="notification-message"><?= amankan($n['pesan']) ?></span>
                    <small><?= date('d M Y H:i', strtotime($n['dibuat_pada'])) ?> <i></i> <?= amankan(
     $n['channel'] ?? 'aplikasi',
 ) ?></small>
                </span>
                <span class="notification-arrow" aria-hidden="true"><?= ikon('arrow-right', 18) ?></span>
            </a>
        <?php
        endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
