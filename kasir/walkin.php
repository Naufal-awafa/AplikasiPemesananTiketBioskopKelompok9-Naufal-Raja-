<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Studio.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/TransaksiProduk.php';
require_once __DIR__ . '/../core/PembayaranTunai.php';
require_once __DIR__ . '/../core/PembayaranTransferBank.php';
require_once __DIR__ . '/../core/PembayaranEwallet.php';
require_once __DIR__ . '/../core/PembayaranKartuKredit.php';
require_once __DIR__ . '/../core/SistemPembayaran.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibLogin(['kasir']);
$pdo = Database::getInstance()->getKoneksi();
FiturBioskop::pastikanShiftHarian($user->getId());
$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'tiket') === 'snack' ? 'snack' : 'tiket';
$jadwalId = (int) ($_GET['jadwal_id'] ?? ($_POST['jadwal_id'] ?? 0));
$jadwal = $jadwalId ? Jadwal::cariById($jadwalId) : null;
$produkList = TransaksiProduk::daftarAktif();
$pesanError = '';

function pembayaranKasir(string $metode, int $total, int $tunai): LayananPembayaran
{
    return match ($metode) {
        'transfer' => new PembayaranTransferBank($total, $_POST['bank'] ?? 'BCA'),
        'ewallet' => new PembayaranEwallet($total, $_POST['provider_ewallet'] ?? 'OVO'),
        'kartu' => new PembayaranKartuKredit($total, $_POST['nomor_kartu'] ?? '0000'),
        default => new PembayaranTunai($total, $tunai),
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'proses_kasir') {
    $metode = (string) ($_POST['metode_bayar'] ?? 'tunai');
    $uangDiterima = max(0, (int) ($_POST['uang_diterima'] ?? 0));
    try {
        $pesananProduk = TransaksiProduk::siapkan(is_array($_POST['produk_qty'] ?? null) ? $_POST['produk_qty'] : [], is_array($_POST['produk_ukuran'] ?? null) ? $_POST['produk_ukuran'] : []);
        $totalTiket = 0;
        $kursiIds = [];
        $petaKursi = [];
        if ($mode === 'tiket') {
            $kursiIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $_POST['kursi_ids'] ?? '')))));
            if (!$jadwal || !$jadwal->masihBisaDipesan() || !$kursiIds || array_intersect($kursiIds, $jadwal->getKursiTerpesan())) {
                throw new RuntimeException('Jadwal atau kursi tidak lagi tersedia.');
            }
            $film = $jadwal->getFilm();
            $studio = $jadwal->getStudio();
            $hargaReguler = $film->hitungHargaTiket($studio->getTipe());
            $hargaVip = (int) round($hargaReguler * 1.35, -3);
            foreach (Kursi::untukStudio($studio->getId()) as $kursi) {
                $petaKursi[$kursi->getId()] = $kursi;
            }
            foreach ($kursiIds as $kursiId) {
                if (!isset($petaKursi[$kursiId])) {
                    throw new RuntimeException('Pilihan kursi tidak valid.');
                }
                $totalTiket += $petaKursi[$kursiId]->getTipe() === 'vip' ? $hargaVip : $hargaReguler;
            }
        } elseif (!$pesananProduk['items']) {
            throw new RuntimeException('Pilih minimal satu snack atau minuman.');
        }

        $tiketTerkait = null;
        $kodeTiketTerkait = trim((string) ($_POST['kode_tiket'] ?? ''));
        if ($mode === 'snack' && $kodeTiketTerkait !== '') {
            $tiketTerkait = Tiket::cariByKode($kodeTiketTerkait);
            if (!$tiketTerkait || in_array($tiketTerkait->getStatus(), ['pending', 'batal'], true)) {
                throw new RuntimeException('Kode tiket tidak ditemukan atau belum aktif.');
            }
        }

        $total = $totalTiket + (int) $pesananProduk['total'];
        $pembayaran = pembayaranKasir($metode, $total, $uangDiterima);
        if (!$pembayaran->proses()) {
            throw new RuntimeException($metode === 'tunai' ? 'Uang diterima kurang ' . formatRupiah(max(0, $total - $uangDiterima)) . '.' : 'Pembayaran gagal diproses.');
        }

        $pdo->beginTransaction();
        if ($mode === 'tiket') {
            $kodeTiket = Tiket::buatKodeTiketBaru();
            $tiket = new Tiket(null, $kodeTiket, $jadwal->getId(), null, $kursiIds, $total, $pembayaran->getLabelMetode(), 'terpakai', '', '', $user->getId(), 'kasir', $totalTiket, 0, 0, '', 0, 0, 0, (int) $pesananProduk['total']);
            $tiket->simpan();
            $detail = $pdo->prepare('INSERT INTO detail_tiket(tiket_id,jadwal_id,kursi_id,harga) VALUES(?,?,?,?)');
            foreach ($kursiIds as $kursiId) {
                $harga = $petaKursi[$kursiId]->getTipe() === 'vip' ? $hargaVip : $hargaReguler;
                $detail->execute([$tiket->getId(), $jadwal->getId(), $kursiId, $harga]);
            }
            TransaksiProduk::simpanUntukTiket($tiket->getId(), $pesananProduk['items']);
            SistemPembayaran::catatTransaksi($tiket->getId(), $pembayaran->getKodeTransaksi(), $total, $pembayaran->getLabelMetode(), 'sukses');
            FiturBioskop::audit($user->getId(), 'walkin_masuk', 'tiket', $tiket->getId(), $kodeTiket . ' — tiket dan snack');
            $tujuan = 'struk.php?id=' . $tiket->getId();
        } else {
            $transaksiId = TransaksiProduk::buatPenjualan($user->getId(), $tiketTerkait?->getId(), (string) ($_POST['nama_pelanggan'] ?? ''), $pesananProduk['items'], $total, $pembayaran->getLabelMetode());
            FiturBioskop::audit($user->getId(), 'penjualan_snack', 'transaksi_produk', $transaksiId, $kodeTiketTerkait !== '' ? 'Terkait tiket ' . $kodeTiketTerkait : 'Pembelian snack mandiri');
            $tujuan = 'struk-snack.php?id=' . $transaksiId;
        }
        $pdo->commit();
        $kembalian = $metode === 'tunai' && $pembayaran instanceof PembayaranTunai ? $pembayaran->getKembalian() : 0;
        header('Location: ' . $tujuan . '&kembalian=' . $kembalian);
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pesanError = $e->getMessage();
    }
}

$daftarJadwal = Jadwal::semua();
$judulHalaman = 'Kasir Walk-in — Sineverse';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'walkin';
require __DIR__ . '/../includes/dash-open.php';

function tampilkanProdukKasir(array $produkList): void
{
    global $base;
    ?><div class="product-order-grid cashier-product-grid">
    <?php foreach ($produkList as $produk):
        $pilihan = RincianHarga::pilihanUkuran((string) $produk['kategori']);
        $gambar = trim((string) ($produk['gambar'] ?? ''));
        $punyaGambar = $gambar !== '' && is_file(__DIR__ . '/../' . $gambar); ?>
        <div class="product-order-item cashier-product-item">
            <span class="product-order-visual"><?php if ($punyaGambar): ?><img src="<?= $base . amankan($gambar) ?>" alt=""><?php else: ?><?= ikon(($produk['kategori'] ?? '') === 'minuman' ? 'drink' : (($produk['kategori'] ?? '') === 'combo' ? 'combo' : 'food'), 22) ?><?php endif; ?></span>
            <span class="product-order-copy"><b><?= amankan($produk['nama']) ?></b><small>Stok <?= (int) $produk['stok'] ?></small></span>
            <select class="form-control product-size-select cashier-size" name="produk_ukuran[<?= (int) $produk['id'] ?>]" aria-label="Ukuran <?= amankan($produk['nama']) ?>"><?php foreach ($pilihan as $kode => $opsi): $harga = RincianHarga::hargaUkuran((int) $produk['harga'], (string) $produk['kategori'], $kode); ?><option value="<?= amankan($kode) ?>" data-price="<?= $harga ?>"><?= amankan($opsi['label']) ?> — <?= formatRupiah($harga) ?></option><?php endforeach; ?></select>
            <input class="form-control product-qty-input cashier-qty" type="number" name="produk_qty[<?= (int) $produk['id'] ?>]" value="0" min="0" max="10" aria-label="Jumlah <?= amankan($produk['nama']) ?>">
        </div>
    <?php endforeach; ?></div><?php
}

function tampilkanPembayaranKasir(): void
{
    ?><div class="payment-options">
        <label class="payment-option checked" data-metode="tunai"><input type="radio" name="metode_bayar" value="tunai" checked hidden><span class="icon"><?= ikon('money', 20) ?></span><span><span class="name">Tunai</span><br><span class="desc">Bayar langsung di kasir</span></span></label>
        <label class="payment-option" data-metode="transfer"><input type="radio" name="metode_bayar" value="transfer" hidden><span class="icon"><?= ikon('bank', 20) ?></span><span><span class="name">Transfer Bank</span><br><span class="desc">BCA / Mandiri / BNI</span></span></label>
        <label class="payment-option" data-metode="ewallet"><input type="radio" name="metode_bayar" value="ewallet" hidden><span class="icon"><?= ikon('smartphone', 20) ?></span><span><span class="name">E-Wallet</span><br><span class="desc">OVO / GoPay / DANA</span></span></label>
        <label class="payment-option" data-metode="kartu"><input type="radio" name="metode_bayar" value="kartu" hidden><span class="icon"><?= ikon('card', 20) ?></span><span><span class="name">Kartu</span><br><span class="desc">Debit atau kredit via EDC</span></span></label>
    </div>
    <div id="detail-tunai" class="payment-detail-field mt-16"><div class="form-group"><label>Uang Diterima (Rp)</label><input type="number" name="uang_diterima" class="form-control" min="0" placeholder="cth. 100000"></div></div>
    <div id="detail-transfer" class="payment-detail-field mt-16" style="display:none"><div class="form-group"><label>Bank Tujuan</label><select name="bank" class="form-control"><option>BCA</option><option>Mandiri</option><option>BNI</option><option>BRI</option></select></div></div>
    <div id="detail-ewallet" class="payment-detail-field mt-16" style="display:none"><div class="form-group"><label>Provider E-Wallet</label><select name="provider_ewallet" class="form-control"><option>OVO</option><option>GoPay</option><option>DANA</option><option>ShopeePay</option></select></div></div>
    <div id="detail-kartu" class="payment-detail-field mt-16" style="display:none"><div class="form-group"><label>4 Digit Terakhir Kartu</label><input type="text" name="nomor_kartu" class="form-control" maxlength="4" placeholder="cth. 1234"></div></div><?php
}
?>

<div class="section-head reveal"><div><h2>Kasir Tiket &amp; Snack</h2><p>Layani tiket bersama snack, atau transaksi snack saja tanpa harus membeli tiket baru.</p></div></div>
<div class="cashier-mode-tabs reveal mb-24">
    <a href="walkin.php" class="cashier-mode-tab <?= $mode === 'tiket' ? 'active' : '' ?>"><?= ikon('ticket', 18) ?><span><b>Tiket + Snack</b><small>Snack bersifat opsional</small></span></a>
    <a href="walkin.php?mode=snack" class="cashier-mode-tab <?= $mode === 'snack' ? 'active' : '' ?>"><?= ikon('food', 18) ?><span><b>Snack Saja</b><small>Bisa ditautkan ke tiket online</small></span></a>
</div>
<?php if ($pesanError !== ''): ?><div class="alert alert-error reveal show mb-24"><?= ikon('alert-octagon', 16) ?> <?= amankan($pesanError) ?></div><?php endif; ?>

<?php if ($mode === 'tiket' && !$jadwal): ?>
<div class="card glass reveal"><div class="section-head"><div><h3>Pilih Jadwal Tayang</h3><p>Setelah memilih jadwal, kasir dapat memilih kursi dan menambahkan snack.</p></div></div><div class="grid-3"><?php foreach ($daftarJadwal as $j): $f=$j->getFilm(); $s=$j->getStudio(); ?><a href="walkin.php?jadwal_id=<?= $j->getId() ?>" class="card glass cashier-schedule-card"><b><?= amankan($f?->getJudul() ?? '-') ?></b><small><?= amankan($s?->getNama() ?? '') ?> · <?= formatTanggalIndo($j->getTanggal()) ?> <?= amankan($j->getJam()) ?></small></a><?php endforeach; ?></div></div>
<?php else:
    $film=$jadwal?->getFilm(); $studio=$jadwal?->getStudio(); $kursiPerBaris=[]; $terpesan=[]; $hargaReguler=0; $hargaVip=0;
    if ($mode==='tiket') { $terpesan=$jadwal->getKursiTerpesan(); $hargaReguler=$film->hitungHargaTiket($studio->getTipe()); $hargaVip=(int)round($hargaReguler*1.35,-3); foreach(Kursi::untukStudio($studio->getId()) as $k){$kursiPerBaris[substr($k->getLabel(),0,1)][]=$k;} }
?>
<?php if ($mode==='tiket'): ?><div class="section-head reveal"><p class="text-muted"><?= ikon('film',16) ?> <?= amankan($film->getJudul()) ?> · <?= amankan($studio->getNama()) ?> · <?= formatTanggalIndo($jadwal->getTanggal()) ?> <?= amankan($jadwal->getJam()) ?></p><a href="walkin.php" class="btn btn-ghost btn-sm">Ganti Jadwal</a></div><?php endif; ?>
<form method="POST" id="form-walkin"><input type="hidden" name="aksi" value="proses_kasir"><input type="hidden" name="mode" value="<?= $mode ?>"><input type="hidden" name="jadwal_id" value="<?= $jadwalId ?>"><input type="hidden" name="kursi_ids" id="kursi-terpilih">
<div class="grid-2 cashier-order-layout"><div>
<?php if($mode==='tiket'): ?><div class="card glass reveal mb-24"><h3 class="cashier-card-title">1. Pilih Kursi</h3><div class="screen-curve"></div><div class="seat-map"><?php foreach($kursiPerBaris as $baris=>$daftar): ?><div class="seat-row"><span class="row-label"><?= amankan($baris) ?></span><?php foreach($daftar as $k): $isi=in_array($k->getId(),$terpesan,true); ?><div class="seat <?= $k->getTipe() ?> <?= $isi?'taken':'' ?>" data-id="<?= $k->getId() ?>" data-label="<?= amankan($k->getLabel()) ?>" tabindex="<?= $isi?'-1':'0' ?>"><?= amankan($k->getLabel()) ?></div><?php endforeach; ?></div><?php endforeach; ?></div><div class="seat-legend"><span>Reguler <?= formatRupiah($hargaReguler) ?></span><span>VIP <?= formatRupiah($hargaVip) ?></span></div></div><?php endif; ?>
<?php if($mode==='snack'): ?><div class="card glass reveal mb-24"><h3 class="cashier-card-title">Data Pelanggan <span class="text-muted" style="font-weight:500">(opsional)</span></h3><div class="grid-2"><div class="form-group"><label>Nama Pelanggan</label><input class="form-control" name="nama_pelanggan" placeholder="Nama pembeli"></div><div class="form-group"><label>Kode Tiket Online / QR</label><input class="form-control" name="kode_tiket" placeholder="Kosongkan jika hanya beli snack"><small class="text-muted">Isi bila pelanggan sudah membeli tiket online.</small></div></div></div><?php endif; ?>
<div class="card glass reveal mb-24"><h3 class="cashier-card-title"><?= $mode==='tiket'?'2.':'1.' ?> Pilih Snack &amp; Minuman</h3><p class="text-muted mb-16" style="font-size:.82rem"><?= $mode==='tiket'?'Opsional—boleh lanjut hanya dengan tiket.':'Pilih minimal satu produk.' ?></p><?php tampilkanProdukKasir($produkList); ?></div>
<div class="card glass reveal"><h3 class="cashier-card-title"><?= $mode==='tiket'?'3.':'2.' ?> Metode Pembayaran</h3><?php tampilkanPembayaranKasir(); ?></div>
</div><aside class="booking-summary glass card reveal cashier-summary"><h3>Ringkasan Transaksi</h3><?php if($mode==='tiket'): ?><div id="ringkasan-kursi"><div class="text-muted" style="font-size:.85rem">Belum ada kursi dipilih</div></div><?php endif; ?><div id="ringkasan-produk"><div class="text-muted" style="font-size:.85rem"><?= $mode==='snack'?'Belum ada produk dipilih':'Belum ada snack dipilih' ?></div></div><div class="summary-total"><span>Total</span><span class="amount" id="ringkasan-total">Rp0</span></div><button type="submit" id="btn-lanjut-checkout" class="btn btn-primary btn-block mt-24" <?= $mode==='tiket'?'disabled':'' ?>><?= $mode==='tiket'?'Bayar & Izinkan Masuk':'Bayar Snack' ?></button></aside></div></form>
<script>document.body.dataset.hargaReguler='<?= $hargaReguler ?>';document.body.dataset.hargaVip='<?= $hargaVip ?>';document.body.dataset.ticketTotal='0';document.addEventListener('DOMContentLoaded',()=>{const qtys=[...document.querySelectorAll('.cashier-qty')],sizes=[...document.querySelectorAll('.cashier-size')],productSummary=document.getElementById('ringkasan-produk'),totalEl=document.getElementById('ringkasan-total'),button=document.getElementById('btn-lanjut-checkout'),isSnack=<?= $mode==='snack'?'true':'false' ?>;const money=n=>'Rp'+n.toLocaleString('id-ID');function update(){let productTotal=0,rows=[];qtys.forEach(input=>{const item=input.closest('.cashier-product-item'),select=item.querySelector('.cashier-size'),qty=Math.max(0,parseInt(input.value||'0',10)),option=select.options[select.selectedIndex],price=parseInt(option.dataset.price||'0',10);if(qty){productTotal+=qty*price;rows.push(`<div class="summary-row"><span>${item.querySelector('b').textContent} × ${qty}</span><span>${money(qty*price)}</span></div>`);}});productSummary.innerHTML=rows.length?rows.join(''):`<div class="text-muted" style="font-size:.85rem">${isSnack?'Belum ada produk dipilih':'Belum ada snack dipilih'}</div>`;const ticketTotal=parseInt(document.body.dataset.ticketTotal||'0',10);totalEl.textContent=money(ticketTotal+productTotal);if(isSnack)button.disabled=productTotal===0;}[...qtys,...sizes].forEach(el=>el.addEventListener('input',update));document.addEventListener('ticket-total-change',update);update();});</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/dash-close.php'; require __DIR__ . '/../includes/footer.php'; ?>
