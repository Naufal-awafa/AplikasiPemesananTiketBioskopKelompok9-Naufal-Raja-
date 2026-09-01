<?php

/**
 * =====================================================================
 * ABSTRACT CLASS LayananPembayaran
 * ---------------------------------------------------------------------
 * PARENT CLASS kedua pada sistem ini, mendemonstrasikan PEWARISAN yang
 * berbeda konteks dari Pengguna: di sini yang diwariskan adalah
 * PERILAKU PEMROSESAN PEMBAYARAN. Setiap metode bayar (transfer bank,
 * e-wallet, kartu kredit, tunai di kasir) adalah CHILD CLASS yang
 * meng-override method proses() sesuai cara kerjanya masing-masing,
 * namun tetap berbagi struktur & method umum dari sini.
 *
 * >> ENKAPSULASI:
 *    - private   $kodeTransaksi -> dibuat otomatis oleh sistem,
 *                                   tidak boleh diubah dari luar.
 *    - protected $jumlah, $status -> hanya boleh diakses class ini dan
 *                                   turunannya.
 * =====================================================================
 */
abstract class LayananPembayaran
{
    private string $kodeTransaksi;
    protected int $jumlah;
    protected string $status; // menunggu | sukses | gagal

    public function __construct(int $jumlah)
    {
        $this->jumlah = $jumlah;
        $this->status = 'menunggu';
        $this->kodeTransaksi = $this->generateKodeTransaksi();
    }

    /**
     * Method private -> logika internal pembuatan kode transaksi unik,
     * disembunyikan sepenuhnya dari luar class (murni detail implementasi).
     */
    private function generateKodeTransaksi(): string
    {
        return 'PAY-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function getKodeTransaksi(): string
    {
        return $this->kodeTransaksi;
    }

    public function getJumlah(): int
    {
        return $this->jumlah;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Method abstrak -> WAJIB di-override tiap child class karena cara
     * "memproses" transfer bank, e-wallet, kartu kredit, dan tunai
     * jelas berbeda (inilah inti polimorfisme lewat pewarisan).
     */
    abstract public function proses(): bool;

    /** Label metode untuk ditampilkan di UI, juga wajib di-override. */
    abstract public function getLabelMetode(): string;

    /**
     * Method umum yang dipakai bersama oleh SEMUA child class (tidak
     * perlu ditulis ulang) -> mengonfirmasi status akhir transaksi.
     */
    public function konfirmasi(bool $berhasil): void
    {
        $this->status = $berhasil ? 'sukses' : 'gagal';
    }
}
