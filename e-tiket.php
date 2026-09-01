<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Tiket.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Studio.php';

$user = wajibLogin(['customer', 'kasir', 'admin', 'manajer']);

$tiketId = (int) ($_GET['id'] ?? 0);
$tiket = Tiket::cariById($tiketId);
if (!$tiket) {
    header('Location: index.php');
    exit();
}

// Customer dan manajer hanya dapat melihat tiket pribadi. Admin/kasir tetap
// dapat membuka tiket lain untuk kebutuhan operasional yang sudah ada.
if (in_array($user->getRole(), ['customer', 'manajer'], true) && $tiket->getCustomerId() !== $user->getId()) {
    header('Location: riwayat.php');
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
$stmtProduk = Database::getInstance()
    ->getKoneksi()
    ->prepare('SELECT pp.*,p.nama FROM pesanan_produk pp JOIN produk p ON p.id=pp.produk_id WHERE pp.tiket_id=?');
$stmtProduk->execute([$tiketId]);
$produkPesanan = $stmtProduk->fetchAll();

$notifikasi = $_SESSION['pesan_notifikasi'] ?? null;
unset($_SESSION['pesan_notifikasi'], $_SESSION['pembayaran_berhasil']);

$judulHalaman = 'E-Tiket — ' . ($film ? $film->getJudul() : 'Sineverse Cinema');
require __DIR__ . '/includes/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<section class="section container">
    <?php if ($notifikasi): ?>
        <div class="alert <?= $tiket->getStatus() === 'lunas'
            ? 'alert-success'
            : 'alert-error' ?> reveal show" style="max-width:460px; margin:0 auto 24px;" data-autohide>
            <?= amankan($notifikasi) ?>
        </div>
    <?php endif; ?>

    <div class="eticket glass reveal">
        <div class="eticket-head">
            <h3><?= amankan($film?->getJudul() ?? '-') ?></h3>
            <p><?= amankan($studio?->getNama() ?? '-') ?> &middot; <?= amankan($studio?->getTipe() ?? '') ?></p>
        </div>
        <div class="eticket-body">
            <div class="eticket-qr" id="qrcode"></div>
            <div class="eticket-code"><?= amankan($tiket->getKodeTiket()) ?></div>

            <div class="eticket-details">
                <div><div class="label">Tanggal</div><div class="value"><?= $jadwal
                    ? formatTanggalIndo($jadwal->getTanggal())
                    : '-' ?></div></div>
                <div><div class="label">Jam</div><div class="value"><?= amankan(
                    $jadwal?->getJam() ?? '-',
                ) ?></div></div>
                <div><div class="label">Kursi</div><div class="value"><?= amankan(
                    implode(', ', $labelKursi),
                ) ?></div></div>
                <?php if ($tiket->getPotonganJabatan() > 0): ?>
                    <div><div class="label">Diskon Jabatan</div><div class="value"><?= $tiket->getDiskonJabatanPersen() ?>% (-<?= formatRupiah(
    $tiket->getPotonganJabatan(),
) ?>)</div></div>
                <?php endif; ?>
                <?php if ($tiket->getPotonganPromo() > 0): ?>
                    <div><div class="label">Promo <?= amankan(
                        $tiket->getKodePromo(),
                    ) ?></div><div class="value">-<?= formatRupiah($tiket->getPotonganPromo()) ?></div></div>
                <?php endif; ?>
                <?php if (
                    $tiket->getPotonganPoin() > 0
                ): ?><div><div class="label">Poin digunakan</div><div class="value"><?= $tiket->getPoinDigunakan() ?> (-<?= formatRupiah(
     $tiket->getPotonganPoin(),
 ) ?>)</div></div><?php endif; ?>
                <?php if ($produkPesanan): ?><div><div class="label">Snack</div><div class="value"><?php foreach (
    $produkPesanan
    as $p
):
    amankan($p['nama']) ?> (<?= amankan(ucfirst($p['ukuran'] ?? 'regular')) ?>) ×<?= $p['jumlah'] ?><br><?php
endforeach; ?></div></div><?php endif; ?>
                <div><div class="label">Total Bayar</div><div class="value"><?= formatRupiah(
                    $tiket->getTotalHarga(),
                ) ?></div></div>
                <div><div class="label">Metode</div><div class="value"><?= amankan(
                    $tiket->getMetodeBayar(),
                ) ?></div></div>
                <div><div class="label">Kode QR</div><div class="value" style="font-size:.7rem;"><?= amankan(
                    substr($tiket->getKodeQr(), 0, 12),
                ) ?>&hellip;</div></div>
            </div>

            <div class="eticket-status <?= $tiket->getStatus() ?>"><?= strtoupper($tiket->getStatus()) ?></div>
        </div>
    </div>

    <div class="text-center mt-32 reveal">
        <a href="riwayat.php" class="btn btn-ghost">Lihat Semua Riwayat</a>
        <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
    </div>
</section>

<script>
    new QRCode(document.getElementById("qrcode"), {
        text: "<?= amankan($tiket->getKodeQr()) ?>",
        width: 160,
        height: 160,
        colorDark: "#08060f",
        colorLight: "#ffffff",
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
