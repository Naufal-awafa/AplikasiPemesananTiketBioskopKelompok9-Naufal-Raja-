<?php
require_once __DIR__ . '/Pengguna.php';

/**
 * CLASS Manajer extends Pengguna
 * Mewarisi Pengguna, override method abstrak sesuai peran "manajer" ->
 * berwenang melihat laporan pendapatan & statistik, tanpa hak kelola
 * data operasional (itu wewenang Admin).
 */
class Manajer extends Pengguna
{
    public function getDashboardUrl(): string
    {
        return 'manajer/index.php';
    }

    public function getLabelPeran(): string
    {
        return 'Manajer Bioskop';
    }

    public function getDiskonJabatanPersen(): int
    {
        return $this->diskonDariPengaturan('diskon_manajer', 20);
    }
}
