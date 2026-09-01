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
$labelKursi = implode(', ', array_map(fn($id) => $petaKursi[$id] ?? '-', $tiket->getKursiIds()));
$stmtProduk = Database::getInstance()->getKoneksi()->prepare('SELECT pp.*,p.nama FROM pesanan_produk pp JOIN produk p ON p.id=pp.produk_id WHERE pp.tiket_id=? ORDER BY pp.id');
$stmtProduk->execute([$tiket->getId()]);
$produkPesanan = $stmtProduk->fetchAll();
$barisProduk = '';
foreach ($produkPesanan as $item) {
    $barisProduk .= '<tr><td class="label">Snack</td><td class="value">' . htmlspecialchars($item['nama'] . ' (' . ucfirst($item['ukuran']) . ') × ' . $item['jumlah']) . ' — ' . htmlspecialchars(formatRupiah((int) $item['harga_satuan'] * (int) $item['jumlah'])) . '</td></tr>';
}

/**
 * File struk dibuat sebagai dokumen HTML mandiri (self-contained, seluruh CSS
 * inline) sehingga tetap bisa dibuka & dicetak meskipun di-download offline,
 * tanpa bergantung pada aset/CSS aplikasi utama.
 */
$html =
    '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Struk ' .
    htmlspecialchars($tiket->getKodeTiket()) .
    '</title>
<style>
body{font-family: Arial, Helvetica, sans-serif; background:#f4f4f7; padding:30px; color:#111;}
.receipt{max-width:420px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,.12);}
.head{background:linear-gradient(135deg,#1a1a1a,#333333);color:#fff;padding:22px 24px;}
.head h2{margin:0 0 4px;font-size:1.2rem;}
.head p{margin:0;font-size:.8rem;opacity:.85;}

.body{padding:22px 24px;}
.code{text-align:center;font-family:"Courier New",monospace;font-weight:bold;letter-spacing:2px;color:#5b3df0;margin-bottom:18px;font-size:1rem;}
table{width:100%;border-collapse:collapse;font-size:.85rem;}
td{padding:7px 0;border-bottom:1px dashed #e0e0e6;}
td.label{color:#888;width:45%;}
td.value{font-weight:700;text-align:right;}
.status{text-align:center;margin-top:18px;padding:9px;border-radius:999px;background:rgba(255,255,255,.08);color:#c0c0c0;font-weight:bold;font-size:.78rem;letter-spacing:1px;text-transform:uppercase;}
.footer-note{text-align:center;color:#999;font-size:.72rem;margin-top:20px;}
</style></head><body>

<div class="receipt">
    <div class="head"><h2 style="margin:0;color:#fff;font-size:1.2rem;font-weight:800;">Sineverse Cinema</h2><p style="margin:4px 0 0;font-size:.8rem;opacity:.85;">Struk Pembelian Tiket (Walk-in / Loket)</p></div>
    <div class="body">
        <div class="code">' .
    htmlspecialchars($tiket->getKodeTiket()) .
    '</div>
        <table>
            <tr><td class="label">Film</td><td class="value">' .
    htmlspecialchars($film?->getJudul() ?? '-') .
    '</td></tr>
            <tr><td class="label">Studio</td><td class="value">' .
    htmlspecialchars(($studio?->getNama() ?? '-') . ' (' . ($studio?->getTipe() ?? '') . ')') .
    '</td></tr>
            <tr><td class="label">Tanggal</td><td class="value">' .
    htmlspecialchars($jadwal ? formatTanggalIndo($jadwal->getTanggal()) : '-') .
    '</td></tr>
            <tr><td class="label">Jam</td><td class="value">' .
    htmlspecialchars($jadwal?->getJam() ?? '-') .
    '</td></tr>
            <tr><td class="label">Kursi</td><td class="value">' .
    htmlspecialchars($labelKursi) .
    '</td></tr>
            ' . $barisProduk . '
            <tr><td class="label">Metode Bayar</td><td class="value">' .
    htmlspecialchars($tiket->getMetodeBayar()) .
    '</td></tr>
            <tr><td class="label">Total Bayar</td><td class="value">' .
    htmlspecialchars(formatRupiah($tiket->getTotalHarga())) .
    '</td></tr>
            <tr><td class="label">Dilayani Kasir</td><td class="value">' .
    htmlspecialchars($user->getNama()) .
    '</td></tr>
            <tr><td class="label">Waktu Transaksi</td><td class="value">' .
    htmlspecialchars(date('d M Y H:i', strtotime($tiket->getDibuatPada()))) .
    '</td></tr>
        </table>
        <div class="status">' .
    htmlspecialchars(strtoupper($tiket->getStatus())) .
    '</div>
        <p class="footer-note">Tunjukkan struk/kode tiket ini kepada petugas saat masuk studio.</p>
    </div>
</div>
</body></html>';

$namaFile = 'struk-' . $tiket->getKodeTiket() . '.html';

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Content-Length: ' . strlen($html));
echo $html;
exit();
