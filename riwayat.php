<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Tiket.php';
require_once __DIR__ . '/core/Jadwal.php';

$user = wajibLogin(['customer', 'admin', 'kasir', 'manajer']);
$tiketList = Tiket::untukCustomer($user->getId());

$judulHalaman = 'Riwayat Pemesanan — Sineverse Cinema';
require __DIR__ . '/includes/header.php';
?>

<section class="section container">
    <?php if (!empty($_SESSION['error_global'])): ?><div class="alert alert-error"><?= amankan(
    $_SESSION['error_global'],
) ?></div><?php unset($_SESSION['error_global']);endif; ?>
    <?php if (!empty($_SESSION['success_global'])): ?><div class="alert alert-success"><?= amankan(
    $_SESSION['success_global'],
) ?></div><?php unset($_SESSION['success_global']);endif; ?>
    <div class="section-head reveal">
        <div><h2>Riwayat Pemesanan Pribadi</h2><p>Semua tiket yang pernah kamu pesan menggunakan akun ini.</p></div>
    </div>

    <?php if (empty($tiketList)): ?>
        <div class="empty-state glass">
            <div class="icon"><?= ikon('ticket', 44) ?></div>
            <p>Kamu belum pernah memesan tiket.</p>
            <a href="index.php" class="btn btn-primary mt-16">Pesan Tiket Sekarang</a>
        </div>
    <?php else: ?>
        <div class="table-wrap glass reveal">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode Tiket</th>
                        <th>Film</th>
                        <th>Jadwal</th>
                        <th>Kursi</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiketList as $t):

                        $jadwal = Jadwal::cariById($t->getJadwalId());
                        $film = $jadwal?->getFilm();
                        ?>
                        <tr>
                            <td style="font-family: 'Courier New', monospace;"><?= amankan($t->getKodeTiket()) ?></td>
                            <td><?= amankan($film?->getJudul() ?? '-') ?></td>
                            <td><?= $jadwal
                                ? formatTanggalIndo($jadwal->getTanggal()) . ', ' . amankan($jadwal->getJam())
                                : '-' ?></td>
                            <td><?= count($t->getKursiIds()) ?> kursi</td>
                            <td><?= formatRupiah($t->getTotalHarga()) ?></td>
                            <td>
                                <?php
                                $s = $t->getStatus();
                                $kelasPill = match ($s) {
                                    'lunas' => 'pill-success',
                                    'pending' => 'pill-warning',
                                    'batal' => 'pill-danger',
                                    'terpakai' => 'pill-muted',
                                    default => 'pill-muted',
                                };
                                ?>
                                <span class="pill <?= $kelasPill ?>"><?= strtoupper($s) ?></span>
                            </td>
                            <td class="flex gap-8">
                                <a href="e-tiket.php?id=<?= $t->getId() ?>" class="btn btn-ghost btn-sm">Lihat</a>
                                <?php if (in_array($s, ['pending', 'lunas'], true)): ?>
                                    <form method="POST" action="batalkan.php" onsubmit="return confirm('Batalkan tiket ini?');">
                                        <input type="hidden" name="tiket_id" value="<?= $t->getId() ?>">
                                        <input type="hidden" name="alasan" value="Perubahan rencana">
                                        <button type="submit" class="btn btn-danger btn-sm">Batalkan</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php
                    endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
