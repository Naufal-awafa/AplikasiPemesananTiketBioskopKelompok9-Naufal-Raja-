<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Film.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Laporan.php';

$judulHalaman = 'Sineverse Cinema — Pesan Tiket Bioskop Online';
$tampilkanPreloader = true;
require __DIR__ . '/includes/header.php';

$filmTayang = Film::semua('tayang');
$filmSegera = Film::semua('segera');
$semuaFilm = array_merge($filmTayang, $filmSegera);

$q = mb_strtolower(trim($_GET['q'] ?? ''));
$filterGenre = trim($_GET['genre'] ?? '');
$filterBahasa = trim($_GET['bahasa'] ?? '');
$filterUsia = trim($_GET['rating_usia'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterCabang = (int) ($_GET['cabang_id'] ?? 0);
$pdoHome = Database::getInstance()->getKoneksi();
$daftarCabangHome = $pdoHome->query('SELECT * FROM cabang WHERE aktif=1 ORDER BY kota,nama')->fetchAll();
$filmCabang = [];
if ($filterCabang) {
    $s = $pdoHome->prepare(
        'SELECT DISTINCT j.film_id FROM jadwal j JOIN studio s ON s.id=j.studio_id WHERE s.cabang_id=?',
    );
    $s->execute([$filterCabang]);
    $filmCabang = array_map('intval', array_column($s->fetchAll(), 'film_id'));
}
$semuaFilm = array_values(
    array_filter($semuaFilm, function (Film $film) use (
        $q,
        $filterGenre,
        $filterBahasa,
        $filterUsia,
        $filterStatus,
        $filterCabang,
        $filmCabang,
    ) {
        if ($q !== '' && !str_contains(mb_strtolower($film->getJudul() . ' ' . $film->getGenre()), $q)) {
            return false;
        }
        if ($filterGenre !== '' && !str_contains(mb_strtolower($film->getGenre()), mb_strtolower($filterGenre))) {
            return false;
        }
        if ($filterBahasa !== '' && $film->getBahasa() !== $filterBahasa) {
            return false;
        }
        if ($filterUsia !== '' && $film->getRatingUsia() !== $filterUsia) {
            return false;
        }
        if ($filterCabang && !in_array($film->getId(), $filmCabang, true)) {
            return false;
        }
        return true;
    }),
);
$filterOptions = Database::getInstance()
    ->getKoneksi()
    ->query('SELECT DISTINCT bahasa,rating_usia FROM film')
    ->fetchAll();

$totalTiket = Laporan::totalTiketTerjual();
?>

<section class="hero container">
    <?php if (!empty($_SESSION['error_global'])): ?><div class="alert alert-error" style="grid-column:1/-1"><?= amankan(
    $_SESSION['error_global'],
) ?></div><?php unset($_SESSION['error_global']);endif; ?>
    <div class="hero-text">
        <span class="hero-kicker">Pesan tiket dengan mudah</span>
        <h1>
            <span
                class="random-font-target stable-font-cycle hero-effect-text"
                data-font-interval="650"
                data-text="Nonton Film Favorit,"
            >Nonton Film Favorit,</span><br>
            <span class="grad-text">Tanpa Antre Panjang.</span>
        </h1>
        <p class="lead">Pilih film, jadwal, dan kursimu. Tambahkan snack bila perlu, lalu selesaikan pesanan dalam beberapa langkah yang jelas.</p>
        <div class="hero-actions">
            <a href="#daftar-film" class="btn btn-primary">Pesan Tiket Sekarang</a>
            <?php if (!$user): ?>
                <a href="<?= $base ?>register.php" class="btn btn-ghost">Buat Akun Gratis</a>
            <?php elseif ($user->getRole() !== 'customer'): ?>
                <a href="<?= $base . $user->getDashboardUrl() ?>" class="btn btn-ghost">Buka Dashboard</a>
            <?php else: ?>
                <a href="<?= $base ?>menu.php" class="btn btn-ghost">Lihat Snack &amp; Minuman</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card glass"><div class="num" data-countup="<?= count(
            $semuaFilm,
        ) ?>">0</div><div class="label">Film Tersedia</div></div>
        <div class="stat-card glass"><div class="num" data-countup="3">0</div><div class="label">Studio Premium</div></div>
        <div class="stat-card glass"><div class="num" data-countup="<?= $totalTiket ?>">0</div><div class="label">Tiket Terjual</div></div>
        <div class="stat-card glass"><div class="num" data-countup="98">0</div><div class="label">% Kepuasan</div></div>
    </div>
</section>

<section class="section container movie-catalog-section" id="daftar-film">
    <div class="section-head reveal">
        <div>
            <h2>Pilih Film &amp; Jadwal</h2>
            <p>Pilih film, cek jam tayang, lalu tentukan kursi bioskopmu.</p>
        </div>
        <div class="tabs glass">
            <button type="button" class="<?= !in_array($filterStatus, ['tayang', 'segera'], true)
                ? 'active'
                : '' ?>" data-tab-target="semua">Semua</button>
            <button type="button" class="<?= $filterStatus === 'tayang' ? 'active' : '' ?>" data-tab-target="tayang">Sedang Tayang</button>
            <button type="button" class="<?= $filterStatus === 'segera' ? 'active' : '' ?>" data-tab-target="segera">Akan Datang</button>
        </div>
    </div>

    <form method="GET" class="card glass movie-filter-bar mb-24">
        <input type="hidden" name="status" id="film-status-filter" value="<?= amankan($filterStatus) ?>">
        <div class="field-control"><span class="field-icon"><?= ikon(
            'search',
            18,
        ) ?></span><input class="form-control has-icon" name="q" value="<?= amankan(
    $_GET['q'] ?? '',
        ) ?>" placeholder="Cari judul atau genre..."></div>
        <select class="form-control" name="bahasa"><option value="">Semua bahasa</option><?php foreach (
            array_unique(array_column($filterOptions, 'bahasa'))
            as $opsi
        ): ?><option <?= $filterBahasa === $opsi ? 'selected' : '' ?>><?= amankan(
    $opsi,
) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="rating_usia"><option value="">Semua usia</option><?php foreach (
            array_unique(array_column($filterOptions, 'rating_usia'))
            as $opsi
        ): ?><option <?= $filterUsia === $opsi ? 'selected' : '' ?>><?= amankan(
    $opsi,
) ?></option><?php endforeach; ?></select>
        <select class="form-control" name="cabang_id"><option value="">Semua cabang</option><?php foreach (
            $daftarCabangHome
            as $c
        ): ?><option value="<?= $c['id'] ?>" <?= $filterCabang === (int) $c['id'] ? 'selected' : '' ?>><?= amankan(
    $c['kota'] . ' — ' . $c['nama'],
) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary">Terapkan</button><a class="btn btn-ghost" href="index.php#daftar-film">Reset</a>
    </form>

    <?php if (empty($semuaFilm)): ?>
        <div class="empty-state glass">
            <div class="icon"><?= ikon('film', 44) ?></div>
            <p>Belum ada film yang terdaftar saat ini.</p>
        </div>
    <?php else: ?>
        <div class="film-grid">
            <?php foreach ($semuaFilm as $i => $film): ?>
                <?php
                $sinopsisModal = trim($film->getSinopsis());
                if ($sinopsisModal === '') {
                    $sinopsisModal = 'Sinopsis resmi belum tersedia.';
                }
                $dataModal = [
                    'title' => $film->getJudul(),
                    'genre' => $film->getGenre(),
                    'durasi' => $film->getDurasi() . ' menit',
                    'ratingUsia' => $film->getRatingUsia(),
                    'bahasa' => $film->getBahasa(),
                    'synopsis' => mb_strimwidth($sinopsisModal, 0, 360, '…', 'UTF-8'),
                    'poster' => $film->punyaPosterAsli() ? $film->getPosterUrl() : '',
                    'bookingUrl' => 'film-detail.php?id=' . $film->getId(),
                ];
                ?>
                <a href="film-detail.php?id=<?= $film->getId() ?>"
                   class="film-card glass reveal"
                   style="animation-delay: <?= $i * 0.05 ?>s"
                   data-tab-group="<?= $film->getStatus() ?>"
                   data-film-modal="<?= amankan(
                       json_encode($dataModal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                   ) ?>">
                    <div class="film-poster <?= $film->punyaPosterAsli()
                        ? 'has-image'
                        : '' ?>" data-inisial="<?= amankan(substr($film->getJudul(), 0, 1)) ?>"
                         style="background: linear-gradient(160deg, <?= amankan(
                             $film->getPosterWarna(),
                         ) ?>55, #0d0d1400 70%), radial-gradient(circle at 30% 20%, <?= amankan(
    $film->getPosterWarna(),
) ?>40, transparent 60%), #14141e;">
                        <?php if ($film->punyaPosterAsli()): ?><img class="film-poster-img" src="<?= amankan(
    $film->getPosterUrl(),
) ?>" alt="<?= amankan($film->getJudul()) ?>" loading="lazy"><?php endif; ?>
                        <span class="status-chip <?= $film->getStatus() ?>"><?= $film->getStatus() === 'tayang'
    ? 'Tayang'
    : 'Segera' ?></span>
                        <span class="rating-chip"><?= ikon('star-fill', 14) ?> <?= number_format(
     $film->getRating(),
     1,
 ) ?></span>
                    </div>
                    <div class="film-body">
                        <h3><?= amankan($film->getJudul()) ?></h3>
                        <p class="genre"><?= amankan(
                            $film->getGenre(),
                        ) ?> &middot; <?= $film->getDurasi() ?> menit · <?= amankan(
     $film->getBahasa(),
 ) ?> · <?= amankan($film->getRatingUsia()) ?></p>
                        <div class="film-booking-row">
                            <p class="price"><small>Mulai dari</small><?= formatRupiah(
                                $film->getHargaDasar(),
                            ) ?></p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="movie-quick-modal" id="movie-quick-modal" hidden>
    <button class="movie-modal-backdrop" type="button" data-movie-modal-close aria-label="Tutup detail film"></button>
    <article class="movie-modal-card" role="dialog" aria-modal="true" aria-labelledby="movie-modal-title">
        <button class="movie-modal-close" type="button" data-movie-modal-close aria-label="Tutup detail film">&times;</button>
        <div class="movie-modal-media">
            <img id="movie-modal-poster" src="" alt="">
            <div class="movie-modal-poster-fallback"><?= ikon('film', 54) ?></div>
        </div>
        <div class="movie-modal-content">
            <span class="movie-modal-kicker">Tentang Film</span>
            <h2 id="movie-modal-title"></h2>
            <div class="movie-modal-meta" id="movie-modal-meta"></div>
            <div class="movie-modal-about">
                <h3>Film ini bercerita tentang apa?</h3>
                <p class="movie-modal-synopsis" id="movie-modal-synopsis"></p>
            </div>
            <a class="movie-modal-booking" id="movie-modal-booking" href="#">Pesan Tiket</a>
        </div>
    </article>
</div>

<section class="section container" id="tentang">
    <div class="glass card reveal" style="padding: 44px; text-align:center;">
        <h2 style="font-size:1.6rem; font-weight:800; margin-bottom: 12px;">Kenapa Pesan di Sineverse Cinema?</h2>
        <p class="text-muted" style="max-width:640px; margin: 0 auto;">
            Proses pemesanan cepat, pilihan kursi interaktif, berbagai metode pembayaran, dan e-tiket
            dengan QR Code yang siap dipindai langsung di pintu masuk studio. Nikmati pengalaman nonton
            tanpa antre dan tanpa ribet.
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
