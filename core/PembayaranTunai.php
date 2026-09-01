<?php
require_once __DIR__ . '/LayananPembayaran.php';

/**
 * CLASS PembayaranTunai extends LayananPembayaran
 * Dipakai khusus oleh aktor Kasir untuk transaksi walk-in di loket.
 */
class PembayaranTunai extends LayananPembayaran
{
    protected int $uangDiterima;
    protected int $kembalian;

    public function __construct(int $jumlah, int $uangDiterima)
    {
        parent::__construct($jumlah);
        $this->uangDiterima = $uangDiterima;
        $this->kembalian = max(0, $uangDiterima - $jumlah);
    }

    public function getKembalian(): int
    {
        return $this->kembalian;
    }

    public function proses(): bool
    {
        $cukup = $this->uangDiterima >= $this->jumlah;
        $this->konfirmasi($cukup);
        return $cukup;
    }

    public function getLabelMetode(): string
    {
        return 'Tunai (Loket)';
    }
}
