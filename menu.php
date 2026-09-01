<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/RincianHarga.php';

$pdo = Database::getInstance()->getKoneksi();
$produkList = $pdo
    ->query("SELECT * FROM produk WHERE aktif=1 AND stok>0 ORDER BY FIELD(kategori,'combo','makanan','minuman'),nama")
    ->fetchAll();
$judulHalaman = 'Snack & Minuman — Sineverse Cinema';
require __DIR__ . '/includes/header.php';
?>

<section class="menu-hero container">
    <div class="menu-hero-copy reveal">
        <span class="section-kicker">Teman nonton</span>
        <h1>Snack, Minuman<br>&amp; Paket Combo</h1>
        <p>Pilih camilan favoritmu dan tambahkan saat checkout tiket. Semua produk diambil di area pengambilan sebelum masuk studio.</p>
        <a href="index.php#daftar-film" class="btn btn-primary">Pilih Film <?= ikon('arrow-right', 17) ?></a>
    </div>
    <div class="menu-how glass reveal">
        <span>01</span><p><b>Pilih film dan jadwal</b><small>Tentukan film, waktu tayang, serta kursi.</small></p>
        <span>02</span><p><b>Tambahkan produk</b><small>Pilih snack atau minuman di halaman checkout.</small></p>
        <span>03</span><p><b>Ambil pesanan</b><small>Tunjukkan tiket di area pengambilan.</small></p>
    </div>
</section>

<section class="section container menu-catalog">
    <div class="section-head reveal">
        <div><h2>Daftar Menu</h2><p>Harga dan stok terbaru yang tersedia saat ini.</p></div>
        <div class="tabs glass" aria-label="Filter kategori produk">
            <button class="active" data-tab-target="semua">Semua</button>
            <button data-tab-target="makanan">Makanan</button>
            <button data-tab-target="minuman">Minuman</button>
            <button data-tab-target="combo">Combo</button>
        </div>
    </div>

    <?php if ($produkList): ?>
        <div class="menu-product-grid">
            <?php foreach ($produkList as $produk):

                $kategori = in_array($produk['kategori'], ['makanan', 'minuman', 'combo'], true)
                    ? $produk['kategori']
                    : 'makanan';
                $label = ['makanan' => 'Makanan', 'minuman' => 'Minuman', 'combo' => 'Paket Combo'][$kategori];
                $gambar = trim((string) ($produk['gambar'] ?? ''));
                $punyaGambar = $gambar !== '' && is_file(__DIR__ . '/' . $gambar);
                $ikonProduk = $kategori === 'makanan' ? 'food' : ($kategori === 'minuman' ? 'drink' : 'combo');
                $pilihanUkuran = RincianHarga::pilihanUkuran($kategori);
                $hargaUkuran = array_map(
                    fn($kode) => RincianHarga::hargaUkuran((int) $produk['harga'], $kategori, $kode),
                    array_keys($pilihanUkuran),
                );
                ?>
                <article class="menu-product-card glass reveal" data-tab-group="<?= $kategori ?>">
                    <div class="menu-product-media <?= $punyaGambar ? 'has-image' : '' ?>">
                        <?php if ($punyaGambar): ?>
                            <img src="<?= $base . amankan($gambar) ?>" alt="<?= amankan(
    $produk['nama'],
) ?>" loading="lazy">
                        <?php else: ?>
                            <span><?= ikon($ikonProduk, 42) ?></span>
                            <small>Foto belum tersedia</small>
                        <?php endif; ?>
                        <span class="menu-category-chip"><?= $label ?></span>
                    </div>
                    <div class="menu-product-body">
                        <div><h3><?= amankan($produk['nama']) ?></h3><p>Tersedia <?= (int) $produk[
    'stok'
] ?> porsi</p></div>
                        <strong><small>Mulai</small><?= formatRupiah(min($hargaUkuran)) ?></strong>
                    </div>
                    <div class="menu-size-options" aria-label="Pilihan ukuran">
                        <?php foreach ($pilihanUkuran as $kodeUkuran => $opsiUkuran): ?><span><?= amankan(
    $opsiUkuran['label'],
) ?><small><?= formatRupiah(
    RincianHarga::hargaUkuran((int) $produk['harga'], $kategori, $kodeUkuran),
) ?></small></span><?php endforeach; ?>
                    </div>
                </article>
            <?php
            endforeach; ?>
        </div>
        <div class="menu-order-note glass reveal"><?= ikon(
            'info',
            18,
        ) ?><span>Menu dapat ditambahkan saat checkout tiket atau dibeli terpisah langsung melalui kasir.</span></div>
    <?php else: ?>
        <div class="empty-state glass"><div class="icon"><?= ikon(
            'food',
            40,
        ) ?></div><p>Menu sedang tidak tersedia.</p></div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
