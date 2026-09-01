<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Tiket.php';
require_once __DIR__ . '/../core/Jadwal.php';

$user = wajibLogin(['kasir']);

$riwayat = Tiket::untukKasir($user->getId());
$totalHariIni = 0;
foreach ($riwayat as $t) {
    if (
        date('Y-m-d', strtotime($t->getDibuatPada())) === date('Y-m-d') &&
        in_array($t->getStatus(), ['lunas', 'terpakai'], true)
    ) {
        $totalHariIni += $t->getTotalHarga();
    }
}

$judulHalaman = 'Riwayat Walk-in — Kasir';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'riwayat';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Riwayat Walk-in</h2><p>Seluruh transaksi tiket walk-in yang pernah kamu proses di loket.</p></div>
</div>

<div class="kpi-grid" style="grid-template-columns: repeat(2,1fr); max-width:480px;">
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'ticket',
        18,
    ) ?></div><div class="kpi-num" data-countup="<?= count(
    $riwayat,
) ?>">0</div><div class="kpi-label">Total Transaksi</div></div>
    <div class="kpi-card glass reveal"><div class="kpi-icon"><?= ikon(
        'money',
        18,
    ) ?></div><div class="kpi-num" style="font-size:1.1rem;"><?= formatRupiah(
    $totalHariIni,
) ?></div><div class="kpi-label">Pendapatan Hari Ini</div></div>
</div>

<?php if (empty($riwayat)): ?>
    <div class="empty-state glass">
        <div class="icon"><?= ikon('ticket', 44) ?></div>
        <p>Kamu belum pernah memproses transaksi walk-in.</p>
        <a href="walkin.php" class="btn btn-primary mt-16">Pesan Walk-in Sekarang</a>
    </div>
<?php else: ?>
    <div class="table-wrap glass reveal">
        <table class="data-table">
            <thead>
                <tr><th>Kode Tiket</th><th>Film</th><th>Kursi</th><th>Total</th><th>Metode</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat as $t):

                    $jadwal = Jadwal::cariById($t->getJadwalId());
                    $film = $jadwal?->getFilm();
                    ?>
                    <tr>
                        <td style="font-family:'Courier New',monospace;"><?= amankan($t->getKodeTiket()) ?></td>
                        <td><?= amankan($film?->getJudul() ?? '-') ?></td>
                        <td><?= count($t->getKursiIds()) ?> kursi</td>
                        <td><?= formatRupiah($t->getTotalHarga()) ?></td>
                        <td><?= amankan($t->getMetodeBayar()) ?></td>
                        <td>
                            <?php $kelas = match ($t->getStatus()) {
                                'lunas' => 'pill-success',
                                'batal' => 'pill-danger',
                                'terpakai' => 'pill-muted',
                                default => 'pill-warning',
                            }; ?>
                            <span class="pill <?= $kelas ?>"><?= strtoupper($t->getStatus()) ?></span>
                        </td>
                        <td><?= date('d M Y H:i', strtotime($t->getDibuatPada())) ?></td>
                        <td class="flex gap-8">
                            <a href="struk.php?id=<?= $t->getId() ?>" class="btn btn-ghost btn-sm">Lihat Struk</a>
                            <a href="unduh-struk.php?id=<?= $t->getId() ?>" class="btn btn-outline btn-sm">Unduh</a>
                        </td>
                    </tr>
                <?php
                endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
