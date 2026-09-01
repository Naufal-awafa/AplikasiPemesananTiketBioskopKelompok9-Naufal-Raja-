<?php
require_once __DIR__ . '/Pengguna.php';

/**
 * =====================================================================
 * CLASS Customer extends Pengguna
 * ---------------------------------------------------------------------
 * >> PILAR OOP #4 - PEWARISAN
 *    Customer MEWARISI seluruh properti & method dari Pengguna
 *    (protected $id, $nama, $email, method getNama(), dst) tanpa perlu
 *    menuliskannya ulang, lalu MENG-OVERRIDE method abstrak agar
 *    perilakunya spesifik untuk peran "customer".
 * =====================================================================
 */
class Customer extends Pengguna
{
    /**
     * Properti tambahan khusus Customer (protected -> bisa dipakai
     * langsung tanpa getter oleh class ini sendiri, tapi tetap
     * tersembunyi dari kode luar).
     */
    protected array $riwayatTiket = [];

    // Override method abstrak dari parent class Pengguna
    public function getDashboardUrl(): string
    {
        return 'index.php';
    }

    public function getLabelPeran(): string
    {
        return 'Customer';
    }

    /**
     * Method khusus milik Customer (tidak ada di parent class) untuk
     * memvalidasi apakah customer boleh melanjutkan proses booking,
     * mendemonstrasikan bahwa child class BOLEH punya method sendiri
     * di luar apa yang diwariskan induknya.
     */
    // Batas pemesanan umum diwarisi dari Pengguna (maksimal 6 kursi).
}
