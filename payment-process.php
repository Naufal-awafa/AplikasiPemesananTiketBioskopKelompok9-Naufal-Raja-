<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/Tiket.php';
require_once __DIR__ . '/core/LayananPembayaran.php';
require_once __DIR__ . '/core/PembayaranTransferBank.php';
require_once __DIR__ . '/core/PembayaranEwallet.php';
require_once __DIR__ . '/core/PembayaranKartuKredit.php';
require_once __DIR__ . '/core/SistemPembayaran.php';
require_once __DIR__ . '/core/RincianHarga.php';

$user = wajibLogin(['customer', 'admin', 'kasir', 'manajer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$jadwalId = (int) ($_POST['jadwal_id'] ?? 0);
$kursiIds = array_filter(array_map('intval', explode(',', $_POST['kursi_ids'] ?? '')));
$metode = $_POST['metode_bayar'] ?? 'transfer';
$kodePromo = trim($_POST['kode_promo'] ?? '');
$produkQty = is_array($_POST['produk_qty'] ?? null) ? $_POST['produk_qty'] : [];
$produkUkuran = is_array($_POST['produk_ukuran'] ?? null) ? $_POST['produk_ukuran'] : [];
$poinDiminta = (int) ($_POST['poin_digunakan'] ?? 0);
$tokenReservasi = trim($_POST['token_reservasi'] ?? '');

$jadwal = Jadwal::cariById($jadwalId);
if (!$jadwal || empty($kursiIds)) {
    header('Location: index.php');
    exit();
}

$rincian = RincianHarga::hitung($jadwal, $kursiIds, $user, $kodePromo, $produkQty, $poinDiminta, $produkUkuran);
if (!$rincian || $rincian['total_akhir'] <= 0) {
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}
$kursiIds = $rincian['kursi_ids'];
$totalAkhir = $rincian['total_akhir'];

$pdo = Database::getInstance()->getKoneksi();

if (!FiturBioskop::reservasiValid($tokenReservasi, $user->getId(), $jadwalId, $kursiIds)) {
    $_SESSION['error_booking'] = 'Waktu reservasi kursi habis. Silakan pilih ulang.';
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}

try {
    // Transaksi MySQL menjaga pemeriksaan dan penyimpanan pesanan tetap atomik.
    $pdo->beginTransaction();

    if (array_intersect($kursiIds, $jadwal->getKursiTerpesan($user->getId()))) {
        $pdo->rollBack();
        $_SESSION['error_booking'] = 'Kursi yang dipilih sudah tidak tersedia. Silakan pilih kursi lain.';
        header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
        exit();
    }

    /**
     * >> DEMONSTRASI POLIMORFISME LEWAT PEWARISAN
     * Objek $layanan bisa berupa salah satu dari beberapa CHILD CLASS
     * (PembayaranTransferBank / PembayaranEwallet / PembayaranKartuKredit),
     * namun seluruhnya diperlakukan seragam sebagai LayananPembayaran
     * karena sama-sama mewarisi method proses() & getLabelMetode().
     */
    $layanan = match ($metode) {
        'ewallet' => new PembayaranEwallet($totalAkhir, $_POST['provider_ewallet'] ?? 'OVO'),
        'kartu' => new PembayaranKartuKredit($totalAkhir, $_POST['nomor_kartu'] ?? '0000000000000000'),
        default => new PembayaranTransferBank($totalAkhir, 'BCA'),
    };

    $berhasil = $layanan->proses(); // method polymorphic - perilaku beda tiap child class

    // Buat & simpan tiket
    $kodeTiket = Tiket::buatKodeTiketBaru();
    $tiket = new Tiket(
        null,
        $kodeTiket,
        $jadwalId,
        $user->getId(),
        $kursiIds,
        $totalAkhir,
        $layanan->getLabelMetode(),
        $berhasil ? 'lunas' : 'pending',
        '',
        '',
        null,
        $user->getRole(),
        $rincian['subtotal'],
        $rincian['diskon_jabatan_persen'],
        $rincian['potongan_jabatan'],
        $rincian['promo']?->getKode() ?? '',
        $rincian['potongan_promo'],
        $rincian['poin_digunakan'],
        $rincian['potongan_poin'],
        $rincian['total_produk'],
    );
    $tiket->simpan();

    $petaHargaKursi = [];
    $studio = $jadwal->getStudio();
    $film = $jadwal->getFilm();
    $hargaReguler = $film->hitungHargaTiket($studio->getTipe());
    $hargaVip = (int) round($hargaReguler * 1.35, -3);
    foreach (Kursi::untukStudio($studio->getId()) as $kursi) {
        $petaHargaKursi[$kursi->getId()] = $kursi->getTipe() === 'vip' ? $hargaVip : $hargaReguler;
    }
    $stmtDetail = $pdo->prepare('INSERT INTO detail_tiket (tiket_id,jadwal_id,kursi_id,harga) VALUES (?,?,?,?)');
    foreach ($kursiIds as $kursiId) {
        $stmtDetail->execute([$tiket->getId(), $jadwalId, $kursiId, $petaHargaKursi[$kursiId]]);
    }

    $stmtProduk = $pdo->prepare(
        'INSERT INTO pesanan_produk (tiket_id,produk_id,jumlah,harga_satuan,ukuran) VALUES (?,?,?,?,?)',
    );
    foreach ($rincian['produk'] as $produk) {
        $stmtProduk->execute([$tiket->getId(), $produk['id'], $produk['jumlah'], $produk['harga'], $produk['ukuran']]);
        $pdo->prepare('UPDATE produk SET stok=stok-? WHERE id=? AND stok>=?')->execute([
            $produk['jumlah'],
            $produk['id'],
            $produk['jumlah'],
        ]);
    }
    FiturBioskop::gunakanPoin($user->getId(), $tiket->getId(), $rincian['poin_digunakan']);
    if ($berhasil) {
        $poinBaru = FiturBioskop::tambahPoin($user->getId(), $tiket->getId(), $totalAkhir);
        FiturBioskop::notifikasi(
            $user->getId(),
            'Pembayaran berhasil',
            "Tiket {$kodeTiket} berhasil dibayar. Kamu mendapat {$poinBaru} poin.",
            'e-tiket.php?id=' . $tiket->getId(),
        );
    }
    FiturBioskop::hapusReservasi($tokenReservasi);
    FiturBioskop::audit($user->getId(), 'buat_pesanan', 'tiket', $tiket->getId(), $kodeTiket);

    // Catat transaksi lewat aktor "Sistem Pembayaran" (dummy gateway)
    SistemPembayaran::catatTransaksi(
        $tiket->getId(),
        $layanan->getKodeTransaksi(),
        $totalAkhir,
        $layanan->getLabelMetode(),
        $layanan->getStatus(),
    );
    $pesanNotifikasi = SistemPembayaran::kirimNotifikasi($berhasil, $kodeTiket);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Gagal memproses pembayaran: ' . $e->getMessage());
    $_SESSION['error_booking'] = 'Pembayaran belum dapat diproses. Silakan coba kembali.';
    header('Location: booking-kursi.php?jadwal_id=' . $jadwalId);
    exit();
}

$_SESSION['pesan_notifikasi'] = $pesanNotifikasi;
$_SESSION['pembayaran_berhasil'] = $berhasil;

header('Location: e-tiket.php?id=' . $tiket->getId());
exit();
