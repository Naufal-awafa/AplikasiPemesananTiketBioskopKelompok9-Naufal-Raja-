<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Studio.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/LayananPembayaran.php';
require_once __DIR__ . '/../core/PembayaranTunai.php';
require_once __DIR__ . '/../core/PembayaranTransferBank.php';
require_once __DIR__ . '/../core/PembayaranEwallet.php';
require_once __DIR__ . '/../core/PembayaranKartuKredit.php';
require_once __DIR__ . '/../core/SistemPembayaran.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibLogin(['kasir']);

$jadwalId = (int) ($_GET['jadwal_id'] ?? ($_POST['jadwal_id'] ?? 0));
$jadwal = $jadwalId ? Jadwal::cariById($jadwalId) : null;

$hasilTransaksi = null;
$pdo = Database::getInstance()->getKoneksi();
$shiftHariIni = FiturBioskop::pastikanShiftHarian($user->getId());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_walkin') {
    $kursiIds = array_filter(array_map('intval', explode(',', $_POST['kursi_ids'] ?? '')));
    $metode = $_POST['metode_bayar'] ?? 'tunai';
    $uangDiterima = (int) ($_POST['uang_diterima'] ?? 0);

    if (
        $jadwal &&
        $jadwal->masihBisaDipesan() &&
        !array_intersect($kursiIds, $jadwal->getKursiTerpesan()) &&
        !empty($kursiIds)
    ) {
        $film = $jadwal->getFilm();
        $studio = $jadwal->getStudio();
        $hargaReguler = $film->hitungHargaTiket($studio->getTipe());
        $hargaVip = (int) round($hargaReguler * 1.35, -3);

        $petaKursi = [];
        foreach (Kursi::untukStudio($studio->getId()) as $k) {
            $petaKursi[$k->getId()] = $k;
        }

        $total = 0;
        foreach ($kursiIds as $kid) {
            if (isset($petaKursi[$kid])) {
                $total += $petaKursi[$kid]->getTipe() === 'vip' ? $hargaVip : $hargaReguler;
            }
        }

        /**
         * >> DEMONSTRASI POLIMORFISME LEWAT PEWARISAN (sama seperti alur customer),
         * namun di sini aktor yang memicu transaksi adalah Kasir di loket.
         * Kasir kini bisa memilih metode selain tunai (transfer/e-wallet/kartu),
         * dipakai misalnya saat pembeli menyodorkan kartu/QRIS langsung di loket.
         */
        $pembayaran = match ($metode) {
            'transfer' => new PembayaranTransferBank($total, $_POST['bank'] ?? 'BCA'),
            'ewallet' => new PembayaranEwallet($total, $_POST['provider_ewallet'] ?? 'OVO'),
            'kartu' => new PembayaranKartuKredit($total, $_POST['nomor_kartu'] ?? '0000000000000000'),
            default => new PembayaranTunai($total, $uangDiterima),
        };

        $berhasil = $pembayaran->proses();

        if ($berhasil) {
            $kodeTiket = Tiket::buatKodeTiketBaru();
            // Pembelian di loket sekaligus menjadi validasi masuk, sehingga tidak perlu dipindai ulang.
            $tiket = new Tiket(
                null,
                $kodeTiket,
                $jadwal->getId(),
                null,
                $kursiIds,
                $total,
                $pembayaran->getLabelMetode(),
                'terpakai',
                '',
                '',
                $user->getId(),
            );
            $tiket->simpan();
            $stmtDetail = $pdo->prepare(
                'INSERT INTO detail_tiket (tiket_id,jadwal_id,kursi_id,harga) VALUES (?,?,?,?)',
            );
            foreach ($kursiIds as $kid) {
                $stmtDetail->execute([
                    $tiket->getId(),
                    $jadwal->getId(),
                    $kid,
                    $petaKursi[$kid]->getTipe() === 'vip' ? $hargaVip : $hargaReguler,
                ]);
            }
            SistemPembayaran::catatTransaksi(
                $tiket->getId(),
                $pembayaran->getKodeTransaksi(),
                $total,
                $pembayaran->getLabelMetode(),
                'sukses',
            );
            FiturBioskop::audit(
                $user->getId(),
                'walkin_masuk',
                'tiket',
                $tiket->getId(),
                $kodeTiket . ' — langsung tervalidasi',
            );

            $kembalian =
                $metode === 'tunai' && $pembayaran instanceof PembayaranTunai ? $pembayaran->getKembalian() : 0;
            header('Location: struk.php?id=' . $tiket->getId() . '&kembalian=' . $kembalian);
            exit();
        } else {
            $kurang = $metode === 'tunai' ? max(0, $total - $uangDiterima) : 0;
            $hasilTransaksi = ['sukses' => false, 'kurang' => $kurang, 'metode' => $metode];
        }
    }
}

$daftarJadwal = Jadwal::semua();

$judulHalaman = 'Pesan Walk-in — Kasir';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'walkin';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Pesan Tiket Walk-in</h2><p>Pembayaran di loket sekaligus memvalidasi tiket untuk masuk studio.</p></div>
</div>

<?php if ($hasilTransaksi && !$hasilTransaksi['sukses']): ?>
    <div class="alert alert-error reveal show mb-24">
        <?= ikon('alert-octagon', 16) ?> Pembayaran gagal diproses.
        <?php if ($hasilTransaksi['metode'] === 'tunai'): ?>
            Uang diterima kurang <b><?= formatRupiah($hasilTransaksi['kurang']) ?></b>.
        <?php else: ?>
            Silakan coba metode lain atau ulangi transaksi.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$jadwal): ?>
    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;">Pilih Jadwal Tayang</h3>
        <div class="grid-3">
            <?php foreach ($daftarJadwal as $j):

                $f = $j->getFilm();
                $s = $j->getStudio();
                ?>
                <a href="walkin.php?jadwal_id=<?= $j->getId() ?>" class="card glass" style="padding:18px;">
                    <p style="font-weight:700;"><?= amankan($f?->getJudul() ?? '-') ?></p>
                    <p class="text-muted mt-8" style="font-size:.8rem;"><?= amankan(
                        $s?->getNama() ?? '',
                    ) ?> &middot; <?= formatTanggalIndo($j->getTanggal()) ?> <?= amankan($j->getJam()) ?></p>
                </a>
            <?php
            endforeach; ?>
        </div>
    </div>
<?php else:
    $film = $jadwal->getFilm();
    $studio = $jadwal->getStudio();
    $kursi = Kursi::untukStudio($studio->getId());
    $terpesan = $jadwal->getKursiTerpesan();
    $hargaReguler = $film->hitungHargaTiket($studio->getTipe());
    $hargaVip = (int) round($hargaReguler * 1.35, -3);
    $kursiPerBaris = [];
    foreach ($kursi as $k) {
        $kursiPerBaris[substr($k->getLabel(), 0, 1)][] = $k;
    }
    ?>
    <div class="section-head reveal">
        <div><p class="text-muted"><?= ikon('film', 16) ?> <?= amankan($film->getJudul()) ?> &middot; <?= amankan(
     $studio->getNama(),
 ) ?> &middot; <?= formatTanggalIndo($jadwal->getTanggal()) ?> <?= amankan($jadwal->getJam()) ?></p></div>
        <a href="walkin.php" class="btn btn-ghost btn-sm">Ganti Jadwal</a>
    </div>

    <form method="POST" id="form-walkin">
        <input type="hidden" name="aksi" value="proses_walkin">
        <input type="hidden" name="jadwal_id" value="<?= $jadwal->getId() ?>">
        <input type="hidden" name="kursi_ids" id="kursi-terpilih" value="">

        <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
            <div>
                <div class="card glass reveal mb-24">
                    <div class="screen-curve"></div>
                    <div class="seat-map">
                        <?php foreach ($kursiPerBaris as $labelBaris => $daftarKursi): ?>
                            <div class="seat-row">
                                <span class="row-label"><?= amankan($labelBaris) ?></span>
                                <?php foreach ($daftarKursi as $k):
                                    $isTaken = in_array($k->getId(), $terpesan, true); ?>
                                    <div class="seat <?= $k->getTipe() ?> <?= $isTaken
     ? 'taken'
     : '' ?>" data-id="<?= $k->getId() ?>" data-label="<?= amankan($k->getLabel()) ?>"><?= $k->getLabel() ?></div>
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

                <div class="card glass reveal">
                    <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                        'card',
                        18,
                    ) ?> Metode Pembayaran</h3>
                    <div class="payment-options">
                        <label class="payment-option checked" data-metode="tunai">
                            <input type="radio" name="metode_bayar" value="tunai" checked style="display:none;">
                            <span class="icon"><?= ikon('money', 20) ?></span>
                            <span><span class="name">Tunai</span><br><span class="desc">Bayar langsung di loket</span></span>
                        </label>
                        <label class="payment-option" data-metode="transfer">
                            <input type="radio" name="metode_bayar" value="transfer" style="display:none;">
                            <span class="icon"><?= ikon('bank', 20) ?></span>
                            <span><span class="name">Transfer Bank</span><br><span class="desc">VA BCA / Mandiri / BNI</span></span>
                        </label>
                        <label class="payment-option" data-metode="ewallet">
                            <input type="radio" name="metode_bayar" value="ewallet" style="display:none;">
                            <span class="icon"><?= ikon('smartphone', 20) ?></span>
                            <span><span class="name">E-Wallet</span><br><span class="desc">OVO / GoPay / DANA</span></span>
                        </label>
                        <label class="payment-option" data-metode="kartu">
                            <input type="radio" name="metode_bayar" value="kartu" style="display:none;">
                            <span class="icon"><?= ikon('card', 20) ?></span>
                            <span><span class="name">Kartu Debit/Kredit</span><br><span class="desc">Gesek di mesin EDC</span></span>
                        </label>
                    </div>

                    <div id="detail-tunai" class="payment-detail-field mt-16">
                        <div class="form-group">
                            <label>Uang Diterima (Rp)</label>
                            <input type="number" name="uang_diterima" class="form-control" placeholder="cth. 100000">
                        </div>
                    </div>
                    <div id="detail-transfer" class="payment-detail-field mt-16" style="display:none;">
                        <div class="form-group">
                            <label>Bank Tujuan</label>
                            <select name="bank" class="form-control">
                                <option value="BCA">BCA</option>
                                <option value="Mandiri">Mandiri</option>
                                <option value="BNI">BNI</option>
                                <option value="BRI">BRI</option>
                            </select>
                        </div>
                    </div>
                    <div id="detail-ewallet" class="payment-detail-field mt-16" style="display:none;">
                        <div class="form-group">
                            <label>Provider E-Wallet</label>
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
                            <label>4 Digit Terakhir Kartu (dummy)</label>
                            <input type="text" name="nomor_kartu" class="form-control" placeholder="cth. 1234" maxlength="4">
                        </div>
                    </div>
                    <p class="text-muted mt-8" style="font-size:.76rem;">*Seluruh proses pembayaran non-tunai bersifat simulasi (dummy gateway).</p>
                </div>
            </div>

            <div class="booking-summary glass card reveal">
                <h3>Ringkasan Transaksi</h3>
                <div id="ringkasan-kursi"><div class="text-muted" style="font-size:.85rem;">Belum ada kursi dipilih</div></div>
                <div class="summary-total"><span>Total</span><span class="amount" id="ringkasan-total">Rp0</span></div>
                <button type="submit" id="btn-lanjut-checkout" class="btn btn-primary btn-block mt-24" disabled>Bayar &amp; Izinkan Masuk</button>
            </div>
        </div>
    </form>
    <script>document.body.dataset.hargaReguler = "<?= $hargaReguler ?>"; document.body.dataset.hargaVip = "<?= $hargaVip ?>";</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
