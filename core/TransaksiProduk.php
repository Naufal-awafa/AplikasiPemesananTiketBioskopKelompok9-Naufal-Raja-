<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/RincianHarga.php';

class TransaksiProduk
{
    public static function buatKode(): string
    {
        return 'SNK-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    }

    public static function daftarAktif(): array
    {
        return Database::getInstance()->getKoneksi()
            ->query('SELECT * FROM produk WHERE aktif=1 AND stok>0 ORDER BY kategori,nama')
            ->fetchAll();
    }

    /** Validasi pilihan produk dari form dan hitung harga hanya dari database. */
    public static function siapkan(array $qty, array $ukuran): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM produk WHERE id=? AND aktif=1');
        $items = [];
        $total = 0;
        foreach ($qty as $produkId => $jumlahMentah) {
            $jumlah = max(0, min(10, (int) $jumlahMentah));
            if ($jumlah === 0) {
                continue;
            }
            $stmt->execute([(int) $produkId]);
            $produk = $stmt->fetch();
            if (!$produk) {
                throw new RuntimeException('Ada produk yang sudah tidak tersedia.');
            }
            if ((int) $produk['stok'] < $jumlah) {
                throw new RuntimeException('Stok ' . $produk['nama'] . ' tidak mencukupi.');
            }
            $opsi = RincianHarga::pilihanUkuran((string) $produk['kategori']);
            $kodeUkuran = strtolower(trim((string) ($ukuran[$produkId] ?? 'regular')));
            if (!isset($opsi[$kodeUkuran])) {
                $kodeUkuran = 'regular';
            }
            $harga = RincianHarga::hargaUkuran((int) $produk['harga'], (string) $produk['kategori'], $kodeUkuran);
            $items[] = [
                'id' => (int) $produk['id'],
                'nama' => (string) $produk['nama'],
                'jumlah' => $jumlah,
                'harga' => $harga,
                'ukuran' => $kodeUkuran,
                'label_ukuran' => $opsi[$kodeUkuran]['label'],
                'subtotal' => $harga * $jumlah,
            ];
            $total += $harga * $jumlah;
        }
        return ['items' => $items, 'total' => $total];
    }

    /** Simpan item gabungan ke tiket dan kurangi stok secara atomik. */
    public static function simpanUntukTiket(int $tiketId, array $items): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $insert = $pdo->prepare('INSERT INTO pesanan_produk(tiket_id,produk_id,jumlah,harga_satuan,ukuran) VALUES(?,?,?,?,?)');
        self::simpanBaris($items, function (array $item) use ($insert, $tiketId): void {
            $insert->execute([$tiketId, $item['id'], $item['jumlah'], $item['harga'], $item['ukuran']]);
        });
    }

    public static function buatPenjualan(int $kasirId, ?int $tiketId, string $nama, array $items, int $total, string $metode): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('INSERT INTO transaksi_produk(kode_transaksi,kasir_id,tiket_id,nama_pelanggan,total_harga,metode_bayar) VALUES(?,?,?,?,?,?)');
        $stmt->execute([self::buatKode(), $kasirId, $tiketId, trim($nama), $total, $metode]);
        $id = (int) $pdo->lastInsertId();
        $insert = $pdo->prepare('INSERT INTO detail_transaksi_produk(transaksi_produk_id,produk_id,jumlah,harga_satuan,ukuran) VALUES(?,?,?,?,?)');
        self::simpanBaris($items, function (array $item) use ($insert, $id): void {
            $insert->execute([$id, $item['id'], $item['jumlah'], $item['harga'], $item['ukuran']]);
        });
        return $id;
    }

    private static function simpanBaris(array $items, callable $insert): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $kurangi = $pdo->prepare('UPDATE produk SET stok=stok-? WHERE id=? AND stok>=?');
        foreach ($items as $item) {
            $insert($item);
            $kurangi->execute([$item['jumlah'], $item['id'], $item['jumlah']]);
            if ($kurangi->rowCount() !== 1) {
                throw new RuntimeException('Stok ' . $item['nama'] . ' berubah. Silakan ulangi transaksi.');
            }
        }
    }

    public static function cari(int $id): ?array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT tp.*,t.kode_tiket FROM transaksi_produk tp LEFT JOIN tiket t ON t.id=tp.tiket_id WHERE tp.id=?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) {
            return null;
        }
        $items = $pdo->prepare('SELECT d.*,p.nama FROM detail_transaksi_produk d JOIN produk p ON p.id=d.produk_id WHERE d.transaksi_produk_id=? ORDER BY d.id');
        $items->execute([$id]);
        $data['items'] = $items->fetchAll();
        return $data;
    }

    public static function untukKasir(int $kasirId): array
    {
        $stmt = Database::getInstance()->getKoneksi()->prepare('SELECT tp.*,t.kode_tiket FROM transaksi_produk tp LEFT JOIN tiket t ON t.id=tp.tiket_id WHERE tp.kasir_id=? ORDER BY tp.id DESC');
        $stmt->execute([$kasirId]);
        return $stmt->fetchAll();
    }
}
