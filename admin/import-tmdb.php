<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Film.php';
require_once __DIR__ . '/../core/TmdbService.php';

$user = wajibAkses('film');

$errorPesan = '';

// ---------------------------------------------------------------------
// Proses impor 1 film terpilih dari TMDB ke tabel film lokal.
// Field bisnis (harga_dasar, status) tetap ditentukan/diatur Admin
// lewat halaman Kelola Film setelah impor -> data TMDB hanya untuk
// mengisi judul/genre/sinopsis/poster/trailer secara otomatis.
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'impor') {
    $tmdbId = (int) ($_POST['tmdb_id'] ?? 0);
    $statusSaran = in_array($_POST['status_saran'] ?? '', ['tayang', 'segera'], true)
        ? $_POST['status_saran']
        : 'tayang';

    $sudahAda = $tmdbId ? Film::cariByTmdbId($tmdbId) : null;
    if ($sudahAda) {
        header('Location: films.php?edit=' . $sudahAda->getId() . '&pesan=tersimpan');
        exit();
    }

    try {
        $d = TmdbService::detail($tmdbId);
        $film = new Film(
            null,
            $d['judul'],
            $d['genre'],
            $d['durasi'],
            $d['sinopsis'],
            '#7c5cff',
            $d['rating'],
            $statusSaran,
            35000, // harga dasar default -> Admin sesuaikan sendiri setelah impor
            $d['tmdb_id'],
            $d['poster_url'],
            $d['trailer_key'],
        );
        $idBaru = $film->simpan();
        header('Location: films.php?edit=' . $idBaru . '&pesan=diimpor');
        exit();
    } catch (RuntimeException $e) {
        $errorPesan = $e->getMessage();
    }
}

// ---------------------------------------------------------------------
// Ambil daftar film dari TMDB sesuai tab yang aktif
// ---------------------------------------------------------------------
$tab = in_array($_GET['tab'] ?? '', ['tayang', 'segera', 'cari'], true) ? $_GET['tab'] : 'tayang';
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$kataKunci = trim($_GET['q'] ?? '');

$daftar = ['hasil' => [], 'halaman' => 1, 'total_halaman' => 1];
try {
    if ($tab === 'cari') {
        if ($kataKunci !== '') {
            $daftar = TmdbService::cari($kataKunci, $halaman);
        }
    } elseif ($tab === 'segera') {
        $daftar = TmdbService::akanDatang($halaman);
    } else {
        $daftar = TmdbService::sedangTayang($halaman);
    }
} catch (RuntimeException $e) {
    $errorPesan = $errorPesan ?: $e->getMessage();
}

// Tandai judul yang sudah pernah diimpor, supaya kartunya menampilkan status berbeda
$petaSudahImpor = [];
foreach (Film::semua() as $f) {
    if ($f->getTmdbId()) {
        $petaSudahImpor[$f->getTmdbId()] = $f->getId();
    }
}

$queryTanpaHalaman = 'tab=' . urlencode($tab) . '&q=' . urlencode($kataKunci);

$judulHalaman = 'Impor Film dari TMDB — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'tmdb';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div>
        <h2>Impor Film dari TMDB</h2>
        <p>Ambil data film asli — judul, sinopsis, poster, dan trailer YouTube resmi — langsung dari The Movie Database.</p>
    </div>
</div>

<?php if ($errorPesan): ?><div class="alert alert-error" data-autohide><?= amankan($errorPesan) ?></div><?php endif; ?>

<div class="tabs glass reveal" style="margin-bottom:20px;">
    <a href="import-tmdb.php?tab=tayang" class="<?= $tab === 'tayang' ? 'active' : '' ?>">Sedang Tayang</a>
    <a href="import-tmdb.php?tab=segera" class="<?= $tab === 'segera' ? 'active' : '' ?>">Akan Datang</a>
    <a href="import-tmdb.php?tab=cari" class="<?= $tab === 'cari' ? 'active' : '' ?>">Cari Judul</a>
</div>

<?php if ($tab === 'cari'): ?>
<form method="GET" class="card glass reveal flex gap-8" style="margin-bottom:24px; flex-wrap:wrap;">
    <input type="hidden" name="tab" value="cari">
    <input type="text" name="q" class="form-control" style="flex:1; min-width:200px;" placeholder="Ketik judul film, mis. Dune, Interstellar..." value="<?= amankan(
        $kataKunci,
    ) ?>" autofocus>
    <button class="btn btn-primary" type="submit"><?= ikon('search', 18) ?> Cari di TMDB</button>
</form>
<?php endif; ?>

<?php if ($tab === 'cari' && $kataKunci === ''): ?>
    <div class="empty-state glass"><div class="icon"><?= ikon(
        'search',
        44,
    ) ?></div><p>Ketik judul film di kolom pencarian untuk mulai mencari.</p></div>
<?php elseif (empty($daftar['hasil'])): ?>
    <div class="empty-state glass"><div class="icon"><?= ikon(
        'film-slate',
        44,
    ) ?></div><p>Tidak ada hasil ditemukan.</p></div>
<?php else: ?>

<div class="tmdb-grid reveal">
    <?php foreach ($daftar['hasil'] as $it):
        $idLokal = $petaSudahImpor[$it['tmdb_id']] ?? null; ?>
        <div class="tmdb-card glass">
            <div class="tmdb-poster"<?= $it['poster_url']
                ? ''
                : ' data-inisial="' . amankan(substr($it['judul'], 0, 1)) . '"' ?>>
                <?php if ($it['poster_url']): ?><img src="<?= amankan($it['poster_url']) ?>" alt="<?= amankan(
    $it['judul'],
) ?>" loading="lazy"><?php endif; ?>
                <span class="rating-chip"><?= ikon('star-fill', 14) ?> <?= number_format($it['rating'], 1) ?></span>
            </div>
            <div class="tmdb-body">
                <h4 title="<?= amankan($it['judul']) ?>"><?= amankan($it['judul']) ?></h4>
                <p class="text-muted" style="font-size:.74rem;"><?= amankan($it['genre']) ?></p>
                <p class="text-muted" style="font-size:.7rem;"><?= $it['tanggal_rilis']
                    ? formatTanggalIndo($it['tanggal_rilis'])
                    : 'Tanggal rilis belum tersedia' ?></p>
                <?php if ($idLokal): ?>
                    <a href="films.php?edit=<?= $idLokal ?>" class="btn btn-ghost btn-sm btn-block mt-8"><?= ikon(
    'check',
    16,
) ?> Sudah Diimpor</a>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="aksi" value="impor">
                        <input type="hidden" name="tmdb_id" value="<?= (int) $it['tmdb_id'] ?>">
                        <input type="hidden" name="status_saran" value="<?= amankan($it['status_saran']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm btn-block mt-8"><?= ikon(
                            'download',
                            16,
                        ) ?> Impor</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php
    endforeach; ?>
</div>

<?php if ($daftar['total_halaman'] > 1): ?>
<div class="flex gap-8" style="justify-content:center; align-items:center; margin-top:28px;">
    <?php if (
        $daftar['halaman'] > 1
    ): ?><a class="btn btn-ghost btn-sm" href="?<?= $queryTanpaHalaman ?>&halaman=<?= $daftar['halaman'] -
    1 ?>"><?= ikon('arrow-left', 16) ?> Sebelumnya</a><?php endif; ?>
    <span class="text-muted" style="font-size:.85rem;">Halaman <?= $daftar['halaman'] ?> / <?= min(
     (int) $daftar['total_halaman'],
     500,
 ) ?></span>
    <?php if (
        $daftar['halaman'] < $daftar['total_halaman']
    ): ?><a class="btn btn-ghost btn-sm" href="?<?= $queryTanpaHalaman ?>&halaman=<?= $daftar['halaman'] +
    1 ?>">Selanjutnya <?= ikon('arrow-right', 16) ?></a><?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<p class="text-muted" style="font-size:.72rem; margin-top:28px; text-align:center;">Data film dipasok oleh <a href="https://www.themoviedb.org/" target="_blank" rel="noopener" style="color:inherit; text-decoration:underline;">TMDB</a>. Setelah diimpor, atur harga tiket &amp; jadwal tayang seperti biasa di menu Kelola Film &amp; Kelola Jadwal.</p>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
