<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Film.php';
$user = wajibLogin();
$pdo = Database::getInstance()->getKoneksi();
$stmt = $pdo->prepare(
    'SELECT f.* FROM favorit v JOIN film f ON f.id=v.film_id WHERE v.pengguna_id=? ORDER BY v.dibuat_pada DESC',
);
$stmt->execute([$user->getId()]);
$rows = $stmt->fetchAll();
$judulHalaman = 'Film Favorit — Sineverse';
require __DIR__ . '/includes/header.php';
?>
<section class="section container"><div class="section-head"><div><h2>Film Favorit</h2><p>Simpan film dan pantau jadwal terbarunya.</p></div></div>
<?php if (!$rows): ?><div class="empty-state glass"><div class="icon"><?= ikon(
    'heart',
    40,
) ?></div><p>Belum ada film favorit.</p></div><?php else: ?><div class="film-grid"><?php foreach ($rows as $row):
    $film = Film::cariById(
        (int) $row['id'],
    ); ?><a class="film-card glass" href="film-detail.php?id=<?= $film->getId() ?>"><div class="film-poster <?= $film->punyaPosterAsli()
    ? 'has-image'
    : '' ?>" style="background:<?= amankan($film->getPosterWarna()) ?>33"><?php if (
    $film->punyaPosterAsli()
): ?><img class="film-poster-img" src="<?= amankan(
    $film->getPosterUrl(),
) ?>" alt=""><?php endif; ?></div><div class="film-body"><h3><?= amankan(
    $film->getJudul(),
) ?></h3><p class="genre"><?= amankan($film->getGenre()) ?></p></div></a><?php
endforeach; ?></div><?php endif; ?>
</section><?php require __DIR__ . '/includes/footer.php'; ?>
