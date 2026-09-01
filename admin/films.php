<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Film.php';
require_once __DIR__ . '/../core/Admin.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibAkses('film');

$pesan = '';
$errorPesan = '';

// Hapus film
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    Film::hapus((int) $_POST['id']);
    FiturBioskop::audit($user->getId(), 'hapus', 'film', (int) $_POST['id']);
    header('Location: films.php?pesan=terhapus');
    exit();
}

// Simpan (tambah/edit) film
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? 'simpan') !== 'hapus') {
    $data = [
        'judul' => trim($_POST['judul'] ?? ''),
        'genre' => trim($_POST['genre'] ?? ''),
        'durasi' => (int) ($_POST['durasi'] ?? 0),
        'sinopsis' => trim($_POST['sinopsis'] ?? ''),
        'poster_warna' => $_POST['poster_warna'] ?? '#7c5cff',
        'rating' => (float) ($_POST['rating'] ?? 0),
        'status' => $_POST['status'] ?? 'tayang',
        'harga_dasar' => (int) ($_POST['harga_dasar'] ?? 0),
        'poster_url' => trim($_POST['poster_url'] ?? ''),
        'trailer_key' => trim($_POST['trailer_key'] ?? ''),
        'bahasa' => trim($_POST['bahasa'] ?? 'Indonesia'),
        'rating_usia' => trim($_POST['rating_usia'] ?? 'SU'),
    ];

    // >> DEMONSTRASI method khusus Admin (validasiDataFilm) sebelum simpan
    if (!$user->validasiDataFilm($data)) {
        $errorPesan = 'Judul, genre, dan harga dasar wajib diisi dengan benar.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        // Pertahankan tmdb_id yang sudah ada saat mengedit film hasil impor
        $filmLama = $id ? Film::cariById($id) : null;
        $film = new Film(
            $id ?: null,
            $data['judul'],
            $data['genre'],
            $data['durasi'],
            $data['sinopsis'],
            $data['poster_warna'],
            $data['rating'],
            $data['status'],
            $data['harga_dasar'],
            $filmLama?->getTmdbId(),
            $data['poster_url'],
            $data['trailer_key'],
            $data['bahasa'],
            $data['rating_usia'],
        );
        $idSimpan = $film->simpan();
        FiturBioskop::audit($user->getId(), $id ? 'ubah' : 'tambah', 'film', $idSimpan, $data['judul']);
        header('Location: films.php?pesan=tersimpan');
        exit();
    }
}

$filmEdit = null;
if (isset($_GET['edit'])) {
    $filmEdit = Film::cariById((int) $_GET['edit']);
}

$daftarFilm = Film::semua();

$judulHalaman = 'Kelola Film — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'films';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Kelola Film</h2><p>Tambah, ubah, atau hapus data film.</p></div>
</div>

<?php if (isset($_GET['pesan'])): ?>
    <div class="alert alert-success" data-autohide><?= $_GET['pesan'] === 'terhapus'
        ? 'Film berhasil dihapus.'
        : ($_GET['pesan'] === 'diimpor'
            ? 'Film berhasil diimpor dari TMDB. Silakan sesuaikan harga tiket & statusnya di bawah.'
            : 'Data film berhasil disimpan.') ?></div>
<?php endif; ?>
<?php if ($errorPesan): ?><div class="alert alert-error" data-autohide><?= amankan($errorPesan) ?></div><?php endif; ?>

<div class="grid-2" style="grid-template-columns: 1fr 380px; align-items:flex-start;">
    <div class="table-wrap glass reveal">
        <table class="data-table">
            <thead><tr><th>Judul</th><th>Genre</th><th>Status</th><th>Harga Dasar</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftarFilm as $f): ?>
                <tr>
                    <td><?= amankan($f->getJudul()) ?> <?php if (
     $f->getTmdbId()
 ): ?><span class="pill" style="font-size:.6rem; background:rgba(34,211,238,.16); color:var(--info, #22d3ee); border:1px solid rgba(34,211,238,.35);">TMDB</span><?php endif; ?></td>
                    <td><?= amankan($f->getGenre()) ?></td>
                    <td><span class="pill <?= $f->getStatus() === 'tayang'
                        ? 'pill-success'
                        : 'pill-warning' ?>"><?= strtoupper($f->getStatus()) ?></span></td>
                    <td><?= formatRupiah($f->getHargaDasar()) ?></td>
                    <td class="flex gap-8">
                        <a href="films.php?edit=<?= $f->getId() ?>" class="btn btn-ghost btn-sm">Edit</a>
                        <form method="POST" onsubmit="return confirm('Hapus film ini beserta seluruh jadwalnya?')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $f->getId() ?>"><button class="btn btn-danger btn-sm">Hapus</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('film-slate', 18) ?> <?= $filmEdit
     ? 'Edit Film'
     : 'Tambah Film Baru' ?></h3>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $filmEdit?->getId() ?? '' ?>">
            <div class="form-group"><label>Judul</label><input type="text" name="judul" class="form-control" value="<?= amankan(
                $filmEdit?->getJudul() ?? '',
            ) ?>" required></div>
            <div class="form-group"><label>Genre</label><input type="text" name="genre" class="form-control" value="<?= amankan(
                $filmEdit?->getGenre() ?? '',
            ) ?>" required></div>
            <div class="form-group"><label>Durasi (menit)</label><input type="number" name="durasi" class="form-control" value="<?= $filmEdit?->getDurasi() ??
                100 ?>"></div>
            <div class="grid-2"><div class="form-group"><label>Bahasa</label><input name="bahasa" class="form-control" value="<?= amankan(
                $filmEdit?->getBahasa() ?? 'Indonesia',
            ) ?>"></div><div class="form-group"><label>Rating Usia</label><select name="rating_usia" class="form-control"><?php foreach (
    ['SU', '13+', '17+', '21+']
    as $usia
): ?><option <?= ($filmEdit?->getRatingUsia() ?? 'SU') === $usia
    ? 'selected'
    : '' ?>><?= $usia ?></option><?php endforeach; ?></select></div></div>
            <div class="form-group"><label>Sinopsis</label><textarea name="sinopsis" class="form-control"><?= amankan(
                $filmEdit?->getSinopsis() ?? '',
            ) ?></textarea></div>
            <div class="form-group"><label>Warna Poster</label><input type="color" name="poster_warna" class="form-control" style="height:44px;" value="<?= amankan(
                $filmEdit?->getPosterWarna() ?? '#7c5cff',
            ) ?>"></div>
            <div class="form-group"><label>Rating (0-5)</label><input type="number" step="0.1" min="0" max="5" name="rating" class="form-control" value="<?= $filmEdit?->getRating() ??
                4.5 ?>"></div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="tayang" <?= ($filmEdit?->getStatus() ?? '') === 'tayang'
                        ? 'selected'
                        : '' ?>>Sedang Tayang</option>
                    <option value="segera" <?= ($filmEdit?->getStatus() ?? '') === 'segera'
                        ? 'selected'
                        : '' ?>>Akan Datang</option>
                </select>
            </div>
            <div class="form-group"><label>Harga Dasar (Rp)</label><input type="number" name="harga_dasar" class="form-control" value="<?= $filmEdit?->getHargaDasar() ??
                35000 ?>" required></div>
            <div class="form-group"><label>URL Poster Asli (opsional, hasil impor TMDB)</label><input type="text" name="poster_url" class="form-control" placeholder="https://image.tmdb.org/..." value="<?= amankan(
                $filmEdit?->getPosterUrl() ?? '',
            ) ?>"></div>
            <div class="form-group"><label>Trailer YouTube Key (opsional)</label><input type="text" name="trailer_key" class="form-control" placeholder="mis. dQw4w9WgXcQ" value="<?= amankan(
                $filmEdit?->getTrailerKey() ?? '',
            ) ?>"></div>
            <button type="submit" class="btn btn-primary btn-block"><?= $filmEdit
                ? 'Simpan Perubahan'
                : 'Tambah Film' ?></button>
            <a href="import-tmdb.php" class="btn btn-ghost btn-block mt-8"><?= ikon(
                'download',
                18,
            ) ?> Impor Film Baru dari TMDB</a>
            <?php if (
                $filmEdit
            ): ?><a href="films.php" class="btn btn-ghost btn-block mt-8">Batal Edit</a><?php endif; ?>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
