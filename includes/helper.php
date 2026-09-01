<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../core/Pengguna.php';
require_once __DIR__ . '/../core/Customer.php';
require_once __DIR__ . '/../core/Admin.php';
require_once __DIR__ . '/../core/Kasir.php';
require_once __DIR__ . '/../core/Manajer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && PHP_SAPI !== 'cli') {
    $tokenCsrf = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!hash_equals($_SESSION['csrf_token'], $tokenCsrf)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba kembali.');
    }
}

function inputCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') .
        '">';
}

/**
 * Factory sederhana: membaca 1 baris tabel pengguna lalu membangun
 * OBJEK sesuai role-nya (Customer/Admin/Kasir/Manajer). Ini adalah
 * contoh polimorfisme praktis dari hasil PEWARISAN class Pengguna.
 */
function buatObjekPengguna(array $baris): Pengguna
{
    $status = $baris['status'] ?? 'aktif';
    $objek = match ($baris['role']) {
        'admin' => new Admin(
            (int) $baris['id'],
            $baris['nama'],
            $baris['email'],
            $baris['password'],
            $baris['no_hp'],
            'admin',
            $baris['dibuat_pada'],
            $status,
        ),
        'kasir' => new Kasir(
            (int) $baris['id'],
            $baris['nama'],
            $baris['email'],
            $baris['password'],
            $baris['no_hp'],
            'kasir',
            $baris['dibuat_pada'],
            $status,
        ),
        'manajer' => new Manajer(
            (int) $baris['id'],
            $baris['nama'],
            $baris['email'],
            $baris['password'],
            $baris['no_hp'],
            'manajer',
            $baris['dibuat_pada'],
            $status,
        ),
        default => new Customer(
            (int) $baris['id'],
            $baris['nama'],
            $baris['email'],
            $baris['password'],
            $baris['no_hp'],
            'customer',
            $baris['dibuat_pada'],
            $status,
        ),
    };
    // Isi foto profil bila kolom `foto` sudah ada di tabel (aman kalau belum bermigrasi).
    if (isset($baris['foto'])) {
        $objek->setFoto((string) $baris['foto']);
    }
    return $objek;
}

function penggunaSaatIni(): ?Pengguna
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = Database::getInstance()->getKoneksi();
    $stmt = $pdo->prepare('SELECT * FROM pengguna WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $baris = $stmt->fetch();
    return $baris ? buatObjekPengguna($baris) : null;
}

function wajibLogin(array $rolesDiizinkan = []): Pengguna
{
    $user = penggunaSaatIni();
    if (!$user) {
        header('Location: ' . urlDasar() . 'login.php');
        exit();
    }
    // Jika akun dinonaktifkan/diblokir Admin di tengah sesi aktif, paksa logout.
    if (!$user->isAktif()) {
        session_destroy();
        header('Location: ' . urlDasar() . 'login.php?diblokir=1');
        exit();
    }
    if (!empty($rolesDiizinkan) && !in_array($user->getRole(), $rolesDiizinkan, true)) {
        header('Location: ' . urlDasar() . 'index.php');
        exit();
    }
    return $user;
}

/** Hak akses admin granular. Nilai kosong berarti akses penuh untuk kompatibilitas akun lama. */
function wajibAkses(string $izin): Pengguna
{
    $user = wajibLogin(['admin']);
    $stmt = Database::getInstance()->getKoneksi()->prepare('SELECT hak_akses FROM pengguna WHERE id=?');
    $stmt->execute([$user->getId()]);
    $hak = trim((string) $stmt->fetchColumn());
    if ($hak !== '' && !in_array($izin, array_map('trim', explode(',', $hak)), true)) {
        http_response_code(403);
        exit('Anda tidak memiliki hak akses untuk modul ini.');
    }
    return $user;
}

/** Menghitung path relatif ke root project agar link tetap benar dari subfolder (admin/, kasir/, dll). */
function urlDasar(): string
{
    $skrip = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $depth = substr_count(trim(dirname($skrip), '/'), '/');
    // Deteksi apakah kita sedang di dalam subfolder admin/kasir/manajer
    $folder = basename(dirname($skrip));
    return in_array($folder, ['admin', 'kasir', 'manajer'], true) ? '../' : '';
}

function formatRupiah(int $angka): string
{
    return 'Rp' . number_format($angka, 0, ',', '.');
}

function formatTanggalIndo(string $tanggal): string
{
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function amankan(?string $teks): string
{
    return htmlspecialchars($teks ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Mengembalikan nama file logo custom milik Sineverse jika sudah diunggah
 * ke folder assets/img/ (mendukung logo.svg, logo.png, logo.webp, logo.jpg).
 * Kalau belum ada file logo sama sekali, return null -> tampilan fallback
 * (ikon 🎬) tetap dipakai sehingga situs tidak pernah tampil rusak/kosong.
 * Urutan array menentukan prioritas format jika lebih dari satu ada.
 */
function logoFilename(): ?string
{
    static $hasil = null;
    static $sudahDicek = false;
    if ($sudahDicek) {
        return $hasil;
    }
    $sudahDicek = true;
    foreach (['logo.svg', 'logo.png', 'logo.webp', 'logo.jpg', 'logo.jpeg'] as $nama) {
        if (file_exists(__DIR__ . '/../assets/img/' . $nama)) {
            $hasil = $nama;
            break;
        }
    }
    return $hasil;
}

/**
 * Mengembalikan markup SVG inline (gaya Lucide/Heroicons, stroke=currentColor)
 * sehingga tidak lagi memakai emoji sebagai ikon. Nama yang didukung:
 * ticket, film, user, admin, kasir, manajer, bank, wallet, card, sparkles,
 * star, star-fill, calendar, clock, bell, menu, close, check, info, alert, alert-octagon, play,
 * arrow-right, search, seat, qrcode, mail, phone, lock, heart, ban, download.
 */
function ikon(string $nama, int $size = 20, string $kelas = ''): string
{
    static $map = [
        'ticket' =>
            '<path d="M3 8.5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1.5a2.5 2.5 0 0 0 0 5V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1.5a2.5 2.5 0 0 0 0-5V8.5z"/><path d="M13 6v12" stroke-dasharray="2 2.5"/>',
        'film' =>
            '<rect x="3" y="3" width="18" height="18" rx="2.5"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'admin' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'kasir' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'manajer' => '<path d="M4 20V10M10 20V4M16 20v-7M21 20V8"/>',
        'bank' => '<path d="M4 9l8-5 8 5"/><path d="M5 9v9M19 9v9M9 9v9M15 9v9"/><path d="M3 20h18"/>',
        'wallet' =>
            '<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1"/><rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="16.5" cy="13.5" r="1.3" fill="currentColor" stroke="none"/>',
        'card' => '<rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="M3 10h18M7 14h3"/>',
        'sparkles' =>
            '<path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3z"/><path d="M18 14l.8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8L18 14z"/>',
        'food' => '<path d="M4 13h16M6 13a6 6 0 0 1 12 0M12 5V3M3 17h18"/>',
        'drink' => '<path d="M6 4h12l-1.5 17h-9L6 4zM5 8h14M14 4l3-3"/>',
        'combo' => '<path d="M3 14h10v7H3zM5 14a3 3 0 0 1 6 0M15 5h6l-1 16h-4L15 5zM15 9h6"/>',
        'star' => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9L12 3.5z"/>',
        'star-fill' =>
            '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9L12 3.5z" fill="currentColor" stroke="none"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 9h18M8 3v4M16 3v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
        'alert-octagon' =>
            '<path d="M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86L7.86 2z"/><path d="M12 8v4M12 16h.01"/>',
        'ban' => '<circle cx="12" cy="12" r="9"/><path d="M5 5l14 14"/>',
        'play' => '<path d="M7 5l12 7-12 7z" fill="currentColor" stroke="none"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
        'seat' => '<path d="M6 4v8a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4"/><path d="M5 20h14"/>',
        'qrcode' =>
            '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v7M14 21h7"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7l8.5 6 8.5-6"/>',
        'phone' =>
            '<path d="M6 3h3l2 5-2.5 1.5a11 11 0 0 0 5 5L17 12l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2z"/>',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'heart' => '<path d="M12 20s-7-4.5-9.5-9A4.5 4.5 0 0 1 12 6a4.5 4.5 0 0 1 9.5 5C19 15.5 12 20 12 20z"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M21 20V8"/>',
        'home' => '<path d="M4 20V10l8-6 8 6v10"/><path d="M9 20v-6h6v6"/>',
        'download' => '<path d="M12 4v12M6 12l6 6 6-6"/><path d="M4 20h16"/>',
        'print' => '<path d="M6 9V4h12v5M6 10h12v9H6zM4 19h16"/><path d="M8 21h8"/>',
        'money' =>
            '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M2 10h20"/>',
        'smartphone' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/>',
        'camera' =>
            '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'receipt' =>
            '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trophy' => '<path d="M6 3h12v4H9v4c0 2 2 3.5 4 3.5s4-1.5 4-3.5V7H18V3z"/><path d="M10 17v3h4v-3"/>',
        'chair' =>
            '<path d="M2 10h6M2 14h6M2 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2zM8 10v4M16 10v4"/>',
        'film-slate' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M3 9h5l2-2h7M3 13h7l3-3h3"/>',
    ];
    $inner = $map[$nama] ?? $map['ticket'];
    $cls = $kelas !== '' ? ' ' . $kelas : '';
    return '<svg class="ico' .
        $cls .
        '" width="' .
        $size .
        '" height="' .
        $size .
        '" viewBox="0 0 24 24" ' .
        'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' .
        'aria-hidden="true" focusable="false">' .
        $inner .
        '</svg>';
}
