<?php
require_once __DIR__ . '/LayananPembayaran.php';

/**
 * CLASS PembayaranKartuKredit extends LayananPembayaran
 * >> ENKAPSULASI tambahan: nomor kartu disimpan private dan hanya
 *    diekspos dalam bentuk masking (disamarkan) demi keamanan.
 */
class PembayaranKartuKredit extends LayananPembayaran
{
    private string $nomorKartu; // sensitif, hanya boleh keluar dalam bentuk masked

    public function __construct(int $jumlah, string $nomorKartu)
    {
        parent::__construct($jumlah);
        $this->nomorKartu = $nomorKartu;
    }

    /** Mengembalikan nomor kartu dalam bentuk tersamar, misal **** **** **** 1234 */
    public function getNomorKartuMasked(): string
    {
        $bersih = preg_replace('/\D/', '', $this->nomorKartu);
        $empat = substr($bersih, -4);
        return '**** **** **** ' . $empat;
    }

    public function proses(): bool
    {
        $this->konfirmasi(true);
        return true;
    }

    public function getLabelMetode(): string
    {
        return 'Kartu Kredit/Debit';
    }
}
