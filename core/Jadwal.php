<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/Film.php';
require_once __DIR__ . '/Studio.php';
require_once __DIR__ . '/FiturBioskop.php';

/**
 * CLASS Jadwal - menghubungkan Film <-> Studio pada tanggal & jam
 * tertentu. Juga bertugas menghitung kursi mana saja yang sudah
 * dipesan untuk sesi tayang tersebut.
 */
class Jadwal
{
    private ?int $id;
    protected int $filmId;
    protected int $studioId;
    protected string $tanggal;
    protected string $jam;

    public function __construct(?int $id, int $filmId, int $studioId, string $tanggal, string $jam)
    {
        $this->id = $id;
        $this->filmId = $filmId;
        $this->studioId = $studioId;
        $this->tanggal = $tanggal;
        $this->jam = $jam;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getFilmId(): int
    {
        return $this->filmId;
    }
    public function getStudioId(): int
    {
        return $this->studioId;
    }
    public function getTanggal(): string
    {
        return $this->tanggal;
    }
    public function getJam(): string
    {
        return $this->jam;
    }
    public function masihBisaDipesan(): bool
    {
        return FiturBioskop::jadwalMasihBisaDipesan($this->tanggal, $this->jam);
    }

    public function getFilm(): ?Film
    {
        return Film::cariById($this->filmId);
    }
    public function getStudio(): ?Studio
    {
        return Studio::cariById($this->studioId);
    }

    private static function dariBaris(array $b): Jadwal
    {
        return new self((int) $b['id'], (int) $b['film_id'], (int) $b['studio_id'], $b['tanggal'], $b['jam']);
    }

    /** @return Jadwal[] */
    public static function untukFilm(int $filmId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM jadwal WHERE film_id = ? ORDER BY tanggal ASC, jam ASC');
        $stmt->execute([$filmId]);
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public static function cariById(int $id): ?Jadwal
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM jadwal WHERE id = ?');
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        return $b ? self::dariBaris($b) : null;
    }

    /** @return Jadwal[] */
    public static function semua(): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query('SELECT * FROM jadwal ORDER BY tanggal ASC, jam ASC');
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('INSERT INTO jadwal (film_id, studio_id, tanggal, jam) VALUES (?,?,?,?)');
        $stmt->execute([$this->filmId, $this->studioId, $this->tanggal, $this->jam]);
        $this->id = (int) $pdo->lastInsertId();
        return $this->id;
    }

    public static function adaBentrok(
        int $studioId,
        string $tanggal,
        string $jam,
        int $durasiFilm,
        int $jedaMenit = 20,
    ): bool {
        $mulaiBaru = strtotime($tanggal . ' ' . $jam);
        $selesaiBaru = $mulaiBaru + ($durasiFilm + $jedaMenit) * 60;
        $stmt = Database::getInstance()
            ->getKoneksi()
            ->prepare(
                'SELECT j.tanggal,j.jam,f.durasi FROM jadwal j JOIN film f ON f.id=j.film_id WHERE j.studio_id=? AND j.tanggal=?',
            );
        $stmt->execute([$studioId, $tanggal]);
        foreach ($stmt->fetchAll() as $baris) {
            $mulaiLama = strtotime($baris['tanggal'] . ' ' . $baris['jam']);
            $selesaiLama = $mulaiLama + ((int) $baris['durasi'] + $jedaMenit) * 60;
            if ($mulaiBaru < $selesaiLama && $selesaiBaru > $mulaiLama) {
                return true;
            }
        }
        return false;
    }

    public static function hapus(int $id): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('DELETE FROM jadwal WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Mengambil daftar ID kursi yang SUDAH terpesan (status lunas/pending)
     * untuk sesi jadwal ini, dipakai halaman pilih-kursi agar kursi yang
     * sudah diambil tidak bisa dipilih customer lain.
     * @return int[]
     */
    public function getKursiTerpesan(?int $kecualiReservasiPengguna = null): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare(
            "SELECT kursi_ids FROM tiket WHERE jadwal_id = ? AND status IN ('lunas','pending','terpakai')",
        );
        $stmt->execute([$this->id]);
        $terpesan = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (explode(',', $row['kursi_ids']) as $kid) {
                if ($kid !== '') {
                    $terpesan[] = (int) $kid;
                }
            }
        }
        $detail = $pdo->prepare(
            "SELECT d.kursi_id FROM detail_tiket d JOIN tiket t ON t.id=d.tiket_id WHERE d.jadwal_id=? AND t.status IN ('lunas','pending','terpakai')",
        );
        $detail->execute([$this->id]);
        $terpesan = array_merge($terpesan, array_map('intval', array_column($detail->fetchAll(), 'kursi_id')));
        return array_values(
            array_unique(
                array_merge($terpesan, FiturBioskop::kursiDireservasi((int) $this->id, $kecualiReservasiPengguna)),
            ),
        );
    }
}
