<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/FiturBioskop.php';
$user = wajibAkses('operasional');
$pdo = Database::getInstance()->getKoneksi();
$pesan = '';
$tipePesan = 'success';

function simpanGambarProduk(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gambar gagal diunggah.');
    }
    if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 3 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($ekstensi[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Gunakan gambar JPG, PNG, atau WEBP yang valid.');
    }
    $folder = __DIR__ . '/../assets/img/produk';
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        throw new RuntimeException('Folder gambar produk tidak dapat dibuat.');
    }
    $nama = 'produk-' . bin2hex(random_bytes(8)) . '.' . $ekstensi[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $nama)) {
        throw new RuntimeException('Gambar tidak dapat disimpan.');
    }
    return 'assets/img/produk/' . $nama;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'pengaturan') {
            foreach (
                [
                    'diskon_kasir',
                    'diskon_admin',
                    'diskon_manajer',
                    'durasi_reservasi',
                    'batas_diskon_staff_bulanan',
                    'nilai_satu_poin',
                    'poin_per_10000',
                ]
                as $kunci
            ) {
                FiturBioskop::simpanPengaturan($kunci, (string) max(0, (int) ($_POST[$kunci] ?? 0)));
            }
            FiturBioskop::audit($user->getId(), 'ubah', 'pengaturan_sistem');
            $pesan = 'Pengaturan berhasil disimpan.';
        } elseif ($aksi === 'produk') {
            $gambar = simpanGambarProduk($_FILES['gambar'] ?? []);
            $nama = trim($_POST['nama'] ?? '');
            $kategori = in_array($_POST['kategori'] ?? '', ['makanan', 'minuman', 'combo'], true)
                ? $_POST['kategori']
                : 'makanan';
            if ($nama === '') {
                throw new RuntimeException('Nama produk wajib diisi.');
            }
            $data = [
                $nama,
                $kategori,
                max(0, (int) ($_POST['harga'] ?? 0)),
                max(0, (int) ($_POST['stok'] ?? 0)),
                isset($_POST['aktif']) ? 1 : 0,
                $gambar,
            ];
            $pdo->prepare('INSERT INTO produk (nama,kategori,harga,stok,aktif,gambar) VALUES (?,?,?,?,?,?)')->execute(
                $data,
            );
            $id = (int) $pdo->lastInsertId();
            FiturBioskop::audit($user->getId(), 'tambah', 'produk', $id);
            $pesan = 'Produk berhasil ditambahkan.';
        } elseif ($aksi === 'edit_produk') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $nama = trim($_POST['nama'] ?? '');
            $kategori = in_array($_POST['kategori'] ?? '', ['makanan', 'minuman', 'combo'], true)
                ? $_POST['kategori']
                : 'makanan';
            if ($id === 0 || $nama === '') {
                throw new RuntimeException('Data produk tidak valid.');
            }
            $stmt = $pdo->prepare('UPDATE produk SET nama=?,kategori=?,harga=?,stok=?,aktif=? WHERE id=?');
            $stmt->execute([
                $nama,
                $kategori,
                max(0, (int) ($_POST['harga'] ?? 0)),
                max(0, (int) ($_POST['stok'] ?? 0)),
                isset($_POST['aktif']) ? 1 : 0,
                $id,
            ]);
            FiturBioskop::audit($user->getId(), 'ubah', 'produk', $id);
            $pesan = 'Produk berhasil diperbarui.';
        } elseif ($aksi === 'hapus_produk') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM pesanan_produk WHERE produk_id=?');
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Produk sudah memiliki riwayat transaksi dan tidak dapat dihapus. Edit produk lalu ubah statusnya menjadi Nonaktif.',
                );
            }
            $stmt = $pdo->prepare('DELETE FROM produk WHERE id=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }
            FiturBioskop::audit($user->getId(), 'hapus', 'produk', $id);
            $pesan = 'Produk berhasil dihapus.';
        } elseif ($aksi === 'gambar_produk') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $gambar = simpanGambarProduk($_FILES['gambar'] ?? []);
            if ($gambar === '') {
                throw new RuntimeException('Pilih gambar produk terlebih dahulu.');
            }
            $pdo->prepare('UPDATE produk SET gambar=? WHERE id=?')->execute([$gambar, $id]);
            FiturBioskop::audit($user->getId(), 'ubah_gambar', 'produk', $id);
            $pesan = 'Gambar produk berhasil diperbarui.';
        } elseif ($aksi === 'cabang') {
            $pdo->prepare('INSERT INTO cabang (nama,kota,alamat,fasilitas) VALUES (?,?,?,?)')->execute([
                trim($_POST['nama'] ?? ''),
                trim($_POST['kota'] ?? ''),
                trim($_POST['alamat'] ?? ''),
                trim($_POST['fasilitas'] ?? ''),
            ]);
            FiturBioskop::audit($user->getId(), 'tambah', 'cabang', (int) $pdo->lastInsertId());
            $pesan = 'Cabang berhasil ditambahkan.';
        } elseif ($aksi === 'refund') {
            $id = (int) ($_POST['id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['diproses', 'disetujui', 'ditolak', 'selesai'], true)
                ? $_POST['status']
                : 'diproses';
            $pdo->prepare('UPDATE refund SET status=? WHERE id=?')->execute([$status, $id]);
            $pdo->prepare(
                'UPDATE tiket SET refund_status=? WHERE id=(SELECT tiket_id FROM refund WHERE id=?)',
            )->execute([$status, $id]);
            FiturBioskop::audit($user->getId(), 'ubah_status', 'refund', $id, $status);
            $pesan = 'Status refund diperbarui.';
        }
    } catch (Throwable $e) {
        $pesan = $e->getMessage();
        $tipePesan = 'error';
    }
}
$produk = $pdo->query('SELECT * FROM produk ORDER BY kategori,nama')->fetchAll();
$cabang = $pdo->query('SELECT * FROM cabang ORDER BY id')->fetchAll();
$produkEdit = null;
if (isset($_GET['edit_produk'])) {
    $stmt = $pdo->prepare('SELECT * FROM produk WHERE id=?');
    $stmt->execute([max(0, (int) $_GET['edit_produk'])]);
    $produkEdit = $stmt->fetch() ?: null;
}
$refund = $pdo
    ->query(
        'SELECT r.*,t.kode_tiket,p.nama FROM refund r JOIN tiket t ON t.id=r.tiket_id JOIN pengguna p ON p.id=r.pengguna_id ORDER BY r.id DESC',
    )
    ->fetchAll();
$audit = $pdo
    ->query('SELECT a.*,p.nama FROM audit_log a LEFT JOIN pengguna p ON p.id=a.pengguna_id ORDER BY a.id DESC LIMIT 50')
    ->fetchAll();
$judulHalaman = 'Operasional — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'operasional';
require __DIR__ . '/../includes/dash-open.php';
?>
<div class="section-head"><div><h2>Pusat Operasional</h2><p>Konfigurasi benefit, cabang, produk, refund, audit, dan backup.</p></div><a class="btn btn-outline" href="backup.php"><?= ikon(
    'download',
    18,
) ?> Unduh Backup DB</a></div>
<?php if ($pesan): ?><div class="alert alert-<?= $tipePesan ?>"><?= amankan($pesan) ?></div><?php endif; ?>
<div class="card glass mb-24"><h3>Pengaturan Sistem</h3><form method="POST" class="settings-grid mt-16"><input type="hidden" name="aksi" value="pengaturan">
<?php foreach (
    [
        'diskon_kasir' => 'Diskon Kasir (%)',
        'diskon_admin' => 'Diskon Admin (%)',
        'diskon_manajer' => 'Diskon Manajer (%)',
        'durasi_reservasi' => 'Reservasi (menit)',
        'batas_diskon_staff_bulanan' => 'Batas benefit/bulan',
        'nilai_satu_poin' => 'Nilai 1 poin (Rp)',
        'poin_per_10000' => 'Poin per Rp10.000',
    ]
    as $k => $label
): ?><div class="form-group"><label><?= $label ?></label><input class="form-control" type="number" name="<?= $k ?>" value="<?= (int) FiturBioskop::pengaturan(
    $k,
    '0',
) ?>"></div><?php endforeach; ?><button class="btn btn-primary">Simpan Pengaturan</button></form></div>
<div class="grid-2 mb-24"><div class="card glass"><h3><?= $produkEdit
    ? 'Edit Produk'
    : 'Tambah Produk' ?></h3><form method="POST" enctype="multipart/form-data" class="mt-16"><input type="hidden" name="aksi" value="<?= $produkEdit
    ? 'edit_produk'
    : 'produk' ?>"><?php if ($produkEdit): ?><input type="hidden" name="id" value="<?= (int) $produkEdit[
    'id'
] ?>"><?php endif; ?><div class="form-group"><label>Nama</label><input class="form-control" name="nama" value="<?= amankan(
    $produkEdit['nama'] ?? '',
) ?>" required></div><div class="grid-2"><div class="form-group"><label>Kategori</label><select class="form-control" name="kategori"><?php foreach (
    ['makanan', 'minuman', 'combo']
    as $kategori
): ?><option value="<?= $kategori ?>" <?= ($produkEdit['kategori'] ?? 'makanan') === $kategori
    ? 'selected'
    : '' ?>><?= $kategori ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Harga</label><input class="form-control" type="number" min="0" name="harga" value="<?= (int) ($produkEdit[
    'harga'
] ??
    0) ?>" required></div></div><div class="form-group"><label>Stok</label><input class="form-control" type="number" min="0" name="stok" value="<?= (int) ($produkEdit[
    'stok'
] ?? 0) ?>" required></div><?php if (
    !$produkEdit
): ?><div class="form-group"><label>Gambar Produk</label><input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG, atau WEBP. Maksimal 3 MB.</small></div><?php endif; ?><label><input type="checkbox" name="aktif" <?= !$produkEdit ||
!empty($produkEdit['aktif'])
    ? 'checked'
    : '' ?>> Aktif</label><button class="btn btn-primary btn-block mt-16"><?= $produkEdit
    ? 'Simpan Perubahan'
    : 'Tambah Produk' ?></button><?php if (
    $produkEdit
): ?><a href="operasional.php" class="btn btn-ghost btn-block mt-8">Batal Edit</a><?php endif; ?></form></div>
<div class="card glass"><h3>Tambah Cabang</h3><form method="POST" class="mt-16"><input type="hidden" name="aksi" value="cabang"><div class="form-group"><label>Nama</label><input class="form-control" name="nama" required></div><div class="form-group"><label>Kota</label><input class="form-control" name="kota" required></div><div class="form-group"><label>Alamat</label><input class="form-control" name="alamat"></div><div class="form-group"><label>Fasilitas</label><input class="form-control" name="fasilitas" placeholder="IMAX, Lounge, Parkir"></div><button class="btn btn-primary btn-block">Tambah Cabang</button></form></div></div>
<div class="table-wrap glass mb-24"><table class="data-table"><thead><tr><th>Gambar</th><th>Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th><th>Ubah Gambar</th></tr></thead><tbody><?php foreach (
    $produk
    as $p
): ?><tr><td><?php if (!empty($p['gambar'])): ?><img class="admin-product-thumb" src="<?= $base .
    amankan($p['gambar']) ?>" alt=""><?php else: ?><span class="admin-product-placeholder"><?= ikon(
    'food',
    20,
) ?></span><?php endif; ?></td><td><?= amankan($p['nama']) ?></td><td><?= amankan(
    $p['kategori'],
) ?></td><td><?= formatRupiah((int) $p['harga']) ?></td><td><?= $p['stok'] ?></td><td><?= $p['aktif']
    ? 'Aktif'
    : 'Nonaktif' ?></td><td><div class="flex gap-8"><a href="operasional.php?edit_produk=<?= (int) $p[
    'id'
] ?>" class="btn btn-ghost btn-sm">Edit</a><form method="POST" onsubmit="return confirm('Hapus produk ini? Produk yang sudah memiliki riwayat transaksi tidak dapat dihapus.')"><input type="hidden" name="aksi" value="hapus_produk"><input type="hidden" name="id" value="<?= (int) $p[
    'id'
] ?>"><button class="btn btn-danger btn-sm">Hapus</button></form></div></td><td><form method="POST" enctype="multipart/form-data" class="product-image-form"><input type="hidden" name="aksi" value="gambar_produk"><input type="hidden" name="id" value="<?= (int) $p[
    'id'
] ?>"><input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-outline btn-sm">Unggah</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<div class="table-wrap glass mb-24"><table class="data-table"><thead><tr><th>Refund</th><th>Pemesan</th><th>Jumlah</th><th>Alasan</th><th>Status</th></tr></thead><tbody><?php foreach (
    $refund
    as $r
): ?><tr><td><?= amankan($r['kode_tiket']) ?></td><td><?= amankan($r['nama']) ?></td><td><?= formatRupiah(
    (int) $r['jumlah'],
) ?> (<?= $r['persentase'] ?>%)</td><td><?= amankan(
    $r['alasan'],
) ?></td><td><form method="POST" class="flex gap-8"><input type="hidden" name="aksi" value="refund"><input type="hidden" name="id" value="<?= $r[
    'id'
] ?>"><select class="form-control" name="status"><?php foreach (
    ['diproses', 'disetujui', 'ditolak', 'selesai']
    as $s
): ?><option <?= $r['status'] === $s
    ? 'selected'
    : '' ?>><?= $s ?></option><?php endforeach; ?></select><button class="btn btn-primary btn-sm">Simpan</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<div class="table-wrap glass"><h3 style="padding:20px">Audit Log Terbaru</h3><table class="data-table"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Detail</th><th>IP</th></tr></thead><tbody><?php foreach (
    $audit
    as $a
): ?><tr><td><?= date('d/m H:i', strtotime($a['dibuat_pada'])) ?></td><td><?= amankan(
    $a['nama'] ?? 'Sistem',
) ?></td><td><?= amankan($a['aksi']) ?></td><td><?= amankan($a['entitas']) .
    ' #' .
    (int) $a['entitas_id'] ?></td><td><?= amankan($a['detail']) ?></td><td><?= amankan(
    $a['ip_address'],
) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php
require __DIR__ . '/../includes/dash-close.php';
require __DIR__ . '/../includes/footer.php';
 ?>
