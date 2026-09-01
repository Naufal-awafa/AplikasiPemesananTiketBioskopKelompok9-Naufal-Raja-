<?php
/** Koneksi tunggal PDO MySQL/MariaDB untuk XAMPP. */
class Database
{
    private static ?Database $instance = null;
    private PDO $koneksi;

    private function __construct()
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db = getenv('DB_NAME') ?: 'pemesanantiket2';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
            throw new RuntimeException('Nama database MySQL tidak valid.');
        }
        $opsi = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $opsi);
        $server->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->koneksi = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, $opsi);
        if (!$this->tabelAda('pengguna')) {
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            if ($sql === false) {
                throw new RuntimeException('database/schema.sql tidak dapat dibaca.');
            }
            $this->koneksi->exec($sql);
        }
        $this->migrasiSkema();
    }

    private function tabelAda(string $tabel): bool
    {
        $q = $this->koneksi->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
        );
        $q->execute([$tabel]);
        return (int) $q->fetchColumn() > 0;
    }

    private function kolomAda(string $tabel, string $kolom): bool
    {
        $q = $this->koneksi->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
        );
        $q->execute([$tabel, $kolom]);
        return (int) $q->fetchColumn() > 0;
    }

    private function tambahKolom(string $tabel, string $kolom, string $definisi): void
    {
        if (!$this->kolomAda($tabel, $kolom)) {
            $this->koneksi->exec("ALTER TABLE `{$tabel}` ADD COLUMN `{$kolom}` {$definisi}");
        }
    }

    /** Melengkapi database MySQL versi lama tanpa menghapus data. */
    private function migrasiSkema(): void
    {
        $this->buatTabelFitur();
        foreach (
            [
                'email_terverifikasi' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'poin' => 'INT NOT NULL DEFAULT 0',
                'cabang_id' => 'INT DEFAULT 1',
                'hak_akses' => "VARCHAR(255) DEFAULT ''",
            ]
            as $k => $d
        ) {
            $this->tambahKolom('pengguna', $k, $d);
        }
        foreach (
            [
                'tmdb_id' => 'INT DEFAULT NULL',
                'poster_url' => 'TEXT DEFAULT NULL',
                'trailer_key' => "VARCHAR(100) DEFAULT ''",
                'bahasa' => "VARCHAR(60) DEFAULT 'Indonesia'",
                'rating_usia' => "VARCHAR(20) DEFAULT 'SU'",
            ]
            as $k => $d
        ) {
            $this->tambahKolom('film', $k, $d);
        }
        $this->tambahKolom('studio', 'cabang_id', 'INT DEFAULT 1');
        $this->tambahKolom('jadwal', 'selesai_pada', "VARCHAR(30) DEFAULT ''");
        $this->tambahKolom('ulasan', 'terverifikasi', 'TINYINT(1) NOT NULL DEFAULT 0');
        foreach (
            [
                'role_pemesan' => "VARCHAR(20) DEFAULT ''",
                'subtotal_harga' => 'INT NOT NULL DEFAULT 0',
                'diskon_jabatan_persen' => 'INT NOT NULL DEFAULT 0',
                'potongan_jabatan' => 'INT NOT NULL DEFAULT 0',
                'kode_promo' => "VARCHAR(100) DEFAULT ''",
                'potongan_promo' => 'INT NOT NULL DEFAULT 0',
                'poin_digunakan' => 'INT NOT NULL DEFAULT 0',
                'potongan_poin' => 'INT NOT NULL DEFAULT 0',
                'total_produk' => 'INT NOT NULL DEFAULT 0',
                'refund_status' => "VARCHAR(30) DEFAULT ''",
            ]
            as $k => $d
        ) {
            $this->tambahKolom('tiket', $k, $d);
        }
        $this->tambahKolom('produk', 'gambar', "VARCHAR(255) DEFAULT ''");
        $this->tambahKolom('pesanan_produk', 'ukuran', "VARCHAR(20) NOT NULL DEFAULT 'regular'");
        $this->isiDataFitur();
    }

    private function buatTabelFitur(): void
    {
        $this->koneksi->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS cabang (id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(150) NOT NULL,kota VARCHAR(100) NOT NULL,alamat TEXT,fasilitas TEXT,aktif TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS pengaturan_sistem (kunci VARCHAR(100) PRIMARY KEY,nilai TEXT NOT NULL,keterangan TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS audit_log (id INT AUTO_INCREMENT PRIMARY KEY,pengguna_id INT,aksi VARCHAR(100) NOT NULL,entitas VARCHAR(100) DEFAULT '',entitas_id INT,detail TEXT,ip_address VARCHAR(45) DEFAULT '',dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS login_attempt (id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL,ip_address VARCHAR(45) NOT NULL,berhasil TINYINT(1) NOT NULL DEFAULT 0,dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP,INDEX idx_login_attempt(email,ip_address,dibuat_pada)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS verifikasi_email (pengguna_id INT PRIMARY KEY,kode VARCHAR(20) NOT NULL,kedaluwarsa_pada DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS reset_password (id INT AUTO_INCREMENT PRIMARY KEY,pengguna_id INT NOT NULL,token VARCHAR(190) NOT NULL UNIQUE,kedaluwarsa_pada DATETIME NOT NULL,digunakan TINYINT(1) NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS reservasi_kursi (id INT AUTO_INCREMENT PRIMARY KEY,pengguna_id INT NOT NULL,jadwal_id INT NOT NULL,kursi_id INT NOT NULL,token VARCHAR(190) NOT NULL,kedaluwarsa_pada DATETIME NOT NULL,UNIQUE KEY ux_reservasi(jadwal_id,kursi_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS detail_tiket (id INT AUTO_INCREMENT PRIMARY KEY,tiket_id INT NOT NULL,jadwal_id INT,kursi_id INT NOT NULL,harga INT NOT NULL,UNIQUE KEY ux_detail(tiket_id,kursi_id),UNIQUE KEY ux_detail_jadwal(jadwal_id,kursi_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS refund (id INT AUTO_INCREMENT PRIMARY KEY,tiket_id INT NOT NULL UNIQUE,pengguna_id INT NOT NULL,jumlah INT NOT NULL,persentase INT NOT NULL,alasan TEXT,status VARCHAR(30) NOT NULL DEFAULT 'diproses',dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS favorit (pengguna_id INT NOT NULL,film_id INT NOT NULL,dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(pengguna_id,film_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS notifikasi (id INT AUTO_INCREMENT PRIMARY KEY,pengguna_id INT NOT NULL,judul VARCHAR(190) NOT NULL,pesan TEXT NOT NULL,tautan VARCHAR(255) DEFAULT '',channel VARCHAR(100) DEFAULT 'aplikasi,email,whatsapp',dibaca TINYINT(1) NOT NULL DEFAULT 0,dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS poin_transaksi (id INT AUTO_INCREMENT PRIMARY KEY,pengguna_id INT NOT NULL,tiket_id INT,tipe VARCHAR(30) NOT NULL,jumlah INT NOT NULL,keterangan TEXT,dibuat_pada DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS produk (id INT AUTO_INCREMENT PRIMARY KEY,nama VARCHAR(150) NOT NULL,kategori VARCHAR(50) NOT NULL DEFAULT 'makanan',harga INT NOT NULL,stok INT NOT NULL DEFAULT 0,aktif TINYINT(1) NOT NULL DEFAULT 1,gambar VARCHAR(255) DEFAULT '') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS pesanan_produk (id INT AUTO_INCREMENT PRIMARY KEY,tiket_id INT NOT NULL,produk_id INT NOT NULL,jumlah INT NOT NULL,harga_satuan INT NOT NULL,ukuran VARCHAR(20) NOT NULL DEFAULT 'regular') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            CREATE TABLE IF NOT EXISTS shift_kasir (id INT AUTO_INCREMENT PRIMARY KEY,kasir_id INT NOT NULL,mulai DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,selesai DATETIME,saldo_awal INT NOT NULL DEFAULT 0,saldo_akhir INT,catatan TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            SQL
            ,
        );
    }

    private function isiDataFitur(): void
    {
        $this->koneksi->exec(
            "INSERT IGNORE INTO cabang(id,nama,kota,alamat,fasilitas) VALUES(1,'Sineverse Central','Jakarta','Jl. Sinema No. 1','IMAX, 3D, Lounge, Parkir')",
        );
        $data = [
            ['diskon_kasir', '10', 'Diskon jabatan kasir (%)'],
            ['diskon_admin', '15', 'Diskon jabatan admin (%)'],
            ['diskon_manajer', '20', 'Diskon jabatan manajer (%)'],
            ['durasi_reservasi', '10', 'Durasi penahanan kursi (menit)'],
            ['batas_diskon_staff_bulanan', '8', 'Maksimal transaksi diskon staff per bulan'],
            ['nilai_satu_poin', '100', 'Nilai rupiah satu poin'],
            ['poin_per_10000', '1', 'Poin yang diperoleh per Rp10.000'],
        ];
        $q = $this->koneksi->prepare('INSERT IGNORE INTO pengaturan_sistem(kunci,nilai,keterangan) VALUES(?,?,?)');
        foreach ($data as $d) {
            $q->execute($d);
        }
        $produk = [
            ['Popcorn Caramel', 'makanan', 28000, 50, 'assets/img/produk/popcorn-caramel.png'],
            ['Popcorn Salted', 'makanan', 24000, 50, 'assets/img/produk/popcorn-salted.png'],
            ['Cola Regular', 'minuman', 18000, 80, 'assets/img/produk/cola-regular.png'],
            ['Mineral Water', 'minuman', 12000, 100, 'assets/img/produk/mineral-water.png'],
            ['Movie Combo', 'combo', 42000, 40, 'assets/img/produk/movie-combo.png'],
        ];
        $q = $this->koneksi->prepare(
            'INSERT INTO produk(nama,kategori,harga,stok,gambar) SELECT ?,?,?,?,? FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM produk WHERE nama=?)',
        );
        $qGambar = $this->koneksi->prepare("UPDATE produk SET gambar=? WHERE nama=? AND (gambar IS NULL OR gambar='')");
        foreach ($produk as $d) {
            $q->execute([...$d, $d[0]]);
            $qGambar->execute([$d[4], $d[0]]);
        }

        if ((int) $this->koneksi->query('SELECT COUNT(*) FROM pengguna')->fetchColumn() === 0) {
            $hash = '$2b$10$kaHmUU82RugC1pIdFFrTQ.ZXbWKMA.2qzIb.hg5h1iuftLkL11N5q';
            $q = $this->koneksi->prepare('INSERT INTO pengguna(nama,email,password,no_hp,role) VALUES(?,?,?,?,?)');
            foreach (
                [
                    ['Admin Sineverse', 'admin@sineverse.id', $hash, '0811000001', 'admin'],
                    ['Kasir Nadia', 'kasir@sineverse.id', $hash, '0811000002', 'kasir'],
                    ['Manajer Bimo', 'manajer@sineverse.id', $hash, '0811000003', 'manajer'],
                    ['Rangga Customer', 'customer@sineverse.id', $hash, '0811000004', 'customer'],
                ]
                as $d
            ) {
                $q->execute($d);
            }
        }
        if ((int) $this->koneksi->query('SELECT COUNT(*) FROM studio')->fetchColumn() === 0) {
            $this->koneksi->exec(
                "INSERT INTO studio(nama,tipe,jumlah_baris,jumlah_kolom) VALUES('Studio 1','2D',6,10),('Studio 2','3D',6,10),('Studio 3 IMAX','IMAX',8,12)",
            );
        }
        if ((int) $this->koneksi->query('SELECT COUNT(*) FROM film')->fetchColumn() === 0) {
            $this->koneksi
                ->exec("INSERT INTO film(judul,genre,durasi,sinopsis,poster_warna,rating,status,harga_dasar) VALUES
            ('Nebula Terakhir','Sci-Fi / Petualangan',128,'Sekelompok penjelajah menemukan sinyal misterius dari galaksi yang telah lama dianggap punah.','#7c5cff',4.8,'tayang',45000),
            ('Bayangan Kota','Thriller / Kriminal',112,'Seorang detektif muda mengungkap konspirasi gelap yang mengancam seluruh kota.','#ff5c8a',4.5,'tayang',40000),
            ('Rumah di Ujung Kabut','Horor',98,'Sebuah keluarga pindah ke rumah tua yang menyimpan rahasia kelam.','#22d3ee',4.2,'tayang',38000),
            ('Melodi Senja','Drama / Romansa',105,'Kisah cinta dua musisi jalanan yang dipertemukan oleh nada yang sama.','#ffb020',4.6,'tayang',35000)");
        }
        if ((int) $this->koneksi->query('SELECT COUNT(*) FROM kursi')->fetchColumn() === 0) {
            $q = $this->koneksi->prepare('INSERT INTO kursi(studio_id,baris,nomor,tipe) VALUES(?,?,?,?)');
            foreach ($this->koneksi->query('SELECT id,jumlah_baris,jumlah_kolom FROM studio') as $s) {
                for ($b = 1; $b <= (int) $s['jumlah_baris']; $b++) {
                    for ($n = 1; $n <= (int) $s['jumlah_kolom']; $n++) {
                        $q->execute([
                            (int) $s['id'],
                            chr(64 + $b),
                            $n,
                            $b > (int) $s['jumlah_baris'] - 2 ? 'vip' : 'reguler',
                        ]);
                    }
                }
            }
        }
        if ((int) $this->koneksi->query('SELECT COUNT(*) FROM promo')->fetchColumn() === 0) {
            $this->koneksi->exec(
                "INSERT INTO promo(kode,deskripsi,diskon_persen,berlaku_hingga,aktif) VALUES('SINE10','Diskon 10% untuk semua tiket',10,DATE_ADD(CURDATE(),INTERVAL 30 DAY),1),('WEEKDAY20','Diskon 20% khusus penayangan hari kerja',20,DATE_ADD(CURDATE(),INTERVAL 30 DAY),1)",
            );
        }
        if (
            (int) $this->koneksi
                ->query('SELECT COUNT(*) FROM jadwal WHERE TIMESTAMP(tanggal,jam)>NOW()')
                ->fetchColumn() === 0
        ) {
            $this->koneksi->exec(
                "INSERT INTO jadwal(film_id,studio_id,tanggal,jam) VALUES(1,1,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'13:00'),(2,2,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'16:00'),(3,3,DATE_ADD(CURDATE(),INTERVAL 2 DAY),'19:00'),(4,1,DATE_ADD(CURDATE(),INTERVAL 3 DAY),'15:30')",
            );
        }
    }

    public function exportSql(): string
    {
        $out = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($this->koneksi->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $a = str_replace('`', '``', $t);
            $c = $this->koneksi->query("SHOW CREATE TABLE `{$a}`")->fetch(PDO::FETCH_NUM);
            $out .= "DROP TABLE IF EXISTS `{$a}`;\n{$c[1]};\n";
            foreach ($this->koneksi->query("SELECT * FROM `{$a}`") as $r) {
                $v = array_map(fn($x) => $x === null ? 'NULL' : $this->koneksi->quote((string) $x), array_values($r));
                $out .= "INSERT INTO `{$a}` VALUES(" . implode(',', $v) . ");\n";
            }
            $out .= "\n";
        }
        return $out . "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    public static function getInstance(): Database
    {
        return self::$instance ??= new self();
    }
    public function getKoneksi(): PDO
    {
        return $this->koneksi;
    }
}
