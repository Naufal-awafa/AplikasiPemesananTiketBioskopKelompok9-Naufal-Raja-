<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Studio.php';

$user = wajibLogin(['kasir', 'admin']);

$tiketId = (int) ($_GET['id'] ?? 0);
$tiket = Tiket::cariById($tiketId);
if (!$tiket || $tiket->getKasirId() === null) {
    header('Location: walkin.php');
    exit();
}

$jadwal = Jadwal::cariById($tiket->getJadwalId());
$film = $jadwal?->getFilm();
$studio = $jadwal?->getStudio();

$petaKursi = [];
if ($studio) {
    foreach (Kursi::untukStudio($studio->getId()) as $k) {
        $petaKursi[$k->getId()] = $k->getLabel();
    }
}
$labelKursi = array_map(fn($id) => $petaKursi[$id] ?? '-', $tiket->getKursiIds());

$kembalian = (int) ($_GET['kembalian'] ?? 0);
$stmtProduk = Database::getInstance()->getKoneksi()->prepare('SELECT pp.*,p.nama FROM pesanan_produk pp JOIN produk p ON p.id=pp.produk_id WHERE pp.tiket_id=? ORDER BY pp.id');
$stmtProduk->execute([$tiket->getId()]);
$produkPesanan = $stmtProduk->fetchAll();

$judulHalaman = 'Struk Transaksi — ' . $tiket->getKodeTiket();
require __DIR__ . '/../includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<section class="section container">
    <div class="alert alert-success reveal show mb-24 no-print" style="max-width:460px; margin:0 auto 24px;">
        <?= ikon('check', 16) ?> Transaksi berhasil dicatat. Struk siap dicetak / diunduh untuk pembeli.
    </div>

    <div class="eticket glass reveal print-receipt" id="struk-cetak">
        <div class="eticket-head">
            <h3><?= ikon('film-slate', 18) ?> Sineverse Cinema</h3>
            <p>Struk Pembelian Tiket (Walk-in / Loket)</p>
        </div>
        <div class="eticket-body">
            <div class="eticket-qr" id="qrcode"></div>
            <div class="eticket-code"><?= amankan($tiket->getKodeTiket()) ?></div>

            <div class="eticket-details">
                <div><div class="label">Film</div><div class="value"><?= amankan(
                    $film?->getJudul() ?? '-',
                ) ?></div></div>
                <div><div class="label">Studio</div><div class="value"><?= amankan(
                    $studio?->getNama() ?? '-',
                ) ?> (<?= amankan($studio?->getTipe() ?? '') ?>)</div></div>
                <div><div class="label">Tanggal</div><div class="value"><?= $jadwal
                    ? formatTanggalIndo($jadwal->getTanggal())
                    : '-' ?></div></div>
                <div><div class="label">Jam</div><div class="value"><?= amankan(
                    $jadwal?->getJam() ?? '-',
                ) ?></div></div>
                <div><div class="label">Kursi</div><div class="value"><?= amankan(
                    implode(', ', $labelKursi),
                ) ?></div></div>
                <?php if ($produkPesanan): ?>
                    <div style="grid-column:1/-1"><div class="label">Snack &amp; Minuman</div><div class="value"><?php foreach ($produkPesanan as $item): ?><div><?= amankan($item['nama']) ?> (<?= amankan(ucfirst($item['ukuran'])) ?>) × <?= (int) $item['jumlah'] ?> — <?= formatRupiah((int) $item['harga_satuan'] * (int) $item['jumlah']) ?></div><?php endforeach; ?></div></div>
                    <div><div class="label">Subtotal Tiket</div><div class="value"><?= formatRupiah($tiket->getSubtotalHarga()) ?></div></div>
                    <div><div class="label">Subtotal Snack</div><div class="value"><?= formatRupiah($tiket->getTotalProduk()) ?></div></div>
                <?php endif; ?>
                <div><div class="label">Metode Bayar</div><div class="value"><?= amankan(
                    $tiket->getMetodeBayar(),
                ) ?></div></div>
                <div><div class="label">Total Bayar</div><div class="value"><?= formatRupiah(
                    $tiket->getTotalHarga(),
                ) ?></div></div>
                <?php if ($kembalian > 0): ?>
                    <div><div class="label">Kembalian</div><div class="value"><?= formatRupiah(
                        $kembalian,
                    ) ?></div></div>
                <?php endif; ?>
                <div><div class="label">Dilayani Kasir</div><div class="value"><?= amankan(
                    $user->getNama(),
                ) ?></div></div>
                <div><div class="label">Waktu Transaksi</div><div class="value"><?= date(
                    'd M Y H:i',
                    strtotime($tiket->getDibuatPada()),
                ) ?></div></div>
            </div>

            <div class="eticket-status <?= $tiket->getStatus() ?>"><?= strtoupper($tiket->getStatus()) ?></div>
        </div>
    </div>

    <div class="text-center mt-32 reveal no-print">
        <button onclick="window.print()" class="btn btn-primary"><?= ikon(
            'print',
            16,
        ) ?> Cetak / Simpan sebagai PDF</button>
        <a href="unduh-struk.php?id=<?= $tiket->getId() ?>" class="btn btn-outline"><?= ikon(
    'download',
    16,
) ?> Unduh Struk</a>
        <a href="walkin.php" class="btn btn-ghost">Transaksi Baru</a>
    </div>
</section>

<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "<?= amankan($tiket->getKodeQr()) ?>",
        width: 160, height: 160,
        colorDark: "#08060f", colorLight: "#ffffff",
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
