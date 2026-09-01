<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Jadwal.php';
require_once __DIR__ . '/../core/Film.php';
require_once __DIR__ . '/../core/Studio.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibAkses('jadwal');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    Jadwal::hapus((int) $_POST['id']);
    FiturBioskop::audit($user->getId(), 'hapus', 'jadwal', (int) $_POST['id']);
    header('Location: jadwal.php?pesan=terhapus');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') !== 'hapus') {
    $filmId = (int) ($_POST['film_id'] ?? 0);
    $studioId = (int) ($_POST['studio_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';
    $filmDipilih = Film::cariById($filmId);
    if (!FiturBioskop::jadwalMasihBisaDipesan($tanggal, $jam)) {
        $error = 'Jadwal harus berada di masa mendatang.';
    } elseif ($filmDipilih && Jadwal::adaBentrok($studioId, $tanggal, $jam, $filmDipilih->getDurasi())) {
        $error = 'Studio masih digunakan pada jam tersebut (termasuk jeda pembersihan 20 menit).';
    } elseif ($filmId && $studioId && $tanggal && $jam) {
        $idBaru = (new Jadwal(null, $filmId, $studioId, $tanggal, $jam))->simpan();
        FiturBioskop::audit($user->getId(), 'tambah', 'jadwal', $idBaru);
        header('Location: jadwal.php?pesan=tersimpan');
        exit();
    }
}

$daftarJadwal = Jadwal::semua();
$daftarFilm = Film::semua();
$daftarStudio = Studio::semua();

$judulHalaman = 'Kelola Jadwal — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'jadwal';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Kelola Jadwal Tayang</h2><p>Atur jadwal film per studio dan jam tayang.</p></div>
</div>

<?php if (isset($_GET['pesan'])): ?>
    <div class="alert alert-success" data-autohide><?= $_GET['pesan'] === 'terhapus'
        ? 'Jadwal berhasil dihapus.'
        : 'Jadwal berhasil ditambahkan.' ?></div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= amankan($error) ?></div><?php endif; ?>

<div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
    <div class="table-wrap glass reveal">
        <table class="data-table">
            <thead><tr><th>Film</th><th>Studio</th><th>Tanggal</th><th>Jam</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftarJadwal as $j):

                $f = $j->getFilm();
                $s = $j->getStudio();
                ?>
                <tr>
                    <td><?= amankan($f?->getJudul() ?? '-') ?></td>
                    <td><?= amankan($s?->getNama() ?? '-') ?> (<?= amankan($s?->getTipe() ?? '') ?>)</td>
                    <td><?= formatTanggalIndo($j->getTanggal()) ?></td>
                    <td><?= amankan($j->getJam()) ?></td>
                    <td><form method="POST" onsubmit="return confirm('Hapus jadwal ini?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $j->getId() ?>"><button class="btn btn-danger btn-sm">Hapus</button></form></td>
                </tr>
            <?php
            endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('plus', 18) ?> Tambah Jadwal</h3>
        <form method="POST">
            <div class="form-group">
                <label>Film</label>
                <select name="film_id" class="form-control" required>
                    <?php foreach ($daftarFilm as $f): ?><option value="<?= $f->getId() ?>"><?= amankan(
    $f->getJudul(),
) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Studio</label>
                <select name="studio_id" class="form-control" required>
                    <?php foreach ($daftarStudio as $s): ?><option value="<?= $s->getId() ?>"><?= amankan(
    $s->getNama(),
) ?> (<?= amankan($s->getTipe()) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" min="<?= date(
                'Y-m-d',
            ) ?>" class="form-control" required></div>
            <div class="form-group"><label>Jam</label><input type="time" name="jam" class="form-control" required></div>
            <button type="submit" class="btn btn-primary btn-block">Tambah Jadwal</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
