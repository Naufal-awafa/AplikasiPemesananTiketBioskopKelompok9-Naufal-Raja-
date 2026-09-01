<?php
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/core/Tiket.php';
require_once __DIR__ . '/core/Jadwal.php';
require_once __DIR__ . '/core/FiturBioskop.php';

$user = wajibLogin(['customer', 'admin', 'kasir', 'manajer']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tiketId = (int) ($_POST['tiket_id'] ?? 0);
    $alasan = trim($_POST['alasan'] ?? 'Dibatalkan oleh pemesan');
    $tiket = Tiket::cariById($tiketId);
    if (
        $tiket &&
        $tiket->getCustomerId() === $user->getId() &&
        in_array($tiket->getStatus(), ['pending', 'lunas'], true)
    ) {
        $jadwal = Jadwal::cariById($tiket->getJadwalId());
        $selisih = $jadwal ? strtotime($jadwal->getTanggal() . ' ' . $jadwal->getJam()) - time() : 0;
        $persen = $selisih >= 86400 ? 100 : ($selisih >= 7200 ? 50 : 0);
        if ($persen === 0) {
            $_SESSION['error_global'] = 'Tiket tidak dapat dibatalkan kurang dari 2 jam sebelum tayang.';
            header('Location: riwayat.php');
            exit();
        }
        $jumlah = (int) round(($tiket->getTotalHarga() * $persen) / 100);
        $pdo = Database::getInstance()->getKoneksi();
        $pdo->beginTransaction();
        $tiket->batalkan();
        $pdo->prepare('DELETE FROM detail_tiket WHERE tiket_id=?')->execute([$tiketId]);
        $pdo->prepare(
            "INSERT IGNORE INTO refund (tiket_id,pengguna_id,jumlah,persentase,alasan,status) VALUES (?,?,?,?,?,'diproses')",
        )->execute([$tiketId, $user->getId(), $jumlah, $persen, $alasan]);
        $pdo->prepare("UPDATE tiket SET refund_status='diproses' WHERE id=?")->execute([$tiketId]);
        $items = $pdo->prepare('SELECT produk_id,jumlah FROM pesanan_produk WHERE tiket_id=?');
        $items->execute([$tiketId]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare('UPDATE produk SET stok=stok+? WHERE id=?')->execute([$item['jumlah'], $item['produk_id']]);
        }
        $poinStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN jumlah>0 THEN jumlah ELSE 0 END),0) masuk, COALESCE(SUM(CASE WHEN jumlah<0 THEN -jumlah ELSE 0 END),0) keluar FROM poin_transaksi WHERE tiket_id=?',
        );
        $poinStmt->execute([$tiketId]);
        $poin = $poinStmt->fetch();
        $koreksi = (int) $poin['keluar'] - (int) $poin['masuk'];
        if ($koreksi !== 0) {
            $pdo->prepare('UPDATE pengguna SET poin=GREATEST(0,poin+?) WHERE id=?')->execute([
                $koreksi,
                $user->getId(),
            ]);
            $pdo->prepare(
                "INSERT INTO poin_transaksi (pengguna_id,tiket_id,tipe,jumlah,keterangan) VALUES (?,?,'refund',?,'Koreksi poin pembatalan')",
            )->execute([$user->getId(), $tiketId, $koreksi]);
        }
        $pdo->commit();
        FiturBioskop::notifikasi(
            $user->getId(),
            'Refund diproses',
            "Refund {$persen}% sebesar " . formatRupiah($jumlah) . ' sedang diproses.',
            'riwayat.php',
        );
        FiturBioskop::audit($user->getId(), 'batalkan_dan_refund', 'tiket', $tiketId, "{$persen}% - {$alasan}");
        $_SESSION['success_global'] = 'Tiket dibatalkan. Refund ' . formatRupiah($jumlah) . ' sedang diproses.';
    }
}

header('Location: riwayat.php');
exit();
