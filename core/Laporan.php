<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * CLASS Laporan
 * ---------------------------------------------------------------------
 * Kumpulan method statistik yang dipakai aktor Manajer & Admin:
 * total pendapatan, film terlaris, dan tingkat occupancy kursi.
 * Seluruh method bersifat static karena tidak butuh state internal -
 * murni fungsi agregasi terhadap data di database.
 */
class Laporan
{
    public static function totalPendapatan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query(
            "SELECT COALESCE(SUM(total_harga),0) AS total FROM tiket WHERE status IN ('lunas','terpakai')",
        );
        return (int) $stmt->fetch()['total'];
    }

    public static function totalTiketTerjual(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query("SELECT COUNT(*) AS jml FROM tiket WHERE status IN ('lunas','terpakai')");
        return (int) $stmt->fetch()['jml'];
    }

    /** @return array<int, array{judul:string, jumlah_terjual:int, pendapatan:int}> */
    public static function filmTerlaris(int $limit = 5): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare(
            "SELECT f.judul,
                    COUNT(t.id) AS jumlah_terjual,
                    COALESCE(SUM(t.total_harga),0) AS pendapatan
             FROM tiket t
             JOIN jadwal j ON j.id = t.jadwal_id
             JOIN film f ON f.id = j.film_id
             WHERE t.status IN ('lunas','terpakai')
             GROUP BY f.id
             ORDER BY jumlah_terjual DESC
             LIMIT ?",
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /** Tingkat okupansi kursi keseluruhan dalam persen. */
    public static function tingkatOccupancy(): float
    {
        $pdo = Database::getInstance()->getKoneksi();

        $totalKursiTerjual = (int) $pdo
            ->query(
                "SELECT COALESCE(SUM(LENGTH(kursi_ids) - LENGTH(REPLACE(kursi_ids,',','')) + 1),0) AS jml
             FROM tiket WHERE status IN ('lunas','terpakai') AND kursi_ids != ''",
            )
            ->fetch()['jml'];

        $totalSesiJadwal = (int) $pdo->query('SELECT COUNT(*) AS jml FROM jadwal')->fetch()['jml'];
        $rataKapasitas = (int) $pdo
            ->query('SELECT COALESCE(AVG(jumlah_baris*jumlah_kolom),0) AS r FROM studio')
            ->fetch()['r'];

        $totalKapasitas = max(1, $totalSesiJadwal * $rataKapasitas);
        return round(($totalKursiTerjual / $totalKapasitas) * 100, 1);
    }

    /** @return array<int, array{tanggal:string, pendapatan:int}> pendapatan 7 hari terakhir untuk grafik */
    public static function pendapatanMingguan(): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query(
            "SELECT DATE(dibuat_pada) AS tanggal, COALESCE(SUM(total_harga),0) AS pendapatan
             FROM tiket
             WHERE status IN ('lunas','terpakai') AND dibuat_pada >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(dibuat_pada)
             ORDER BY tanggal ASC",
        );
        return $stmt->fetchAll();
    }
}
