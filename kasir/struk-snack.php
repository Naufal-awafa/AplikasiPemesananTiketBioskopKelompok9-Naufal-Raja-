<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/TransaksiProduk.php';
$user = wajibLogin(['kasir', 'admin']);
$transaksi = TransaksiProduk::cari((int) ($_GET['id'] ?? 0));
if (!$transaksi || ($user->getRole() === 'kasir' && (int) $transaksi['kasir_id'] !== $user->getId())) {
    header('Location: walkin.php?mode=snack');
    exit();
}
$kembalian = max(0, (int) ($_GET['kembalian'] ?? 0));
$judulHalaman = 'Struk Snack — ' . $transaksi['kode_transaksi'];
require __DIR__ . '/../includes/header.php';
?>
<section class="section container">
    <div class="alert alert-success reveal show mb-24 no-print" style="max-width:460px;margin:0 auto 24px"><?= ikon('check',16) ?> Transaksi snack berhasil. Stok dan rekap kasir sudah diperbarui.</div>
    <div class="eticket glass reveal print-receipt">
        <div class="eticket-head"><h3><?= ikon('food',18) ?> Sineverse Cinema</h3><p>Struk Snack &amp; Minuman</p></div>
        <div class="eticket-body"><div class="eticket-code"><?= amankan($transaksi['kode_transaksi']) ?></div>
            <div class="eticket-details">
                <?php if (trim((string)$transaksi['nama_pelanggan']) !== ''): ?><div><div class="label">Pelanggan</div><div class="value"><?= amankan($transaksi['nama_pelanggan']) ?></div></div><?php endif; ?>
                <?php if (!empty($transaksi['kode_tiket'])): ?><div><div class="label">Tiket Terkait</div><div class="value"><?= amankan($transaksi['kode_tiket']) ?></div></div><?php endif; ?>
                <div style="grid-column:1/-1"><div class="label">Pesanan</div><div class="value"><?php foreach($transaksi['items'] as $item): ?><div><?= amankan($item['nama']) ?> (<?= amankan(ucfirst($item['ukuran'])) ?>) × <?= (int)$item['jumlah'] ?> — <?= formatRupiah((int)$item['harga_satuan']*(int)$item['jumlah']) ?></div><?php endforeach; ?></div></div>
                <div><div class="label">Metode Bayar</div><div class="value"><?= amankan($transaksi['metode_bayar']) ?></div></div>
                <div><div class="label">Total Bayar</div><div class="value"><?= formatRupiah((int)$transaksi['total_harga']) ?></div></div>
                <?php if($kembalian>0): ?><div><div class="label">Kembalian</div><div class="value"><?= formatRupiah($kembalian) ?></div></div><?php endif; ?>
                <div><div class="label">Waktu</div><div class="value"><?= date('d M Y H:i',strtotime($transaksi['dibuat_pada'])) ?></div></div>
            </div><div class="eticket-status lunas">SUKSES</div>
        </div>
    </div>
    <div class="text-center mt-32 reveal no-print"><button onclick="window.print()" class="btn btn-primary"><?= ikon('print',16) ?> Cetak / Simpan PDF</button> <a href="unduh-struk-snack.php?id=<?= (int)$transaksi['id'] ?>" class="btn btn-outline"><?= ikon('download',16) ?> Unduh Struk</a> <a href="walkin.php?mode=snack" class="btn btn-ghost">Transaksi Baru</a></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
