<?php
require_once __DIR__ . '/Jadwal.php';
require_once __DIR__ . '/Studio.php';
require_once __DIR__ . '/Promo.php';
require_once __DIR__ . '/Pengguna.php';

/**
 * Satu sumber perhitungan harga untuk halaman checkout dan proses bayar.
 * Dengan begitu total dari browser tidak pernah dipercaya oleh server.
 */
class RincianHarga
{
    /** Daftar ukuran dan selisih harga dari harga Regular. */
    public static function pilihanUkuran(string $kategori): array
    {
        return match ($kategori) {
            'makanan' => [
                'small' => ['label' => 'Small', 'tambahan' => -4000],
                'regular' => ['label' => 'Regular', 'tambahan' => 0],
                'large' => ['label' => 'Large', 'tambahan' => 6000],
            ],
            'minuman' => [
                'small' => ['label' => 'Small', 'tambahan' => -3000],
                'regular' => ['label' => 'Regular', 'tambahan' => 0],
                'large' => ['label' => 'Large', 'tambahan' => 5000],
            ],
            'combo' => [
                'regular' => ['label' => 'Regular', 'tambahan' => 0],
                'large' => ['label' => 'Large', 'tambahan' => 10000],
            ],
            default => ['regular' => ['label' => 'Regular', 'tambahan' => 0]],
        };
    }

    public static function hargaUkuran(int $hargaDasar, string $kategori, string $ukuran): int
    {
        $pilihan = self::pilihanUkuran($kategori);
        $ukuran = isset($pilihan[$ukuran]) ? $ukuran : 'regular';
        return max(1000, $hargaDasar + (int) $pilihan[$ukuran]['tambahan']);
    }

    /**
     * @return array{subtotal:int,diskon_jabatan_persen:int,potongan_jabatan:int,promo:?Promo,potongan_promo:int,total_akhir:int,kursi_ids:int[],label_kursi:string[]}|null
     */
    public static function hitung(
        Jadwal $jadwal,
        array $kursiIds,
        Pengguna $user,
        string $kodePromo = '',
        array $produkQty = [],
        int $poinDiminta = 0,
        array $produkUkuran = [],
    ): ?array {
        $kursiIds = array_values(array_unique(array_filter(array_map('intval', $kursiIds), fn(int $id) => $id > 0)));
        if (!$user->bolehMemesan(count($kursiIds))) {
            return null;
        }

        $studio = $jadwal->getStudio();
        $film = $jadwal->getFilm();
        if (!$studio || !$film) {
            return null;
        }

        $petaKursi = [];
        foreach (Kursi::untukStudio($studio->getId()) as $kursi) {
            $petaKursi[$kursi->getId()] = $kursi;
        }

        // Tolak seluruh permintaan jika ada ID kursi palsu/dari studio lain.
        foreach ($kursiIds as $kursiId) {
            if (!isset($petaKursi[$kursiId])) {
                return null;
            }
        }

        $hargaReguler = $film->hitungHargaTiket($studio->getTipe());
        $hargaVip = (int) round($hargaReguler * 1.35, -3);
        $subtotal = 0;
        $labelKursi = [];
        foreach ($kursiIds as $kursiId) {
            $kursi = $petaKursi[$kursiId];
            $subtotal += $kursi->getTipe() === 'vip' ? $hargaVip : $hargaReguler;
            $labelKursi[] = $kursi->getLabel();
        }

        $diskonJabatanPersen = $user->getDiskonJabatanPersen();
        $potonganJabatan = (int) round(($subtotal * $diskonJabatanPersen) / 100);
        $setelahDiskonJabatan = max(0, $subtotal - $potonganJabatan);

        $promo = $kodePromo !== '' ? Promo::cariByKode(trim($kodePromo)) : null;
        $potonganPromo = $promo ? $promo->hitungPotongan($setelahDiskonJabatan) : 0;

        $produkDipilih = [];
        $totalProduk = 0;
        if ($produkQty) {
            $stmtProduk = Database::getInstance()->getKoneksi()->prepare('SELECT * FROM produk WHERE id=? AND aktif=1');
            foreach ($produkQty as $produkId => $jumlahMentah) {
                $jumlah = max(0, min(10, (int) $jumlahMentah));
                if ($jumlah === 0) {
                    continue;
                }
                $stmtProduk->execute([(int) $produkId]);
                $produk = $stmtProduk->fetch();
                if (!$produk || (int) $produk['stok'] < $jumlah) {
                    continue;
                }
                $pilihanUkuran = self::pilihanUkuran((string) $produk['kategori']);
                $ukuran = strtolower(trim((string) ($produkUkuran[$produkId] ?? 'regular')));
                if (!isset($pilihanUkuran[$ukuran])) {
                    $ukuran = 'regular';
                }
                $produk['ukuran'] = $ukuran;
                $produk['label_ukuran'] = $pilihanUkuran[$ukuran]['label'];
                $produk['harga'] = self::hargaUkuran((int) $produk['harga'], (string) $produk['kategori'], $ukuran);
                $produk['jumlah'] = $jumlah;
                $produk['subtotal'] = (int) $produk['harga'] * $jumlah;
                $produkDipilih[] = $produk;
                $totalProduk += $produk['subtotal'];
            }
        }

        $sebelumPoin = max(0, $setelahDiskonJabatan - $potonganPromo) + $totalProduk;
        $nilaiPoin = max(1, (int) FiturBioskop::pengaturan('nilai_satu_poin', '100'));
        $poinDigunakan = min(max(0, $poinDiminta), $user->getPoin(), intdiv($sebelumPoin, $nilaiPoin));
        $potonganPoin = $poinDigunakan * $nilaiPoin;

        return [
            'subtotal' => $subtotal,
            'diskon_jabatan_persen' => $diskonJabatanPersen,
            'potongan_jabatan' => $potonganJabatan,
            'promo' => $promo,
            'potongan_promo' => $potonganPromo,
            'total_produk' => $totalProduk,
            'produk' => $produkDipilih,
            'poin_digunakan' => $poinDigunakan,
            'potongan_poin' => $potonganPoin,
            'total_akhir' => max(0, $sebelumPoin - $potonganPoin),
            'kursi_ids' => $kursiIds,
            'label_kursi' => $labelKursi,
        ];
    }
}
