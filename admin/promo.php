<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Promo.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibAkses('promo');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    Promo::hapus((int) $_POST['id']);
    FiturBioskop::audit($user->getId(), 'hapus', 'promo', (int) $_POST['id']);
    header('Location: promo.php?pesan=terhapus');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') !== 'hapus') {
    $kode = trim($_POST['kode'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $diskon = (int) ($_POST['diskon_persen'] ?? 0);
    $hingga = $_POST['berlaku_hingga'] ?? '';
    $aktif = isset($_POST['aktif']);
    if ($kode !== '' && $hingga !== '') {
        $idBaru = (new Promo(null, $kode, $deskripsi, $diskon, $hingga, $aktif))->simpan();
        FiturBioskop::audit($user->getId(), 'tambah', 'promo', $idBaru, $kode);
        header('Location: promo.php?pesan=tersimpan');
        exit();
    }
}

$daftarPromo = Promo::semua();

$judulHalaman = 'Kelola Promo — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'promo';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Promo &amp; Diskon</h2><p>Buat kode promo untuk ditawarkan ke customer saat checkout.</p></div>
</div>

<?php if (isset($_GET['pesan'])): ?>
    <div class="alert alert-success" data-autohide><?= $_GET['pesan'] === 'terhapus'
        ? 'Promo berhasil dihapus.'
        : 'Promo berhasil disimpan.' ?></div>
<?php endif; ?>

<div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
    <div class="table-wrap glass reveal">
        <table class="data-table">
            <thead><tr><th>Kode</th><th>Diskon</th><th>Berlaku Hingga</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftarPromo as $p): ?>
                <tr>
                    <td style="font-family:'Courier New',monospace; font-weight:700;"><?= amankan($p->getKode()) ?></td>
                    <td><?= $p->getDiskonPersen() ?>%</td>
                    <td><?= formatTanggalIndo($p->getBerlakuHingga()) ?></td>
                    <td><span class="pill <?= $p->isAktif() ? 'pill-success' : 'pill-muted' ?>"><?= $p->isAktif()
    ? 'AKTIF'
    : 'NONAKTIF' ?></span></td>
                    <td><form method="POST" onsubmit="return confirm('Hapus promo ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $p->getId() ?>"><button class="btn btn-danger btn-sm">Hapus</button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('plus', 18) ?> Buat Promo Baru</h3>
        <form method="POST">
            <div class="form-group"><label>Kode Promo</label><input type="text" name="kode" class="form-control" placeholder="cth. SINE20" required></div>
            <div class="form-group"><label>Deskripsi</label><input type="text" name="deskripsi" class="form-control" placeholder="Deskripsi singkat"></div>
            <div class="form-group"><label>Diskon (%)</label><input type="number" name="diskon_persen" class="form-control" value="10" min="1" max="90"></div>
            <div class="form-group"><label>Berlaku Hingga</label><input type="date" name="berlaku_hingga" class="form-control" required></div>
            <div class="form-group flex items-center gap-8"><input type="checkbox" name="aktif" checked style="width:auto;"><label style="margin:0;">Aktifkan promo</label></div>
            <button type="submit" class="btn btn-primary btn-block">Simpan Promo</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
