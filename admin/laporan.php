<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Laporan.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/Jadwal.php';

$user = wajibAkses('laporan');

$pendapatan = Laporan::totalPendapatan();
$tiketTerjual = Laporan::totalTiketTerjual();
$filmTerlaris = Laporan::filmTerlaris(10);
$occupancy = Laporan::tingkatOccupancy();
$mingguan = Laporan::pendapatanMingguan();
$semuaTiket = Tiket::semua();
$pdo = Database::getInstance()->getKoneksi();
$totalDiskonStaff = (int) $pdo
    ->query("SELECT COALESCE(SUM(potongan_jabatan),0) FROM tiket WHERE status IN ('lunas','terpakai')")
    ->fetchColumn();
$totalDiskonPromo = (int) $pdo
    ->query("SELECT COALESCE(SUM(potongan_promo),0) FROM tiket WHERE status IN ('lunas','terpakai')")
    ->fetchColumn();
$totalRefund = (int) $pdo
    ->query("SELECT COALESCE(SUM(jumlah),0) FROM refund WHERE status IN ('disetujui','selesai')")
    ->fetchColumn();
$jamTerlaris = $pdo
    ->query(
        "SELECT j.jam,COUNT(t.id) jumlah FROM tiket t JOIN jadwal j ON j.id=t.jadwal_id WHERE t.status IN ('lunas','terpakai') GROUP BY j.jam ORDER BY jumlah DESC LIMIT 1",
    )
    ->fetch();
$kursiFavorit = $pdo
    ->query(
        "SELECT CONCAT(k.baris,k.nomor) label,COUNT(*) jumlah FROM detail_tiket d JOIN kursi k ON k.id=d.kursi_id JOIN tiket t ON t.id=d.tiket_id WHERE t.status IN ('lunas','terpakai') GROUP BY k.baris,k.nomor ORDER BY jumlah DESC LIMIT 1",
    )
    ->fetch();
$promoTerpakai = $pdo
    ->query(
        "SELECT kode_promo,COUNT(*) jumlah,SUM(potongan_promo) potongan FROM tiket WHERE kode_promo!='' GROUP BY kode_promo ORDER BY jumlah DESC",
    )
    ->fetchAll();

$judulHalaman = 'Laporan Penjualan — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'laporan';
require __DIR__ . '/../includes/dash-open.php';

$maxPendapatan = max(1, ...array_map(fn($m) => (int) $m['pendapatan'], $mingguan ?: [['pendapatan' => 0]]));
?>

<div class="section-head reveal">
    <div><h2>Laporan Penjualan</h2><p>Ringkasan performa penjualan tiket bioskop.</p></div>
    <div class="flex gap-8"><a class="btn btn-outline" href="export-laporan.php?format=csv"><?= ikon(
        'download',
        16,
    ) ?> CSV/Excel</a><a class="btn btn-outline" target="_blank" href="export-laporan.php?format=print"><?= ikon(
     'print',
     16,
 ) ?> Cetak/PDF</a></div>
</div>
<div class="kpi-grid mb-24">
    <div class="kpi-card glass"><div class="kpi-num" style="font-size:1rem"><?= formatRupiah(
        $totalDiskonStaff,
    ) ?></div><div class="kpi-label">Diskon Pegawai</div></div>
    <div class="kpi-card glass"><div class="kpi-num" style="font-size:1rem"><?= formatRupiah(
        $totalDiskonPromo,
    ) ?></div><div class="kpi-label">Diskon Promo</div></div>
    <div class="kpi-card glass"><div class="kpi-num" style="font-size:1rem"><?= formatRupiah(
        $totalRefund,
    ) ?></div><div class="kpi-label">Refund Disetujui</div></div>
    <div class="kpi-card glass"><div class="kpi-num" style="font-size:1rem"><?= amankan(
        $jamTerlaris['jam'] ?? '-',
    ) ?></div><div class="kpi-label">Jam Terlaris</div></div>
</div>
<?php if (
    $promoTerpakai
): ?><div class="card glass mb-24"><h3>Efektivitas Promo</h3><div class="flex gap-12 mt-16" style="flex-wrap:wrap"><?php foreach (
    $promoTerpakai
    as $p
): ?><span class="pill pill-info"><?= amankan($p['kode_promo']) ?>: <?= $p['jumlah'] ?> transaksi · <?= formatRupiah(
     (int) $p['potongan'],
 ) ?></span><?php endforeach; ?></div></div><?php endif; ?>
<div class="card glass mb-24"><b>Kursi paling diminati:</b> <?= amankan($kursiFavorit['label'] ?? '-') .
    ($kursiFavorit ? ' · ' . $kursiFavorit['jumlah'] . ' pemesanan' : '') ?></div>

<div class="kpi-grid">
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'money',
        18,
    ) ?></div><div class="kpi-num" style="font-size:1.15rem;"><?= formatRupiah(
    $pendapatan,
) ?></div><div class="kpi-label">Total Pendapatan</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'ticket',
        18,
    ) ?></div><div class="kpi-num" data-countup="<?= $tiketTerjual ?>">0</div><div class="kpi-label">Tiket Terjual</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'chart-bar',
        18,
    ) ?></div><div class="kpi-num"><?= $occupancy ?>%</div><div class="kpi-label">Okupansi Kursi</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'film-slate',
        18,
    ) ?></div><div class="kpi-num" data-countup="<?= count(
    $filmTerlaris,
) ?>">0</div><div class="kpi-label">Film Terjual</div></div>
</div>

<div class="card glass reveal mb-24">
    <h3 style="font-size:1rem; font-weight:800; margin-bottom:8px;"><?= ikon(
        'chart-bar',
        18,
    ) ?> Pendapatan 7 Hari Terakhir</h3>
    <?php if (empty($mingguan)): ?>
        <p class="text-muted" style="font-size:.87rem;">Belum ada data transaksi minggu ini.</p>
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

<div class="table-wrap glass reveal">
    <table class="data-table">
        <thead><tr><th>Kode Tiket</th><th>Total</th><th>Metode</th><th>Status</th><th>Tanggal</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($semuaTiket, 0, 20) as $t): ?>
            <tr>
                <td style="font-family:'Courier New',monospace;"><?= amankan($t->getKodeTiket()) ?></td>
                <td><?= formatRupiah($t->getTotalHarga()) ?></td>
                <td><?= amankan($t->getMetodeBayar()) ?></td>
                <td>
                    <?php $kelas = match ($t->getStatus()) {
                        'lunas' => 'pill-success',
                        'pending' => 'pill-warning',
                        'batal' => 'pill-danger',
                        default => 'pill-muted',
                    }; ?>
                    <span class="pill <?= $kelas ?>"><?= strtoupper($t->getStatus()) ?></span>
                </td>
                <td><?= date('d M Y H:i', strtotime($t->getDibuatPada())) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
