<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Film.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Ulasan.php';
require_once __DIR__ . '/core/FiturBioskop.php';

$filmId = (int) ($_GET['id'] ?? 0);
$film = Film::cariById($filmId);
if (!$film) {
    header('Location: index.php');
    exit();
}

$user = penggunaSaatIni();
$pdo = Database::getInstance()->getKoneksi();
$isFavorit = false;
if ($user) {
    $stmtFav = $pdo->prepare('SELECT 1 FROM favorit WHERE pengguna_id=? AND film_id=?');
    $stmtFav->execute([$user->getId(), $filmId]);
    $isFavorit = (bool) $stmtFav->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle_favorit' && $user) {
    if ($isFavorit) {
        $pdo->prepare('DELETE FROM favorit WHERE pengguna_id=? AND film_id=?')->execute([$user->getId(), $filmId]);
    } else {
        $pdo->prepare('INSERT IGNORE INTO favorit (pengguna_id,film_id) VALUES (?,?)')->execute([
            $user->getId(),
            $filmId,
        ]);
    }
    header('Location: film-detail.php?id=' . $filmId);
    exit();
}

// Proses submit ulasan (hanya customer yang login)
$pesanUlasan = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'kirim_ulasan') {
    if (!$user || !FiturBioskop::bolehMengulas($user->getId(), $filmId)) {
        $pesanUlasan = 'Ulasan hanya dapat diberikan oleh pemilik tiket yang sudah dipakai.';
    } else {
        $rating = (int) ($_POST['rating'] ?? 5);
        $komentar = trim($_POST['komentar'] ?? '');
        if ($komentar !== '') {
            (new Ulasan(null, $filmId, $user->getId(), $rating, $komentar, '', '', true))->simpan();
            header('Location: film-detail.php?id=' . $filmId . '#ulasan');
            exit();
        }
    }
}

$jadwalList = Jadwal::untukFilm($filmId);
$namaCabang = array_column($pdo->query('SELECT id,nama FROM cabang')->fetchAll(), 'nama', 'id');
$ulasanList = Ulasan::untukFilm($filmId);

// Kelompokkan jadwal per tanggal untuk tampilan lebih rapi
$jadwalPerTanggal = [];
foreach ($jadwalList as $j) {
    $jadwalPerTanggal[$j->getTanggal()][] = $j;
}

$judulHalaman = $film->getJudul() . ' — Sineverse Cinema';
require __DIR__ . '/includes/header.php';
?>

<section class="film-detail-hero<?= $film->punyaPosterAsli() ? ' has-backdrop' : '' ?>">
    <?php if ($film->punyaPosterAsli()): ?>
        <div class="film-detail-backdrop" aria-hidden="true">
            <img src="<?= amankan($film->getPosterUrl()) ?>" alt="">
        </div>
    <?php endif; ?>

    <div class="container film-detail-hero-content">
    <div class="grid-2 film-detail-grid" style="grid-template-columns: 320px 1fr; align-items:flex-start;">
        <div class="film-poster glass reveal <?= $film->punyaPosterAsli()
            ? 'has-image'
            : '' ?>" data-inisial="<?= amankan(substr($film->getJudul(), 0, 1)) ?>"
             style="aspect-ratio:2/3; border-radius:24px; background: linear-gradient(160deg, <?= amankan(
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
            <span class="rating-chip"><?= ikon('star-fill', 14) ?> <?= number_format($film->getRating(), 1) ?></span>
        </div>

        <div class="film-detail-copy glass reveal">
            <span class="badge-role" style="margin-bottom:14px; display:inline-block;"><?= amankan(
                $film->getGenre(),
            ) ?></span>
            <h1 style="font-size:2.2rem; font-weight:800; margin-bottom:10px;"><?= amankan($film->getJudul()) ?></h1>
            <p class="text-muted" style="margin-bottom:22px;"><?= $film->getDurasi() ?> menit &middot; Harga mulai <?= formatRupiah(
     $film->getHargaDasar(),
 ) ?></p>
            <p style="color: var(--silver); max-width: 640px; line-height:1.8;"><?= nl2br(
                amankan($film->getSinopsis()),
            ) ?></p>
            <p class="text-muted mt-16"><?= amankan($film->getBahasa()) ?> · Rating usia <?= amankan(
     $film->getRatingUsia(),
 ) ?></p>
            <?php if (
                $user
            ): ?><form method="POST" class="mt-16"><input type="hidden" name="aksi" value="toggle_favorit"><button class="btn btn-outline favorite-toggle <?= $isFavorit
    ? 'is-favorite'
    : '' ?>" type="submit" aria-pressed="<?= $isFavorit ? 'true' : 'false' ?>"><?= ikon(
    'heart',
    18,
    $isFavorit ? 'favorite-heart' : '',
) ?> <?= $isFavorit ? 'Hapus dari Favorit' : 'Tambahkan ke Favorit' ?></button></form><?php endif; ?>
        </div>
    </div>
    </div>
</section>

<?php if ($film->punyaTrailer()): ?>
<section class="section container film-detail-booking-section">
    <div class="section-head reveal">
        <div><h2>Trailer</h2><p>Tonton cuplikan resmi sebelum menentukan jadwal.</p></div>
    </div>
    <div class="card glass reveal trailer-wrap">
        <iframe src="https://www.youtube.com/embed/<?= amankan($film->getTrailerKey()) ?>" title="Trailer <?= amankan(
    $film->getJudul(),
) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
    </div>
</section>
<?php endif; ?>

<section class="section container film-detail-booking-section">
    <div class="section-head reveal">
        <div><h2>Pilih Jadwal Tayang</h2><p>Pilih tanggal &amp; studio yang sesuai untukmu.</p></div>
    </div>

    <?php if (empty($jadwalPerTanggal)): ?>
        <div class="empty-state glass">
        <div class="icon"><?= ikon('calendar', 44) ?></div>
        <p><?= $film->getStatus() === 'segera'
            ? 'Film ini akan segera tayang. Jadwal belum tersedia.'
            : 'Belum ada jadwal untuk film ini.' ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($jadwalPerTanggal as $tanggal => $daftar): ?>
            <div class="card glass reveal mb-24">
                <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                    'calendar',
                    18,
                ) ?> <?= formatTanggalIndo($tanggal) ?></h3>
                <div class="grid-4" style="grid-template-columns: repeat(auto-fill, minmax(160px,1fr));">
                    <?php foreach ($daftar as $j):
                        $studio = $j->getStudio(); ?>
                        <a href="<?= !$j->masihBisaDipesan()
                            ? '#'
                            : ($user
                                ? 'booking-kursi.php?jadwal_id=' . $j->getId()
                                : 'login.php') ?>" class="btn btn-outline<?= !$j->masihBisaDipesan()
    ? ' disabled'
    : '' ?>" style="flex-direction:column; height:auto; padding:14px; gap:4px;">
                            <strong style="font-size:1rem;"><?= amankan($j->getJam()) ?></strong>
                            <span style="font-size:.72rem; opacity:.8;"><?= amankan(
                                $studio->getNama(),
                            ) ?> &middot; <?= amankan(
     $studio->getTipe(),
 ) ?></span><span style="font-size:.65rem;opacity:.65"><?= amankan(
    $namaCabang[$studio->getCabangId()] ?? 'Sineverse',
) ?></span>
                            <?php if (
                                !$j->masihBisaDipesan()
                            ): ?><span style="font-size:.65rem;color:var(--danger);">Jadwal lewat</span><?php endif; ?>
                        </a>
                    <?php
                    endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="section container film-detail-booking-section" id="ulasan">
    <div class="section-head reveal">
        <div><h2>Ulasan Penonton</h2><p>Apa kata mereka yang sudah menonton?</p></div>
    </div>

    <div class="grid-2" style="grid-template-columns: 1fr 340px; align-items:flex-start;">
        <div class="card glass reveal">
            <?php if (empty($ulasanList)): ?>
                <p class="text-muted" style="font-size:.88rem;">Belum ada ulasan untuk film ini. Jadilah yang pertama!</p>
            <?php else:foreach ($ulasanList as $u): ?>
                <div class="review-item">
                    <div class="rev-head">
                        <span class="rev-name"><?= amankan($u->getNamaCustomer()) ?></span>
                        <?php if (
                            $u->isTerverifikasi()
                        ): ?><span class="pill pill-success">Terverifikasi</span><?php endif; ?>
                        <span class="rev-date"><?= date('d M Y', strtotime($u->getDibuatPada())) ?></span>
                    </div>
                    <div class="stars"><?= str_repeat(ikon('star-fill', 14), $u->getRating()) .
                        str_repeat(ikon('star', 14), 5 - $u->getRating()) ?></div>
                    <p class="mt-8"><?= amankan($u->getKomentar()) ?></p>
                </div>
            <?php endforeach;endif; ?>
        </div>

        <div class="card glass reveal">
            <h3 style="font-size:.95rem; font-weight:800; margin-bottom:14px;">Beri Ulasan</h3>
            <?php if ($pesanUlasan): ?><div class="alert alert-info" data-autohide><?= amankan(
    $pesanUlasan,
) ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="aksi" value="kirim_ulasan">
                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating" class="form-control">
                        <option value="5"><?= ikon('star-fill', 14) ?> <?= ikon('star-fill', 14) ?> <?= ikon(
     'star-fill',
     14,
 ) ?> <?= ikon('star-fill', 14) ?> <?= ikon('star-fill', 14) ?> Sangat Bagus</option>
                        <option value="4"><?= ikon('star-fill', 14) ?> <?= ikon('star-fill', 14) ?> <?= ikon(
     'star-fill',
     14,
 ) ?> <?= ikon('star-fill', 14) ?> Bagus</option>
                        <option value="3"><?= ikon('star-fill', 14) ?> <?= ikon('star-fill', 14) ?> <?= ikon(
     'star-fill',
     14,
 ) ?> Cukup</option>
                        <option value="2"><?= ikon('star-fill', 14) ?> <?= ikon('star-fill', 14) ?> Kurang</option>
                        <option value="1"><?= ikon('star-fill', 14) ?> Buruk</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Komentar</label>
                    <textarea name="komentar" class="form-control" placeholder="Bagaimana pendapatmu tentang film ini?" required></textarea>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Kirim Ulasan</button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
