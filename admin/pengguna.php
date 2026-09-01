<?php
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../core/Admin.php';
require_once __DIR__ . '/../core/Pengguna.php';
require_once __DIR__ . '/../core/FiturBioskop.php';

$user = wajibAkses('pengguna');
$pdo = Database::getInstance()->getKoneksi();

$pesanSukses = '';
$pesanError = '';

// --- Tambah akun staff baru (admin/kasir/manajer) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_staff') {
    $data = [
        'nama' => trim($_POST['nama'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'no_hp' => trim($_POST['no_hp'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'cabang_id' => max(1, (int) ($_POST['cabang_id'] ?? 1)),
        'hak_akses' => implode(
            ',',
            array_intersect((array) ($_POST['hak_akses'] ?? []), [
                'film',
                'jadwal',
                'studio',
                'promo',
                'laporan',
                'pengguna',
                'operasional',
            ]),
        ),
    ];

    // >> Method khusus Admin (validasiDataStaff) sebelum akun staff dibuat
    if (!$user->validasiDataStaff($data)) {
        $pesanError = 'Lengkapi nama, email, password (min. 6 karakter), dan peran dengan benar.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $pesanError = 'Format email tidak valid.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM pengguna WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $pesanError = 'Email sudah terdaftar, gunakan email lain.';
        } else {
            $hash = Pengguna::buatHash($data['password']);
            $stmt = $pdo->prepare(
                'INSERT INTO pengguna (nama, email, password, no_hp, role, status, cabang_id, hak_akses, email_terverifikasi) VALUES (?,?,?,?,?,?,?,?,1)',
            );
            $stmt->execute([
                $data['nama'],
                $data['email'],
                $hash,
                $data['no_hp'],
                $data['role'],
                'aktif',
                $data['cabang_id'],
                $data['hak_akses'],
            ]);
            FiturBioskop::audit($user->getId(), 'tambah', 'pengguna', (int) $pdo->lastInsertId(), $data['role']);
            $pesanSukses = 'Akun ' . $data['role'] . ' baru berhasil dibuat.';
        }
    }
}

// --- Ban / aktifkan akun customer ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle_status_customer') {
    $idTarget = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM pengguna WHERE id = ? AND role = ?');
    $stmt->execute([$idTarget, 'customer']);
    $baris = $stmt->fetch();
    if ($baris) {
        $statusBaru = $baris['status'] === 'aktif' ? 'banned' : 'aktif';
        $stmt = $pdo->prepare('UPDATE pengguna SET status = ? WHERE id = ?');
        $stmt->execute([$statusBaru, $idTarget]);
        FiturBioskop::audit($user->getId(), 'ubah_status', 'pengguna', $idTarget, $statusBaru);
        $pesanSukses =
            $statusBaru === 'banned'
                ? "Akun customer \"{$baris['nama']}\" berhasil diblokir."
                : "Akun customer \"{$baris['nama']}\" berhasil diaktifkan kembali.";
    }
}

// --- Hapus akun staff (tidak bisa menghapus akun sendiri) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus_staff') {
    $idTarget = (int) $_POST['id'];
    if ($idTarget !== $user->getId()) {
        $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id = ? AND role IN ('admin','kasir','manajer')");
        $stmt->execute([$idTarget]);
        $pesanSukses = 'Akun staff berhasil dihapus.';
    } else {
        $pesanError = 'Anda tidak dapat menghapus akun Anda sendiri.';
    }
}

$daftarStaff = $pdo
    ->query("SELECT * FROM pengguna WHERE role IN ('admin','kasir','manajer') ORDER BY role ASC, nama ASC")
    ->fetchAll();
$daftarCustomer = $pdo->query("SELECT * FROM pengguna WHERE role = 'customer' ORDER BY id DESC")->fetchAll();
$daftarCabang = $pdo->query('SELECT * FROM cabang WHERE aktif=1 ORDER BY nama')->fetchAll();
$namaCabang = array_column($daftarCabang, 'nama', 'id');

$judulHalaman = 'Kelola Pengguna — Admin';
require __DIR__ . '/../includes/header.php';
$menuAktif = 'pengguna';
require __DIR__ . '/../includes/dash-open.php';
?>

<div class="section-head reveal">
    <div><h2>Kelola Pengguna</h2><p>Buat akun staff baru dan kelola status akun customer.</p></div>
</div>

<?php if ($pesanSukses): ?><div class="alert alert-success" data-autohide><?= amankan(
    $pesanSukses,
) ?></div><?php endif; ?>
<?php if ($pesanError): ?><div class="alert alert-error" data-autohide><?= amankan($pesanError) ?></div><?php endif; ?>

<div class="grid-2" style="grid-template-columns: 1fr 360px; align-items:flex-start;">
    <div>
        <div class="card glass reveal mb-24">
            <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
                'user',
                18,
            ) ?> Akun Staff (Admin / Kasir / Manajer)</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Cabang / Akses</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($daftarStaff as $s): ?>
                        <tr>
                            <td><?= amankan($s['nama']) ?> <?= (int) $s['id'] === $user->getId()
     ? '<span class="pill pill-info">Anda</span>'
     : '' ?></td>
                            <td><?= amankan($s['email']) ?></td>
                            <td><span class="pill pill-info"><?= strtoupper($s['role']) ?></span></td>
                            <td><?= amankan($namaCabang[$s['cabang_id']] ?? '-') ?><br><small><?= amankan(
    $s['hak_akses'] ?: 'Semua modul',
) ?></small></td>
                            <td><span class="pill <?= $s['status'] === 'aktif'
                                ? 'pill-success'
                                : 'pill-danger' ?>"><?= strtoupper($s['status']) ?></span></td>
                            <td>
                                <?php if ((int) $s['id'] !== $user->getId()): ?>
                                    <form method="POST" onsubmit="return confirm('Hapus akun staff ini?')"><input type="hidden" name="aksi" value="hapus_staff"><input type="hidden" name="id" value="<?= $s[
                                        'id'
                                    ] ?>"><button class="btn btn-danger btn-sm">Hapus</button></form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass reveal">
            <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon('user', 18) ?> Akun Customer</h3>
            <?php if (empty($daftarCustomer)): ?>
                <p class="text-muted" style="font-size:.87rem;">Belum ada customer yang terdaftar.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Nama</th><th>Email</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($daftarCustomer as $c): ?>
                            <tr>
                                <td><?= amankan($c['nama']) ?></td>
                                <td><?= amankan($c['email']) ?></td>
                                <td><span class="pill <?= $c['status'] === 'aktif'
                                    ? 'pill-success'
                                    : 'pill-danger' ?>"><?= $c['status'] === 'aktif'
    ? 'AKTIF'
    : 'DIBLOKIR' ?></span></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('<?= $c['status'] === 'aktif'
                                        ? 'Blokir'
                                        : 'Aktifkan kembali' ?> akun ini?');">
                                        <input type="hidden" name="aksi" value="toggle_status_customer">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn <?= $c['status'] === 'aktif'
                                            ? 'btn-danger'
                                            : 'btn-outline' ?> btn-sm">
                                            <?= $c['status'] === 'aktif'
                                                ? ikon('ban', 16) . ' Blokir'
                                                : ikon('check', 16) . ' Aktifkan' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card glass reveal">
        <h3 style="font-size:1rem; font-weight:800; margin-bottom:16px;"><?= ikon(
            'plus',
            18,
        ) ?> Tambah Akun Staff Baru</h3>
        <form method="POST">
            <input type="hidden" name="aksi" value="tambah_staff">
            <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" class="form-control" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label>No. HP</label><input type="text" name="no_hp" class="form-control"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required></div>
            <div class="form-group">
                <label>Peran</label>
                <select name="role" class="form-control" required>
                    <option value="kasir">Kasir</option>
                    <option value="manajer">Manajer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group"><label>Cabang</label><select name="cabang_id" class="form-control"><?php foreach (
                $daftarCabang
                as $c
            ): ?><option value="<?= $c['id'] ?>"><?= amankan(
    $c['nama'] . ' — ' . $c['kota'],
) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Hak akses admin (kosong = semua)</label><div class="permission-grid"><?php foreach (
                ['film', 'jadwal', 'studio', 'promo', 'laporan', 'pengguna', 'operasional']
                as $izin
            ): ?><label><input type="checkbox" name="hak_akses[]" value="<?= $izin ?>"> <?= ucfirst(
    $izin,
) ?></label><?php endforeach; ?></div></div>
            <button type="submit" class="btn btn-primary btn-block">Buat Akun Staff</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/dash-close.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
