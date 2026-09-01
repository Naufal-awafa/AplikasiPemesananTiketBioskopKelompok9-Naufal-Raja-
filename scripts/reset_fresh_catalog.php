<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../core/TmdbService.php';

$jalankanReset = in_array('--execute', $argv, true);

// Kandidat diambil dari daftar now_playing TMDB pada 31 Agustus 2026.
$kandidat = [
    ['tmdb_id' => 969681, 'harga' => 55_000],  // Spider-Man: Brand New Day
    ['tmdb_id' => 1368337, 'harga' => 55_000], // The Odyssey
    ['tmdb_id' => 1288445, 'harga' => 45_000], // Mutiny
    ['tmdb_id' => 1516698, 'harga' => 40_000], // The Last Sunrise
    ['tmdb_id' => 1339713, 'harga' => 42_000], // Obsession
    ['tmdb_id' => 1084244, 'harga' => 48_000], // Toy Story 5
    ['tmdb_id' => 860508, 'harga' => 42_000],  // The Whisper Man
    ['tmdb_id' => 1315772, 'harga' => 45_000], // Minions & Monsters
    ['tmdb_id' => 1108427, 'harga' => 48_000], // Moana
    ['tmdb_id' => 1204680, 'harga' => 43_000], // Coyote vs. Acme
    ['tmdb_id' => 1384216, 'harga' => 43_000], // The Dog Stars
    ['tmdb_id' => 87513, 'harga' => 42_000],   // Motor City
];

$filmTerpilih = [];
foreach ($kandidat as $item) {
    $detail = TmdbService::detail($item['tmdb_id']);
    if ($detail['poster_url'] === '' || $detail['trailer_key'] === '') {
        fwrite(STDERR, "Lewati {$detail['judul']}: poster atau trailer tidak tersedia.\n");
        continue;
    }

    $detail['harga_dasar'] = $item['harga'];
    $filmTerpilih[] = $detail;

    if (count($filmTerpilih) === 8) {
        break;
    }
}

if (count($filmTerpilih) < 6) {
    throw new RuntimeException('Film dengan poster dan trailer lengkap kurang dari enam; reset dibatalkan.');
}

if (!$jalankanReset) {
    echo json_encode($filmTerpilih, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$pdo = Database::getInstance()->getKoneksi();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

try {
    $pdo->beginTransaction();

    $tabelRiwayat = [
        'pembayaran',
        'pesanan_produk',
        'poin_transaksi',
        'refund',
        'detail_tiket',
        'reservasi_kursi',
        'tiket',
        'ulasan',
        'favorit',
        'notifikasi',
        'verifikasi_email',
        'reset_password',
        'login_attempt',
        'audit_log',
        'shift_kasir',
        'jadwal',
        'film',
    ];

    foreach ($tabelRiwayat as $tabel) {
        $pdo->exec("DELETE FROM `{$tabel}`");
    }

    $pdo->exec("DELETE FROM pengguna WHERE role='customer'");

    $stokAwal = [
        'Popcorn Caramel' => 50,
        'Popcorn Salted' => 50,
        'Cola Regular' => 80,
        'Mineral Water' => 100,
        'Movie Combo' => 40,
    ];
    $ubahStok = $pdo->prepare('UPDATE produk SET stok=?,aktif=1 WHERE nama=?');
    foreach ($stokAwal as $nama => $stok) {
        $ubahStok->execute([$stok, $nama]);
    }

    $warnaPoster = ['#e63946', '#264653', '#f4a261', '#6d597a', '#2a9d8f', '#457b9d', '#8d99ae', '#e76f51'];
    $simpanFilm = $pdo->prepare(
        'INSERT INTO film
            (judul,genre,durasi,sinopsis,poster_warna,rating,status,harga_dasar,tmdb_id,poster_url,trailer_key,bahasa,rating_usia)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
    );

    $filmLokal = [];
    foreach ($filmTerpilih as $index => $film) {
        $sinopsis = trim($film['sinopsis']) ?: 'Sinopsis resmi belum tersedia.';
        $simpanFilm->execute([
            $film['judul'],
            $film['genre'],
            $film['durasi'],
            $sinopsis,
            $warnaPoster[$index % count($warnaPoster)],
            $film['rating'],
            'tayang',
            $film['harga_dasar'],
            $film['tmdb_id'],
            $film['poster_url'],
            $film['trailer_key'],
            'Internasional',
            '13+',
        ]);
        $filmLokal[] = [
            'id' => (int) $pdo->lastInsertId(),
            'judul' => $film['judul'],
        ];
    }

    $studioIds = $pdo->query('SELECT id FROM studio ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if (!$studioIds) {
        throw new RuntimeException('Studio belum tersedia; jadwal tidak dapat dibuat.');
    }

    $jamTayang = ['11:30', '13:45', '16:00', '18:30', '20:45'];
    $simpanJadwal = $pdo->prepare('INSERT INTO jadwal (film_id,studio_id,tanggal,jam) VALUES (?,?,?,?)');
    $hariIni = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));

    foreach ($filmLokal as $index => $film) {
        for ($putaran = 0; $putaran < 2; $putaran++) {
            $tanggal = $hariIni->modify('+' . (1 + $index % 4 + $putaran * 4) . ' days')->format('Y-m-d');
            $studioId = (int) $studioIds[($index + $putaran) % count($studioIds)];
            $jam = $jamTayang[($index + $putaran * 2) % count($jamTayang)];
            $simpanJadwal->execute([$film['id'], $studioId, $tanggal, $jam]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

echo 'Reset selesai: ' . count($filmTerpilih) . " film TMDB dan " . count($filmTerpilih) * 2 . " jadwal dibuat.\n";
