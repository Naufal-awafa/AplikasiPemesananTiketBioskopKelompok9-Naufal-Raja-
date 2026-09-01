<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Studio.php';
$user = wajibLogin(['customer', 'admin', 'kasir', 'manajer']);

$jadwalId = (int) ($_GET['jadwal_id'] ?? 0);
$jadwal = Jadwal::cariById($jadwalId);
if (!$jadwal || !$jadwal->masihBisaDipesan()) {
    $_SESSION['error_global'] = 'Jadwal tersebut sudah lewat.';
    header('Location: index.php');
    exit();
}

$film = $jadwal->getFilm();
$studio = $jadwal->getStudio();
$kursi = Kursi::untukStudio($studio->getId());
$terpesan = $jadwal->getKursiTerpesan($user->getId());

$hargaReguler = $film->hitungHargaTiket($studio->getTipe());
$hargaVip = (int) round($hargaReguler * 1.35, -3);

// Susun kursi per baris untuk grid tampilan
$kursiPerBaris = [];
foreach ($kursi as $k) {
    $kursiPerBaris[substr($k->getLabel(), 0, 1)][] = $k;
}

$judulHalaman = 'Pilih Kursi — ' . $film->getJudul();
require __DIR__ . '/includes/header.php';
?>

<section class="section container booking-page">
    <?php if (!empty($_SESSION['error_booking'])): ?>
        <div class="alert alert-error reveal" role="alert"><?= amankan($_SESSION['error_booking']) ?></div>
        <?php unset($_SESSION['error_booking']); ?>
    <?php endif; ?>
    <?php if ($user->getDiskonJabatanPersen() > 0): ?>
        <div class="alert alert-success reveal" role="status">
            Benefit <?= amankan(
                $user->getLabelPeran(),
            ) ?>: diskon <?= $user->getDiskonJabatanPersen() ?>% akan diterapkan otomatis saat checkout.
        </div>
    <?php endif; ?>
    <div class="section-head reveal">
        <div>
            <h2>Pilih Kursi — <?= amankan($film->getJudul()) ?></h2>
            <p><?= amankan($studio->getNama()) ?> (<?= amankan($studio->getTipe()) ?>) &middot; <?= formatTanggalIndo(
    $jadwal->getTanggal(),
) ?> &middot; <?= amankan($jadwal->getJam()) ?></p>
        </div>
        <a href="film-detail.php?id=<?= $film->getId() ?>" class="btn btn-ghost btn-sm">&larr; Ganti Jadwal</a>
    </div>

    <form method="POST" action="checkout.php" id="form-booking">
        <input type="hidden" name="jadwal_id" value="<?= $jadwal->getId() ?>">
        <input type="hidden" name="kursi_ids" id="kursi-terpilih" value="">

        <div class="grid-2" style="grid-template-columns: 1fr 320px; align-items:flex-start;">
            <div class="card glass reveal">
                <div class="screen-curve"></div>
                <div class="seat-map">
                    <?php foreach ($kursiPerBaris as $labelBaris => $daftarKursi): ?>
                        <div class="seat-row">
                            <span class="row-label"><?= amankan($labelBaris) ?></span>
                            <?php foreach ($daftarKursi as $k):
                                $isTaken = in_array($k->getId(), $terpesan, true); ?>
                                <div class="seat <?= $k->getTipe() ?> <?= $isTaken ? 'taken' : '' ?>"
                                     data-id="<?= $k->getId() ?>"
                                     data-label="<?= amankan($k->getLabel()) ?>"
                                     role="button"
                                     tabindex="<?= $isTaken ? '-1' : '0' ?>"
                                     aria-pressed="false"
                                     aria-label="Kursi <?= amankan($k->getLabel()) ?> (<?= $k->getTipe() === 'vip'
     ? 'VIP'
     : 'Reguler' ?>)<?= $isTaken ? ' - sudah terisi' : '' ?>"
                                     title="Kursi <?= amankan($k->getLabel()) ?> (<?= $k->getTipe() === 'vip'
     ? 'VIP'
     : 'Reguler' ?>)">
                                    <?= $k->getLabel() ?>
                                </div>
                            <?php
                            endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="seat-legend">
                    <span><span class="legend-box" style="background:rgba(255,255,255,.06); border:1px solid var(--border);"></span> Reguler (<?= formatRupiah(
                        $hargaReguler,
                    ) ?>)</span>
                    <span><span class="legend-box" style="background:rgba(255,176,32,.15); border:1px solid rgba(255,176,32,.4);"></span> VIP (<?= formatRupiah(
                        $hargaVip,
                    ) ?>)</span>
                    <span><span class="legend-box" style="background:var(--accent-grad);"></span> Dipilih</span>
                    <span><span class="legend-box" style="background:rgba(255,92,122,.2);"></span> Terisi</span>
                </div>
            </div>

            <div class="booking-summary glass card reveal">
                <h3>Ringkasan Pesanan</h3>
                <div id="ringkasan-kursi">
                    <div class="text-muted" style="font-size:.85rem;">Belum ada kursi dipilih</div>
                </div>
                <div class="summary-total">
                    <span>Subtotal</span>
                    <span class="amount" id="ringkasan-total">Rp0</span>
                </div>
                <button type="submit" id="btn-lanjut-checkout" class="btn btn-primary btn-block mt-24" disabled>Lanjut ke Pembayaran</button>
            </div>
        </div>
    </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
