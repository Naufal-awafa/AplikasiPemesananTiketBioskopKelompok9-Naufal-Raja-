<?php
require_once __DIR__ . '/Pengguna.php';

/**
 * CLASS Kasir extends Pengguna
 * Mewarisi Pengguna, override method abstrak sesuai peran "kasir" ->
 * validasi QR tiket & memproses pemesanan walk-in di loket.
 */
class Kasir extends Pengguna
{
    public function getDashboardUrl(): string
    {
        return 'kasir/index.php';
    }

    public function getLabelPeran(): string
    {
        return 'Kasir';
    }

    public function getDiskonJabatanPersen(): int
    {
        return $this->diskonDariPengaturan('diskon_kasir', 10);
    }

    /**
     * Method khusus Kasir: mengecek format kode tiket sebelum
     * divalidasi ke database (validasi ringan sisi aplikasi).
     */
    public function formatKodeTiketValid(string $kode): bool
    {
        return (bool) preg_match('/^TIX-[A-Z0-9]{8,}$/', $kode);
    }
}
