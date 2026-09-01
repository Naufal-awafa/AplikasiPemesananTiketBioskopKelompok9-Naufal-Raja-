<?php
require_once __DIR__ . '/LayananPembayaran.php';

/**
 * CLASS PembayaranTransferBank extends LayananPembayaran
 * Mewarisi struktur transaksi umum, override proses() dengan simulasi
 * pengecekan mutasi rekening (dummy, ecek-ecek - tidak konek bank asli).
 */
class PembayaranTransferBank extends LayananPembayaran
{
    protected string $namaBank;
    protected string $nomorVA; // nomor virtual account dummy

    public function __construct(int $jumlah, string $namaBank = 'BCA')
    {
        parent::__construct($jumlah); // memanggil constructor parent class
        $this->namaBank = $namaBank;
        $this->nomorVA = '8807' . random_int(100000000, 999999999);
    }

    public function getNamaBank(): string
    {
        return $this->namaBank;
    }
    public function getNomorVA(): string
    {
        return $this->nomorVA;
    }

    public function proses(): bool
    {
        // Simulasi dummy: transfer bank "selalu berhasil" setelah dicek
        $this->konfirmasi(true);
        return true;
    }

    public function getLabelMetode(): string
    {
        return 'Transfer Bank ' . $this->namaBank;
    }
}
