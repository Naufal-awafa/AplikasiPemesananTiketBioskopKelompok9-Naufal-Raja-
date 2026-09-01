<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Film.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Laporan.php';
require_once __DIR__ . '/../core/Promo.php';

$user = wajibLogin(['admin']);

$totalFilm = count(Film::semua());
$totalJadwal = count(Jadwal::semua());
$totalPromo = count(array_filter(Promo::semua(), fn($p) => $p->isAktif()));
$pendapatan = Laporan::totalPendapatan();
$filmTerlaris = Laporan::filmTerlaris(5);

$judulHalaman = 'Dashboard Admin — Sineverse Cinema';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'index';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2 class="random-font-target" data-font-interval="180">Dashboard Admin</h2><p>Ringkasan pengelolaan data operasional bioskop.</p></div>
</div>

<div class="kpi-grid">
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'film',
        22,
    ) ?></div><div class="kpi-num" data-countup="<?= $totalFilm ?>">0</div><div class="kpi-label">Total Film</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'calendar',
        22,
    ) ?></div><div class="kpi-num" data-countup="<?= $totalJadwal ?>">0</div><div class="kpi-label">Jadwal Tayang</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'ticket',
        22,
    ) ?></div><div class="kpi-num" data-countup="<?= $totalPromo ?>">0</div><div class="kpi-label">Promo Aktif</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'wallet',
        22,
    ) ?></div><div class="kpi-num" style="font-size:1.15rem;"><?= formatRupiah(
    $pendapatan,
) ?></div><div class="kpi-label">Total Pendapatan</div></div>
</div>

<div class="card glass reveal">
    <h3 style="font-size:1rem; font-weight:800; margin-bottom:18px;"><?= ikon('star-fill', 18) ?> Film Terlaris</h3>
    <?php if (empty($filmTerlaris)): ?>
        <p class="text-muted" style="font-size:.87rem;">Belum ada data penjualan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Judul</th><th>Tiket Terjual</th><th>Pendapatan</th></tr></thead>
                <tbody>
                <?php foreach ($filmTerlaris as $f): ?>
                    <tr><td><?= amankan($f['judul']) ?></td><td><?= $f['jumlah_terjual'] ?></td><td><?= formatRupiah(
    (int) $f['pendapatan'],
) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid-3 mt-24">
    <a href="films.php" class="card glass reveal" style="text-align:center;"><div style="font-size:1.8rem;"><?= ikon(
        'film',
        28,
    ) ?></div><p class="mt-8" style="font-weight:700;">Kelola Film</p></a>
    <a href="jadwal.php" class="card glass reveal" style="text-align:center;"><div style="font-size:1.8rem;"><?= ikon(
        'calendar',
        28,
    ) ?></div><p class="mt-8" style="font-weight:700;">Kelola Jadwal</p></a>
    <a href="promo.php" class="card glass reveal" style="text-align:center;"><div style="font-size:1.8rem;"><?= ikon(
        'ticket',
        28,
    ) ?></div><p class="mt-8" style="font-weight:700;">Kelola Promo</p></a>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
