<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Laporan.php';

$user = wajibLogin(['manajer']);

$pendapatan = Laporan::totalPendapatan();
$tiketTerjual = Laporan::totalTiketTerjual();
$occupancy = Laporan::tingkatOccupancy();
$filmTerlaris = Laporan::filmTerlaris(6);
$mingguan = Laporan::pendapatanMingguan();
$maxPendapatan = max(1, ...array_map(fn($m) => (int) $m['pendapatan'], $mingguan ?: [['pendapatan' => 0]]));
$maxTerjual = max(1, ...array_map(fn($f) => (int) $f['jumlah_terjual'], $filmTerlaris ?: [['jumlah_terjual' => 0]]));

$judulHalaman = 'Dashboard Manajer — Sineverse Cinema';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'index';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2 class="random-font-target">Dashboard Manajer</h2><p>Pantau performa bisnis bioskop secara menyeluruh.</p></div>
</div>

<div class="kpi-grid">
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'wallet',
        22,
    ) ?></div><div class="kpi-num" style="font-size:1.15rem;"><?= formatRupiah(
    $pendapatan,
) ?></div><div class="kpi-label">Total Pendapatan</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'ticket',
        22,
    ) ?></div><div class="kpi-num" data-countup="<?= $tiketTerjual ?>">0</div><div class="kpi-label">Tiket Terjual</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'chart',
        22,
    ) ?></div><div class="kpi-num"><?= $occupancy ?>%</div><div class="kpi-label">Tingkat Okupansi Kursi</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'trophy',
        22,
    ) ?></div><div class="kpi-num"><?= $filmTerlaris[0]['judul'] ??
    '-' ?></div><div class="kpi-label" style="font-size:1rem;">Film Terlaris</div></div>
</div>

<div class="grid-2" style="grid-template-columns: 1.3fr 1fr;">
    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:8px;"><?= ikon(
            'chart',
            18,
        ) ?> Tren Pendapatan (7 Hari)</h3>
        <?php if (empty($mingguan)): ?>
            <p class="text-muted" style="font-size:.87rem;">Belum ada transaksi pada periode ini.</p>
        <?php else: ?>
            <div class="bar-chart">
                <?php foreach ($mingguan as $m):
                    $tinggi = max(4, ($m['pendapatan'] / $maxPendapatan) * 100); ?>
                    <div class="bar" style="height: <?= $tinggi ?>%;">
                        <span class="bar-value"><?= formatRupiah((int) $m['pendapatan']) ?></span>
                        <span class="bar-label"><?= date('d/m', strtotime($m['tanggal'])) ?></span>
                    </div>
                <?php
                endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
            'seat',
            18,
        ) ?> Tingkat Okupansi Kursi</h3>
        <div class="progress-bar"><div class="fill" style="width: <?= $occupancy ?>%;"></div></div>
        <p class="text-muted mt-16" style="font-size:.85rem;">Rata-rata <b style="color:var(--accent2);"><?= $occupancy ?>%</b> kursi terisi dari total kapasitas seluruh sesi tayang.</p>
    </div>
</div>

<div class="card glass reveal mt-24">
    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
        'trophy',
        18,
    ) ?> Statistik Film Terlaris</h3>
    <?php if (empty($filmTerlaris)): ?>
        <p class="text-muted" style="font-size:.87rem;">Belum ada data penjualan.</p>
    <?php else: ?>
        <?php foreach ($filmTerlaris as $i => $f):
            $persen = ($f['jumlah_terjual'] / $maxTerjual) * 100; ?>
            <div class="mb-16">
                <div class="flex justify-between mb-8" style="font-size:.87rem;">
                    <span><b>#<?= $i + 1 ?></b> <?= amankan($f['judul']) ?></span>
                    <span class="text-muted"><?= $f['jumlah_terjual'] ?> tiket &middot; <?= formatRupiah(
     (int) $f['pendapatan'],
 ) ?></span>
                </div>
                <div class="progress-bar"><div class="fill" style="width: <?= $persen ?>%;"></div></div>
            </div>
        <?php
        endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
