<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Studio.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibAkses('studio');

$pesan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $tipe = $_POST['tipe'] ?? '2D';
    $baris = max(1, (int) ($_POST['jumlah_baris'] ?? 6));
    $kolom = max(1, (int) ($_POST['jumlah_kolom'] ?? 10));
    $cabangId = max(1, (int) ($_POST['cabang_id'] ?? 1));
    if ($nama !== '') {
        $idBaru = (new Studio(null, $nama, $tipe, $baris, $kolom, $cabangId))->simpan(); // auto-generate layout kursi
        FiturBioskop::audit($user->getId(), 'tambah', 'studio', $idBaru);
        header('Location: studio.php?pesan=tersimpan');
        exit();
    }
}

$daftarStudio = Studio::semua();
$daftarCabang = Database::getInstance()
    ->getKoneksi()
    ->query('SELECT * FROM cabang WHERE aktif=1 ORDER BY nama')
    ->fetchAll();
$namaCabang = array_column($daftarCabang, 'nama', 'id');
$studioDetail = isset($_GET['lihat']) ? Studio::cariById((int) $_GET['lihat']) : null;
$kursiDetail = $studioDetail ? Kursi::untukStudio($studioDetail->getId()) : [];

$kursiPerBaris = [];
foreach ($kursiDetail as $k) {
    $kursiPerBaris[substr($k->getLabel(), 0, 1)][] = $k;
}

$judulHalaman = 'Studio & Kursi — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'studio';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Studio &amp; Layout Kursi</h2><p>Kelola ruang tayang dan susunan kursinya.</p></div>
</div>

<?php if (
    isset($_GET['pesan'])
): ?><div class="alert alert-success" data-autohide>Studio baru berhasil dibuat beserta layout kursinya.</div><?php endif; ?>

<div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
    <div>
        <div class="table-wrap glass reveal mb-24">
            <table class="data-table">
                <thead><tr><th>Nama Studio</th><th>Cabang</th><th>Tipe</th><th>Kapasitas</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($daftarStudio as $s): ?>
                    <tr>
                        <td><?= amankan($s->getNama()) ?></td>
                        <td><?= amankan($namaCabang[$s->getCabangId()] ?? '-') ?></td>
                        <td><span class="pill pill-info"><?= amankan($s->getTipe()) ?></span></td>
                        <td><?= $s->getKapasitas() ?> kursi</td>
                        <td><a href="studio.php?lihat=<?= $s->getId() ?>" class="btn btn-ghost btn-sm">Lihat Layout</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($studioDetail): ?>
            <div class="card glass reveal">
                <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                    'chair',
                    18,
                ) ?> Layout — <?= amankan($studioDetail->getNama()) ?></h3>
                <div class="screen-curve" style="max-width:400px;"></div>
                <div class="seat-map" style="margin-top:50px;">
                    <?php foreach ($kursiPerBaris as $labelBaris => $daftarKursi): ?>
                        <div class="seat-row">
                            <span class="row-label"><?= amankan($labelBaris) ?></span>
                            <?php foreach ($daftarKursi as $k): ?>
                                <div class="seat <?= $k->getTipe() ?>" style="cursor:default;"><?= $k->getLabel() ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="seat-legend">
                    <span><span class="legend-box" style="background:rgba(255,255,255,.06); border:1px solid var(--border);"></span> Reguler</span>
                    <span><span class="legend-box" style="background:rgba(255,176,32,.15); border:1px solid rgba(255,176,32,.4);"></span> VIP (2 baris belakang)</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('plus', 18) ?> Tambah Studio Baru</h3>
        <form method="POST">
            <div class="form-group"><label>Nama Studio</label><input type="text" name="nama" class="form-control" placeholder="cth. Studio 4" required></div>
            <div class="form-group">
                <label>Cabang</label><select name="cabang_id" class="form-control"><?php foreach (
                    $daftarCabang
                    as $c
                ): ?><option value="<?= $c['id'] ?>"><?= amankan(
    $c['nama'] . ' — ' . $c['kota'],
) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group">
                <label>Tipe</label>
                <select name="tipe" class="form-control">
                    <option value="2D">2D</option>
                    <option value="3D">3D</option>
                    <option value="IMAX">IMAX</option>
                </select>
            </div>
            <div class="form-group"><label>Jumlah Baris</label><input type="number" name="jumlah_baris" class="form-control" value="6" min="1" max="20"></div>
            <div class="form-group"><label>Jumlah Kolom</label><input type="number" name="jumlah_kolom" class="form-control" value="10" min="1" max="26"></div>
            <button type="submit" class="btn btn-primary btn-block">Buat Studio + Layout Kursi</button>
            <p class="text-muted mt-16" style="font-size:.76rem;">Layout kursi akan digenerate otomatis. 2 baris paling belakang otomatis ditandai sebagai kursi VIP.</p>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
