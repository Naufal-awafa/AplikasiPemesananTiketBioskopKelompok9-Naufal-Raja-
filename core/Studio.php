<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * CLASS Studio - merepresentasikan ruang tayang (2D/3D/IMAX) beserta
 * kapasitas grid kursinya (baris x kolom).
 */
class Studio
{
    private ?int $id;
    protected string $nama;
    protected string $tipe;
    protected int $jumlahBaris;
    protected int $jumlahKolom;
    protected int $cabangId;

    public function __construct(
        ?int $id,
        string $nama,
        string $tipe,
        int $jumlahBaris,
        int $jumlahKolom,
        int $cabangId = 1,
    ) {
        $this->id = $id;
        $this->nama = $nama;
        $this->tipe = $tipe;
        $this->jumlahBaris = $jumlahBaris;
        $this->jumlahKolom = $jumlahKolom;
        $this->cabangId = $cabangId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getNama(): string
    {
        return $this->nama;
    }
    public function getTipe(): string
    {
        return $this->tipe;
    }
    public function getJumlahBaris(): int
    {
        return $this->jumlahBaris;
    }
    public function getJumlahKolom(): int
    {
        return $this->jumlahKolom;
    }
    public function getKapasitas(): int
    {
        return $this->jumlahBaris * $this->jumlahKolom;
    }
    public function getCabangId(): int
    {
        return $this->cabangId;
    }

    private static function dariBaris(array $b): Studio
    {
        return new self(
            (int) $b['id'],
            $b['nama'],
            $b['tipe'],
            (int) $b['jumlah_baris'],
            (int) $b['jumlah_kolom'],
            (int) ($b['cabang_id'] ?? 1),
        );
    }

    /** @return Studio[] */
    public static function semua(): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query('SELECT * FROM studio ORDER BY id ASC');
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public static function cariById(int $id): ?Studio
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM studio WHERE id = ?');
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        return $b ? self::dariBaris($b) : null;
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        if ($this->id) {
            $stmt = $pdo->prepare(
                'UPDATE studio SET nama=?, tipe=?, jumlah_baris=?, jumlah_kolom=?, cabang_id=? WHERE id=?',
            );
            $stmt->execute([
                $this->nama,
                $this->tipe,
                $this->jumlahBaris,
                $this->jumlahKolom,
                $this->cabangId,
                $this->id,
            ]);
            return $this->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO studio (nama, tipe, jumlah_baris, jumlah_kolom, cabang_id) VALUES (?,?,?,?,?)',
        );
        $stmt->execute([$this->nama, $this->tipe, $this->jumlahBaris, $this->jumlahKolom, $this->cabangId]);
        $this->id = (int) $pdo->lastInsertId();

        // Auto-generate layout kursi setiap kali studio baru dibuat
        Kursi::buatLayoutUntukStudio($this->id, $this->jumlahBaris, $this->jumlahKolom);
        return $this->id;
    }
}

/**
 * CLASS Kursi - satu baris data = satu kursi fisik di dalam studio.
 * Status ketersediaan dihitung on-the-fly relatif terhadap jadwal
 * tayang tertentu (lihat Jadwal::getKursiTerpesan()).
 */
class Kursi
{
    private ?int $id;
    protected int $studioId;
    protected string $baris;
    protected int $nomor;
    protected string $tipe;

    public function __construct(?int $id, int $studioId, string $baris, int $nomor, string $tipe = 'reguler')
    {
        $this->id = $id;
        $this->studioId = $studioId;
        $this->baris = $baris;
        $this->nomor = $nomor;
        $this->tipe = $tipe;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getLabel(): string
    {
        return $this->baris . $this->nomor;
    }
    public function getTipe(): string
    {
        return $this->tipe;
    }

    private static function dariBaris(array $b): Kursi
    {
        return new self((int) $b['id'], (int) $b['studio_id'], $b['baris'], (int) $b['nomor'], $b['tipe']);
    }

    /** @return Kursi[] */
    public static function untukStudio(int $studioId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM kursi WHERE studio_id = ? ORDER BY baris ASC, nomor ASC');
        $stmt->execute([$studioId]);
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public static function buatLayoutUntukStudio(int $studioId, int $jumlahBaris, int $jumlahKolom): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $abjad = range('A', 'Z');
        $stmt = $pdo->prepare('INSERT INTO kursi (studio_id, baris, nomor, tipe) VALUES (?,?,?,?)');
        for ($i = 0; $i < $jumlahBaris; $i++) {
            $labelBaris = $abjad[$i] ?? 'X' . $i;
            for ($n = 1; $n <= $jumlahKolom; $n++) {
                $tipe = $i >= $jumlahBaris - 2 ? 'vip' : 'reguler'; // 2 baris belakang = VIP
                $stmt->execute([$studioId, $labelBaris, $n, $tipe]);
            }
        }
    }
}
