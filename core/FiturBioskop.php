<?php
require_once __DIR__ . '/../config/Database.php';

/** Layanan fitur lintas modul: pengaturan, audit, reservasi, loyalitas, dan notifikasi. */
class FiturBioskop
{
    public static function pengaturan(string $kunci, string $default = ''): string
    {
        $stmt = Database::getInstance()->getKoneksi()->prepare('SELECT nilai FROM pengaturan_sistem WHERE kunci = ?');
        $stmt->execute([$kunci]);
        $nilai = $stmt->fetchColumn();
        return $nilai === false ? $default : (string) $nilai;
    }

    public static function simpanPengaturan(string $kunci, string $nilai, string $keterangan = ''): void
    {
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare(
                'INSERT INTO pengaturan_sistem (kunci,nilai,keterangan) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai), keterangan=VALUES(keterangan)',
            );
        $stmt->execute([$kunci, $nilai, $keterangan]);
    }

    public static function audit(
        ?int $penggunaId,
        string $aksi,
        string $entitas = '',
        ?int $entitasId = null,
        string $detail = '',
    ): void {
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare(
                'INSERT INTO audit_log (pengguna_id,aksi,entitas,entitas_id,detail,ip_address) VALUES (?,?,?,?,?,?)',
            );
        $stmt->execute([$penggunaId, $aksi, $entitas, $entitasId, $detail, $_SERVER['REMOTE_ADDR'] ?? 'CLI']);
    }

    public static function notifikasi(int $penggunaId, string $judul, string $pesan, string $tautan = ''): void
    {
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare('INSERT INTO notifikasi (pengguna_id,judul,pesan,tautan,channel) VALUES (?,?,?,?,?)');
        $stmt->execute([$penggunaId, $judul, $pesan, $tautan, 'aplikasi,email,whatsapp (simulasi)']);
    }

    public static function notifikasiBelumDibaca(int $penggunaId): int
    {
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare('SELECT COUNT(*) FROM notifikasi WHERE pengguna_id=? AND dibaca=0');
        $stmt->execute([$penggunaId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Memastikan setiap kasir memiliki tepat satu shift operasional per hari.
     * Shift lama yang masih terbuka ditutup otomatis pada akhir tanggalnya.
     */
    public static function pastikanShiftHarian(int $kasirId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmtLama = $pdo->prepare(
            'SELECT * FROM shift_kasir WHERE kasir_id=? AND selesai IS NULL AND DATE(mulai)<CURDATE() ORDER BY mulai',
        );
        $stmtLama->execute([$kasirId]);
        $totalTunai = $pdo->prepare(
            "SELECT COALESCE(SUM(total_harga),0) FROM tiket WHERE kasir_id=? AND status IN ('lunas','terpakai') AND metode_bayar LIKE 'Tunai%' AND dibuat_pada>=DATE(?) AND dibuat_pada<DATE_ADD(DATE(?),INTERVAL 1 DAY)",
        );
        $tutup = $pdo->prepare(
            "UPDATE shift_kasir SET selesai=DATE_ADD(DATE(mulai),INTERVAL 1 DAY)-INTERVAL 1 SECOND,saldo_akhir=?,catatan=COALESCE(NULLIF(catatan,''),'Ditutup otomatis oleh sistem') WHERE id=?",
        );
        foreach ($stmtLama->fetchAll() as $shift) {
            $totalTunai->execute([$kasirId, $shift['mulai'], $shift['mulai']]);
            $saldoAkhir = (int) $shift['saldo_awal'] + (int) $totalTunai->fetchColumn();
            $tutup->execute([$saldoAkhir, (int) $shift['id']]);
        }

        $stmtHariIni = $pdo->prepare(
            'SELECT * FROM shift_kasir WHERE kasir_id=? AND DATE(mulai)=CURDATE() ORDER BY id LIMIT 1',
        );
        $stmtHariIni->execute([$kasirId]);
        $shift = $stmtHariIni->fetch();
        if (!$shift) {
            $pdo->prepare(
                "INSERT INTO shift_kasir (kasir_id,mulai,saldo_awal,catatan) VALUES (?,CONCAT(CURDATE(),' 00:00:00'),0,'Shift harian otomatis')",
            )->execute([$kasirId]);
            $id = (int) $pdo->lastInsertId();
            self::audit($kasirId, 'buat_otomatis', 'shift_kasir', $id, date('Y-m-d'));
            $stmtHariIni->execute([$kasirId]);
            $shift = $stmtHariIni->fetch();
        } elseif ($shift['selesai'] !== null) {
            $pdo->prepare('UPDATE shift_kasir SET selesai=NULL,saldo_akhir=NULL WHERE id=?')->execute([
                (int) $shift['id'],
            ]);
            $stmtHariIni->execute([$kasirId]);
            $shift = $stmtHariIni->fetch();
        }
        return $shift;
    }

    public static function bersihkanReservasi(): void
    {
        Database::getInstance()->getKoneksi()->exec('DELETE FROM reservasi_kursi WHERE kedaluwarsa_pada <= NOW()');
    }

    /** @return int[] */
    public static function kursiDireservasi(int $jadwalId, ?int $kecualiPengguna = null): array
    {
        self::bersihkanReservasi();
        $sql = 'SELECT kursi_id FROM reservasi_kursi WHERE jadwal_id=? AND kedaluwarsa_pada > NOW()';
        $params = [$jadwalId];
        if ($kecualiPengguna !== null) {
            $sql .= ' AND pengguna_id != ?';
            $params[] = $kecualiPengguna;
        }
        $stmt = Database::getInstance()->getKoneksi()->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', array_column($stmt->fetchAll(), 'kursi_id'));
    }

    public static function buatReservasi(int $penggunaId, int $jadwalId, array $kursiIds): ?string
    {
        self::bersihkanReservasi();
        $pdo = Database::getInstance()->getKoneksi();
        $token = bin2hex(random_bytes(16));
        $menit = max(2, (int) self::pengaturan('durasi_reservasi', '10'));
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM reservasi_kursi WHERE pengguna_id=? AND jadwal_id=?')->execute([
                $penggunaId,
                $jadwalId,
            ]);
            $stmt = $pdo->prepare(
                'INSERT INTO reservasi_kursi (pengguna_id,jadwal_id,kursi_id,token,kedaluwarsa_pada) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            );
            foreach (array_unique(array_map('intval', $kursiIds)) as $kursiId) {
                $stmt->execute([$penggunaId, $jadwalId, $kursiId, $token, $menit]);
            }
            $pdo->commit();
            return $token;
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return null;
        }
    }

    public static function reservasiValid(string $token, int $penggunaId, int $jadwalId, array $kursiIds): bool
    {
        self::bersihkanReservasi();
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare(
                'SELECT kursi_id FROM reservasi_kursi WHERE token=? AND pengguna_id=? AND jadwal_id=? AND kedaluwarsa_pada > NOW()',
            );
        $stmt->execute([$token, $penggunaId, $jadwalId]);
        $tersimpan = array_map('intval', array_column($stmt->fetchAll(), 'kursi_id'));
        sort($tersimpan);
        $diminta = array_values(array_unique(array_map('intval', $kursiIds)));
        sort($diminta);
        return $tersimpan === $diminta && $tersimpan !== [];
    }

    public static function hapusReservasi(string $token): void
    {
        Database::getInstance()
            ->getKoneksi()
            ->prepare('DELETE FROM reservasi_kursi WHERE token=?')
            ->execute([$token]);
    }

    public static function tambahPoin(int $penggunaId, int $tiketId, int $total): int
    {
        $perolehan = intdiv(max(0, $total), 10000) * max(1, (int) self::pengaturan('poin_per_10000', '1'));
        if ($perolehan <= 0) {
            return 0;
        }
        $pdo = Database::getInstance()->getKoneksi();
        $pdo->prepare('UPDATE pengguna SET poin=poin+? WHERE id=?')->execute([$perolehan, $penggunaId]);
        $pdo->prepare(
            "INSERT INTO poin_transaksi (pengguna_id,tiket_id,tipe,jumlah,keterangan) VALUES (?,?,'masuk',?,'Poin pembelian tiket')",
        )->execute([$penggunaId, $tiketId, $perolehan]);
        return $perolehan;
    }

    public static function gunakanPoin(int $penggunaId, int $tiketId, int $poin): void
    {
        if ($poin <= 0) {
            return;
        }
        $pdo = Database::getInstance()->getKoneksi();
        $pdo->prepare('UPDATE pengguna SET poin=GREATEST(0,poin-?) WHERE id=?')->execute([$poin, $penggunaId]);
        $pdo->prepare(
            "INSERT INTO poin_transaksi (pengguna_id,tiket_id,tipe,jumlah,keterangan) VALUES (?,?,'keluar',?,'Penukaran poin tiket')",
        )->execute([$penggunaId, $tiketId, -$poin]);
    }

    public static function jadwalMasihBisaDipesan(string $tanggal, string $jam): bool
    {
        return strtotime($tanggal . ' ' . $jam) > time();
    }

    public static function bolehMengulas(int $penggunaId, int $filmId): bool
    {
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare(
                "SELECT COUNT(*) FROM tiket t JOIN jadwal j ON j.id=t.jadwal_id WHERE t.customer_id=? AND j.film_id=? AND t.status='terpakai'",
            );
        $stmt->execute([$penggunaId, $filmId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
