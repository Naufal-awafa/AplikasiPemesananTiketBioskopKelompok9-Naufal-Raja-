<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/TransaksiProduk.php';
$user = wajibLogin(['kasir']);
$riwayat = Tiket::untukKasir($user->getId());
$riwayatSnack = TransaksiProduk::untukKasir($user->getId());
$totalHariIni = 0;
foreach ($riwayat as $t) {
    if (date('Y-m-d', strtotime($t->getDibuatPada())) === date('Y-m-d') && in_array($t->getStatus(), ['lunas','terpakai'], true)) $totalHariIni += $t->getTotalHarga();
}
foreach ($riwayatSnack as $s) {
    if (date('Y-m-d', strtotime($s['dibuat_pada'])) === date('Y-m-d') && $s['status'] === 'sukses') $totalHariIni += (int)$s['total_harga'];
}
$judulHalaman='Riwayat Transaksi — Kasir'; require __DIR__.'/../includes/header.php'; $menuAktif='riwayat'; require __DIR__.'/../includes/dash-open.php';
?>
<div class="section-head reveal"><div><h2>Riwayat Transaksi Kasir</h2><p>Tiket walk-in, pesanan gabungan, dan pembelian snack mandiri.</p></div></div>
<div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);max-width:480px"><div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon('receipt',18) ?></div><div class="kpi-num" data-countup="<?= count($riwayat)+count($riwayatSnack) ?>">0</div><div class="kpi-label">Total Transaksi</div></div><div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon('money',18) ?></div><div class="kpi-num" style="font-size:1.1rem"><?= formatRupiah($totalHariIni) ?></div><div class="kpi-label">Pendapatan Hari Ini</div></div></div>
<?php if (!$riwayat && !$riwayatSnack): ?><div class="empty-state glass"><div class="icon"><?= ikon('receipt',44) ?></div><p>Belum ada transaksi yang kamu proses.</p><a href="walkin.php" class="btn btn-primary mt-16">Buat Transaksi</a></div><?php endif; ?>
<?php if ($riwayat): ?><h3 class="mb-16 mt-24">Tiket &amp; Pesanan Gabungan</h3><div class="table-wrap glass reveal"><table class="data-table"><thead><tr><th>Kode Tiket</th><th>Film</th><th>Pesanan</th><th>Total</th><th>Metode</th><th>Waktu</th><th>Aksi</th></tr></thead><tbody><?php foreach($riwayat as $t): $film=Jadwal::cariById($t->getJadwalId())?->getFilm(); ?><tr><td style="font-family:'Courier New',monospace"><?= amankan($t->getKodeTiket()) ?></td><td><?= amankan($film?->getJudul()??'-') ?></td><td><?= count($t->getKursiIds()) ?> kursi<?= $t->getTotalProduk()>0?' + snack':'' ?></td><td><?= formatRupiah($t->getTotalHarga()) ?></td><td><?= amankan($t->getMetodeBayar()) ?></td><td><?= date('d M Y H:i',strtotime($t->getDibuatPada())) ?></td><td class="flex gap-8"><a href="struk.php?id=<?= $t->getId() ?>" class="btn btn-ghost btn-sm">Struk</a><a href="unduh-struk.php?id=<?= $t->getId() ?>" class="btn btn-outline btn-sm">Unduh</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($riwayatSnack): ?><h3 class="mb-16 mt-24">Snack Saja</h3><div class="table-wrap glass reveal"><table class="data-table"><thead><tr><th>Kode</th><th>Pelanggan / Tiket</th><th>Total</th><th>Metode</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead><tbody><?php foreach($riwayatSnack as $s): ?><tr><td style="font-family:'Courier New',monospace"><?= amankan($s['kode_transaksi']) ?></td><td><?= amankan(trim((string)$s['nama_pelanggan'])!==''?$s['nama_pelanggan']:($s['kode_tiket']?:'Pembelian mandiri')) ?></td><td><?= formatRupiah((int)$s['total_harga']) ?></td><td><?= amankan($s['metode_bayar']) ?></td><td><span class="pill pill-success"><?= strtoupper(amankan($s['status'])) ?></span></td><td><?= date('d M Y H:i',strtotime($s['dibuat_pada'])) ?></td><td class="flex gap-8"><a href="struk-snack.php?id=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm">Struk</a><a href="unduh-struk-snack.php?id=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm">Unduh</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php require __DIR__.'/../includes/dash-close.php'; require __DIR__.'/../includes/footer.php'; ?>
