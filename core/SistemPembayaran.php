<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * CLASS SistemPembayaran
 * ---------------------------------------------------------------------
 * Merepresentasikan AKTOR "Sistem Pembayaran" (gateway dummy ala
 * Midtrans/OVO - ecek-ecek, TIDAK terhubung ke API asli manapun).
 * Bertugas: generate nomor pembayaran, konfirmasi transaksi, dan
 * "mengirim notifikasi" sukses/gagal ke pemesan.
 */
class SistemPembayaran
{
    /** Membuat nomor pembayaran/kode transaksi unik. */
    public static function generateNomorPembayaran(): string
    {
        return 'NP' . date('ymdHis') . random_int(100, 999);
    }

    /**
     * Menyimpan catatan transaksi pembayaran ke tabel `pembayaran`
     * dan mengaitkannya dengan tiket terkait.
     */
    public static function catatTransaksi(
        int $tiketId,
        string $kodeTransaksi,
        int $jumlah,
        string $metode,
        string $status,
    ): int {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare(
            'INSERT INTO pembayaran (tiket_id, kode_transaksi, jumlah, metode, status) VALUES (?,?,?,?,?)',
        );
        $stmt->execute([$tiketId, $kodeTransaksi, $jumlah, $metode, $status]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Simulasi "konfirmasi transaksi" dari gateway pembayaran eksternal.
     * Pada gateway asli ini biasanya berupa callback/webhook.
     */
    public static function konfirmasiTransaksi(string $kodeTransaksi, bool $berhasil): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('UPDATE pembayaran SET status = ? WHERE kode_transaksi = ?');
        $stmt->execute([$berhasil ? 'sukses' : 'gagal', $kodeTransaksi]);
    }

    /**
     * Simulasi pengiriman notifikasi ke customer (dummy - dalam sistem
     * nyata bisa lewat email/SMS/WA gateway). Di sini cukup dikembalikan
     * sebagai string pesan untuk ditampilkan di UI.
     */
    public static function kirimNotifikasi(bool $berhasil, string $kodeTiket): string
    {
        return $berhasil
            ? "Pembayaran untuk tiket {$kodeTiket} berhasil dikonfirmasi. E-tiket telah diterbitkan."
            : "Pembayaran untuk tiket {$kodeTiket} gagal diproses. Silakan coba metode lain.";
    }
}
