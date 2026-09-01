<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Promo.php';
require_once __DIR__ . '/core/RincianHarga.php';
require_once __DIR__ . '/core/FiturBioskop.php';

$user = wajibLogin(['customer', 'admin', 'kasir', 'manajer']);

$jadwalId = (int) ($_POST['jadwal_id'] ?? ($_GET['jadwal_id'] ?? 0));
$kursiCsv = $_POST['kursi_ids'] ?? ($_GET['kursi_ids'] ?? '');
$kursiIds = array_filter(array_map('intval', explode(',', $kursiCsv)));

$jadwal = Jadwal::cariById($jadwalId);
if (!$jadwal || !$jadwal->masihBisaDipesan() || empty($kursiIds)) {
    header('Location: index.php');
    exit();
}

$film = $jadwal->getFilm();
$studio = $jadwal->getStudio();

// Pastikan kursi masih tersedia sebelum menampilkan checkout.
$sudahTerpesan = $jadwal->getKursiTerpesan($user->getId());
if (array_intersect($kursiIds, $sudahTerpesan)) {
    $_SESSION['error_booking'] = 'Salah satu kursi baru saja dipesan pengguna lain. Silakan pilih ulang.';
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}

$hargaReguler = $film->hitungHargaTiket($studio->getTipe());
$hargaVip = (int) round($hargaReguler * 1.35, -3);

$kodePromo = trim($_POST['kode_promo'] ?? '');
$produkQty = is_array($_POST['produk_qty'] ?? null) ? $_POST['produk_qty'] : [];
$produkUkuran = is_array($_POST['produk_ukuran'] ?? null) ? $_POST['produk_ukuran'] : [];
$poinDiminta = (int) ($_POST['poin_digunakan'] ?? 0);
$rincian = RincianHarga::hitung($jadwal, $kursiIds, $user, $kodePromo, $produkQty, $poinDiminta, $produkUkuran);
if (!$rincian) {
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}
$kursiIds = $rincian['kursi_ids'];
$labelKursi = $rincian['label_kursi'];
$totalHarga = $rincian['subtotal'];
$diskonJabatanPersen = $rincian['diskon_jabatan_persen'];
$potonganJabatan = $rincian['potongan_jabatan'];
$promo = $rincian['promo'];
$potongan = $rincian['potongan_promo'];
$totalAkhir = $rincian['total_akhir'];
$tokenReservasi = FiturBioskop::buatReservasi($user->getId(), $jadwalId, $kursiIds);
if (!$tokenReservasi) {
    $_SESSION['error_booking'] = 'Kursi baru saja direservasi pengguna lain. Silakan pilih ulang.';
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}
$produkList = Database::getInstance()
    ->getKoneksi()
    ->query('SELECT * FROM produk WHERE aktif=1 AND stok>0 ORDER BY kategori,nama')
    ->fetchAll();

$judulHalaman = 'Checkout — ' . $film->getJudul();
require __DIR__ . '/includes/header.php';
?>

<section class="section container">
    <div class="section-head reveal">
        <div><h2>Checkout &amp; Pembayaran</h2><p>Periksa kembali pesananmu, lalu pilih metode pembayaran.</p></div>
    </div>

    <form method="POST" action="payment-process.php">
        <input type="hidden" name="jadwal_id" value="<?= $jadwal->getId() ?>">
        <input type="hidden" name="kursi_ids" value="<?= amankan(implode(',', $kursiIds)) ?>">
        <input type="hidden" name="token_reservasi" value="<?= amankan($tokenReservasi) ?>">
        <?php if ($promo): ?><input type="hidden" name="kode_promo" value="<?= amankan(
    $promo->getKode(),
) ?>"><?php endif; ?>

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
            <div>
                <div class="card glass reveal mb-24">
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                        'ticket',
                        18,
                    ) ?> Detail Pesanan</h3>
                    <div class="summary-row"><span>Film</span><span><?= amankan($film->getJudul()) ?></span></div>
                    <div class="summary-row"><span>Studio</span><span><?= amankan($studio->getNama()) ?> (<?= amankan(
     $studio->getTipe(),
 ) ?>)</span></div>
                    <div class="summary-row"><span>Jadwal</span><span><?= formatTanggalIndo(
                        $jadwal->getTanggal(),
                    ) ?>, <?= amankan($jadwal->getJam()) ?></span></div>
                    <div class="summary-row"><span>Kursi</span><span><?= amankan(
                        implode(', ', $labelKursi),
                    ) ?></span></div>
                </div>

                <div class="card glass reveal mb-24">
                    <h3 style="font-size:1rem;font-weight:800;margin-bottom:8px;"><?= ikon(
                        'sparkles',
                        18,
                    ) ?> Snack &amp; Minuman</h3>
                    <p class="text-muted mb-16" style="font-size:.8rem;">Opsional, diambil bersamaan dengan tiket.</p>
                    <div class="product-order-grid">
                    <?php foreach ($produkList as $produk):

                        $qty = (int) ($produkQty[$produk['id']] ?? 0);
                        $ukuranAktif = strtolower((string) ($produkUkuran[$produk['id']] ?? 'regular'));
                        $pilihanUkuran = RincianHarga::pilihanUkuran((string) $produk['kategori']);
                        if (!isset($pilihanUkuran[$ukuranAktif])) {
                            $ukuranAktif = 'regular';
                        }
                        $gambar = trim((string) ($produk['gambar'] ?? ''));
                        $punyaGambar = $gambar !== '' && is_file(__DIR__ . '/' . $gambar);
                        ?>
                        <div class="product-order-item">
                            <span class="product-order-visual">
                                <?php if ($punyaGambar): ?>
                                    <img src="<?= $base . amankan($gambar) ?>" alt="">
                                <?php else: ?>
                                    <?= ikon(
                                        ($produk['kategori'] ?? '') === 'minuman'
                                            ? 'drink'
                                            : (($produk['kategori'] ?? '') === 'combo'
                                                ? 'combo'
                                                : 'food'),
                                        22,
                                    ) ?>
                                <?php endif; ?>
                            </span>
                            <span class="product-order-copy"><b><?= amankan(
                                $produk['nama'],
                            ) ?></b><small><?= formatRupiah((int) $produk['harga']) ?> · stok <?= (int) $produk[
     'stok'
 ] ?></small></span>
                            <select class="form-control product-size-select" aria-label="Ukuran <?= amankan(
                                $produk['nama'],
                            ) ?>" name="produk_ukuran[<?= (int) $produk['id'] ?>]">
                                <?php foreach ($pilihanUkuran as $kodeUkuran => $opsiUkuran):
                                    $hargaUkuran = RincianHarga::hargaUkuran(
                                        (int) $produk['harga'],
                                        (string) $produk['kategori'],
                                        $kodeUkuran,
                                    ); ?>
                                    <option value="<?= amankan($kodeUkuran) ?>" <?= $ukuranAktif === $kodeUkuran
    ? 'selected'
    : '' ?>><?= amankan($opsiUkuran['label']) ?> — <?= formatRupiah($hargaUkuran) ?></option>
                                <?php
                                endforeach; ?>
                            </select>
                            <input type="number" class="form-control product-qty-input" aria-label="Jumlah <?= amankan(
                                $produk['nama'],
                            ) ?>" name="produk_qty[<?= (int) $produk['id'] ?>]" value="<?= $qty ?>" min="0" max="10">
                        </div>
                    <?php
                    endforeach; ?>
                    </div>
                    <button type="submit" formaction="checkout.php" class="btn btn-outline btn-sm mt-16">Perbarui Pesanan</button>
                </div>

                <div class="card glass reveal mb-24">
                    <h3 style="font-size:1rem;font-weight:800;margin-bottom:8px;"><?= ikon(
                        'trophy',
                        18,
                    ) ?> Poin Loyalitas</h3>
                    <p class="text-muted mb-16" style="font-size:.8rem;">Saldo: <b><?= $user->getPoin() ?> poin</b> · 1 poin = <?= formatRupiah(
     (int) FiturBioskop::pengaturan('nilai_satu_poin', '100'),
 ) ?></p>
                    <div class="flex gap-12"><input type="number" name="poin_digunakan" value="<?= $rincian[
                        'poin_digunakan'
                    ] ?>" min="0" max="<?= $user->getPoin() ?>" class="form-control"><button type="submit" formaction="checkout.php" class="btn btn-outline">Gunakan</button></div>
                </div>

                <div class="card glass reveal mb-24">
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                        'card',
                        18,
                    ) ?> Metode Pembayaran</h3>
                    <div class="payment-options">
                        <label class="payment-option checked" data-metode="transfer">
                            <input type="radio" name="metode_bayar" value="transfer" checked style="display:none;">
                            <span class="icon"><?= ikon('bank', 20) ?></span>
                            <span><span class="name">Transfer Bank</span><br><span class="desc">VA BCA / Mandiri / BNI</span></span>
                        </label>
                        <label class="payment-option" data-metode="ewallet">
                            <input type="radio" name="metode_bayar" value="ewallet" style="display:none;">
                            <span class="icon"><?= ikon('wallet', 20) ?></span>
                            <span><span class="name">E-Wallet</span><br><span class="desc">OVO / GoPay / DANA</span></span>
                        </label>
                        <label class="payment-option" data-metode="kartu">
                            <input type="radio" name="metode_bayar" value="kartu" style="display:none;">
                            <span class="icon"><?= ikon('card', 20) ?></span>
                            <span><span class="name">Kartu Kredit/Debit</span><br><span class="desc">Visa / Mastercard</span></span>
                        </label>
                        <label class="payment-option" data-metode="lainnya">
                            <input type="radio" name="metode_bayar" value="lainnya" style="display:none;">
                            <span class="icon"><?= ikon('sparkles', 20) ?></span>
                            <span><span class="name">Metode Lain</span><br><span class="desc">Simulasi gateway dummy</span></span>
                        </label>
                    </div>

                    <div id="detail-ewallet" class="payment-detail-field mt-16" style="display:none;">
                        <div class="form-group">
                            <label>Pilih Provider</label>
                            <select name="provider_ewallet" class="form-control">
                                <option value="OVO">OVO</option>
                                <option value="GoPay">GoPay</option>
                                <option value="DANA">DANA</option>
                                <option value="ShopeePay">ShopeePay</option>
                            </select>
                        </div>
                    </div>
                    <div id="detail-kartu" class="payment-detail-field mt-16" style="display:none;">
                        <div class="form-group">
                            <label>Nomor Kartu (dummy, tidak divalidasi ke bank manapun)</label>
                            <input type="text" name="nomor_kartu" class="form-control" placeholder="4111 1111 1111 1111" maxlength="19">
                        </div>
                    </div>
                    <p class="text-muted mt-16" style="font-size:.76rem;">*Seluruh proses pembayaran pada sistem ini bersifat simulasi (dummy gateway), tidak terhubung ke penyedia pembayaran sungguhan.</p>
                </div>

                <div class="card glass reveal">
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                        'ticket',
                        18,
                    ) ?> Kode Promo</h3>
                    <div class="flex gap-12">
                        <input type="text" name="kode_promo" class="form-control" placeholder="Contoh: SINE10" value="<?= amankan(
                            $promo?->getKode() ?? '',
                        ) ?>">
                        <button type="submit" formaction="checkout.php" name="terapkan_promo" class="btn btn-outline">Terapkan</button>
                    </div>
                    <?php if ($promo): ?>
                        <div class="alert alert-success mt-16" style="margin-bottom:0;">Promo "<?= amankan(
                            $promo->getKode(),
                        ) ?>" diterapkan: diskon <?= $promo->getDiskonPersen() ?>%</div>
                    <?php elseif (!empty($_POST['kode_promo'])): ?>
                        <div class="alert alert-error mt-16" style="margin-bottom:0;">Kode promo tidak ditemukan / kedaluwarsa.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="booking-summary glass card reveal">
                <h3>Ringkasan Bayar</h3>
                <div class="summary-row"><span>Subtotal (<?= count($kursiIds) ?> kursi)</span><span><?= formatRupiah(
     $totalHarga,
 ) ?></span></div>
                <?php if ($potonganJabatan > 0): ?>
                    <div class="summary-row"><span>Diskon <?= amankan(
                        $user->getLabelPeran(),
                    ) ?> (<?= $diskonJabatanPersen ?>%)</span><span>-<?= formatRupiah($potonganJabatan) ?></span></div>
                <?php endif; ?>
                <?php if ($potongan > 0): ?><div class="summary-row"><span>Diskon Promo</span><span>-<?= formatRupiah(
    $potongan,
) ?></span></div><?php endif; ?>
                <?php if (
                    $rincian['total_produk'] > 0
                ): ?><div class="summary-row"><span>Snack &amp; Minuman</span><span><?= formatRupiah(
    $rincian['total_produk'],
) ?></span></div><?php endif; ?>
                <?php if ($rincian['potongan_poin'] > 0): ?><div class="summary-row"><span><?= $rincian[
    'poin_digunakan'
] ?> Poin</span><span>-<?= formatRupiah($rincian['potongan_poin']) ?></span></div><?php endif; ?>
                <div class="summary-total"><span>Total Bayar</span><span class="amount"><?= formatRupiah(
                    $totalAkhir,
                ) ?></span></div>
                <p class="text-muted mt-8" style="font-size:.72rem;text-align:center;">Kursi ditahan <span id="reservation-countdown" data-minutes="<?= (int) FiturBioskop::pengaturan(
                    'durasi_reservasi',
                    '10',
                ) ?>"></span></p>
                <button type="submit" class="btn btn-primary btn-block mt-24">Bayar Sekarang</button>
            </div>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
