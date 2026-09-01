<?php
require_once __DIR__ . '/LayananPembayaran.php';

/**
 * CLASS PembayaranEwallet extends LayananPembayaran
 * Mensimulasikan integrasi dummy dengan gateway seperti OVO/GoPay/Midtrans.
 * (Ecek-ecek: tidak benar-benar memanggil API pihak ketiga manapun.)
 */
class PembayaranEwallet extends LayananPembayaran
{
    protected string $provider; // OVO, GoPay, DANA, ShopeePay
    protected string $nomorTujuan;

    public function __construct(int $jumlah, string $provider = 'OVO', string $nomorTujuan = '')
    {
        parent::__construct($jumlah);
        $this->provider = $provider;
        $this->nomorTujuan = $nomorTujuan;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function proses(): bool
    {
        // Simulasi dummy gateway: 95% sukses, 5% gagal (untuk kesan realistis)
        $berhasil = random_int(1, 100) <= 95;
        $this->konfirmasi($berhasil);
        return $berhasil;
    }

    public function getLabelMetode(): string
    {
        return 'E-Wallet ' . $this->provider;
    }
}
