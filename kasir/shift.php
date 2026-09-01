<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibLogin(['kasir']);
$pdo = Database::getInstance()->getKoneksi();
$aktif = FiturBioskop::pastikanShiftHarian($user->getId());

$stmtRingkasan = $pdo->prepare(
    "SELECT COUNT(*) jumlah_transaksi,
            COALESCE(SUM(total_harga),0) total_penjualan,
            COALESCE(SUM(CASE WHEN metode_bayar LIKE 'Tunai%' THEN total_harga ELSE 0 END),0) total_tunai
     FROM tiket
     WHERE kasir_id=? AND status IN ('lunas','terpakai') AND DATE(dibuat_pada)=CURDATE()",
);
$stmtRingkasan->execute([$user->getId()]);
$ringkasanHariIni = $stmtRingkasan->fetch();

$stmt = $pdo->prepare(
    "SELECT h.*,
            COALESCE(t.jumlah_transaksi,0) jumlah_transaksi,
            COALESCE(t.total_penjualan,0) total_penjualan,
            COALESCE(t.total_tunai,0) total_tunai
     FROM (
        SELECT DATE(mulai) tanggal,MIN(mulai) mulai,MAX(selesai) selesai,MAX(catatan) catatan
        FROM shift_kasir WHERE kasir_id=? GROUP BY DATE(mulai)
     ) h
     LEFT JOIN (
        SELECT DATE(dibuat_pada) tanggal,COUNT(*) jumlah_transaksi,SUM(total_harga) total_penjualan,
               SUM(CASE WHEN metode_bayar LIKE 'Tunai%' THEN total_harga ELSE 0 END) total_tunai
        FROM tiket WHERE kasir_id=? AND status IN ('lunas','terpakai') GROUP BY DATE(dibuat_pada)
     ) t ON t.tanggal=h.tanggal
     ORDER BY h.tanggal DESC LIMIT 30",
);
$stmt->execute([$user->getId(), $user->getId()]);
$riwayat = $stmt->fetchAll();

$judulHalaman = 'Shift Kasir — Sineverse';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'shift';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head">
    <div><h2>Shift Harian Kasir</h2><p>Shift dibuat dan direkap otomatis berdasarkan tanggal transaksi.</p></div>
    <span class="pill pill-success"><?= ikon('check', 14) ?> Aktif otomatis</span>
</div>

<div class="card glass mb-24">
    <div class="flex justify-between gap-16" style="align-items:flex-start;flex-wrap:wrap">
        <div>
            <span class="section-kicker">Shift hari ini</span>
            <h3><?= formatTanggalIndo(date('Y-m-d')) ?></h3>
            <p class="text-muted mt-8" style="font-size:.82rem">Aktif sejak awal hari dan akan ditutup otomatis saat tanggal berganti.</p>
        </div>
        <span class="pill pill-success">Sedang berjalan</span>
    </div>
    <div class="kpi-grid shift-kpi-grid mt-24">
        <div class="kpi-card"><div class="kpi-num"><?= (int) $ringkasanHariIni[
            'jumlah_transaksi'
        ] ?></div><div class="kpi-label">Transaksi hari ini</div></div>
        <div class="kpi-card"><div class="kpi-num" style="font-size:1.15rem"><?= formatRupiah(
            (int) $ringkasanHariIni['total_penjualan'],
        ) ?></div><div class="kpi-label">Total penjualan</div></div>
        <div class="kpi-card"><div class="kpi-num" style="font-size:1.15rem"><?= formatRupiah(
            (int) $ringkasanHariIni['total_tunai'],
        ) ?></div><div class="kpi-label">Penerimaan tunai</div></div>
    </div>
</div>

<div class="table-wrap glass">
    <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Status</th><th>Transaksi</th><th>Total Penjualan</th><th>Tunai</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach ($riwayat as $s):
            $hariIni = date('Y-m-d', strtotime($s['mulai'])) === date('Y-m-d'); ?>
            <tr>
                <td><?= formatTanggalIndo(date('Y-m-d', strtotime($s['mulai']))) ?></td>
                <td><span class="pill <?= $hariIni ? 'pill-success' : 'pill-muted' ?>"><?= $hariIni
    ? 'Aktif'
    : 'Selesai' ?></span></td>
                <td><?= (int) $s['jumlah_transaksi'] ?></td>
                <td><?= formatRupiah((int) $s['total_penjualan']) ?></td>
                <td><?= formatRupiah((int) $s['total_tunai']) ?></td>
                <td><?= amankan($s['catatan'] ?? '-') ?></td>
            </tr>
        <?php
        endforeach; ?>
        </tbody>
    </table>
</div>

<?php
require __DIR__ . '/../includes/dash-close.php';
require __DIR__ . '/../includes/footer.php';
 ?>
