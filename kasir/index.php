<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/FiturBioskop.php';
require_once __DIR__ . '/../core/TransaksiProduk.php';

$user = wajibLogin(['kasir']);
FiturBioskop::pastikanShiftHarian($user->getId());

$tiketHariIni = array_filter(Tiket::semua(), fn($t) => date('Y-m-d', strtotime($t->getDibuatPada())) === date('Y-m-d'));
$snackHariIni = array_filter(TransaksiProduk::untukKasir($user->getId()), fn($t) => date('Y-m-d', strtotime($t['dibuat_pada'])) === date('Y-m-d'));

$judulHalaman = 'Dashboard Kasir — Sineverse Cinema';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'index';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2 class="random-font-target">Dashboard Kasir</h2><p>Selamat bertugas, <?= amankan(
        $user->getNama(),
    ) ?></p></div>
</div>

<div class="grid-2 mb-24">
    <a href="validasi-qr.php" class="card glass reveal" style="text-align:center; padding:34px;">
        <div style="font-size:2.2rem;"><?= ikon('camera', 36) ?></div>
        <p class="mt-16" style="font-weight:800; font-size:1.05rem;">Validasi QR Tiket</p>
        <p class="text-muted mt-8" style="font-size:.85rem;">Cek keabsahan tiket penonton di pintu masuk studio.</p>
    </a>
    <a href="walkin.php" class="card glass reveal" style="text-align:center; padding:34px;">
        <div style="font-size:2.2rem;"><?= ikon('receipt', 36) ?></div>
        <p class="mt-16" style="font-weight:800; font-size:1.05rem;">Kasir Tiket &amp; Snack</p>
        <p class="text-muted mt-8" style="font-size:.85rem;">Layani tiket plus snack, atau pembelian snack saja secara langsung.</p>
    </a>
</div>

<div class="card glass reveal">
    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('receipt', 18) ?> Transaksi Hari Ini</h3>
    <?php if (empty($tiketHariIni) && empty($snackHariIni)): ?>
        <p class="text-muted" style="font-size:.87rem;">Belum ada transaksi hari ini.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Kode Tiket</th><th>Total</th><th>Metode</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($tiketHariIni as $t): ?>
                    <tr>
                        <td style="font-family:'Courier New',monospace;"><?= amankan($t->getKodeTiket()) ?></td>
                        <td><?= formatRupiah($t->getTotalHarga()) ?></td>
                        <td><?= amankan($t->getMetodeBayar()) ?></td>
                        <td><span class="pill <?= in_array($t->getStatus(), ['lunas', 'terpakai'], true)
                            ? 'pill-success'
                            : 'pill-muted' ?>"><?= strtoupper($t->getStatus()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($snackHariIni as $s): ?><tr><td style="font-family:'Courier New',monospace"><?= amankan($s['kode_transaksi']) ?></td><td><?= formatRupiah((int)$s['total_harga']) ?></td><td><?= amankan($s['metode_bayar']) ?></td><td><span class="pill pill-success">SNACK</span></td></tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
