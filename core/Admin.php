<?php
require_once __DIR__ . '/Pengguna.php';

/**
 * CLASS Admin extends Pengguna
 * Mewarisi Pengguna dan meng-override method abstrak sesuai peran
 * "admin" -> mengelola film, jadwal, studio, dan promo.
 */
class Admin extends Pengguna
{
    public function getDashboardUrl(): string
    {
        return 'admin/index.php';
    }

    public function getLabelPeran(): string
    {
        return 'Administrator';
    }

    public function getDiskonJabatanPersen(): int
    {
        return $this->diskonDariPengaturan('diskon_admin', 15);
    }

    /**
     * Method khusus Admin: memvalidasi apakah field wajib form film
     * sudah lengkap sebelum disimpan ke database.
     */
    public function validasiDataFilm(array $data): bool
    {
        return !empty($data['judul']) && !empty($data['genre']) && (int) ($data['harga_dasar'] ?? 0) > 0;
    }

    /**
     * Method khusus Admin: memvalidasi data akun staff baru (kasir/manajer/
     * admin lain) sebelum dibuatkan akunnya oleh Admin.
     */
    public function validasiDataStaff(array $data): bool
    {
        $roleValid = in_array($data['role'] ?? '', ['admin', 'kasir', 'manajer'], true);
        return !empty($data['nama']) && !empty($data['email']) && strlen($data['password'] ?? '') >= 6 && $roleValid;
    }
}
