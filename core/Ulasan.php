<?php
require_once __DIR__ . '/../config/Database.php';

/** CLASS Ulasan - review & rating film oleh customer. */
class Ulasan
{
    private ?int $id;
    protected int $filmId;
    protected int $customerId;
    protected int $rating;
    protected string $komentar;
    protected string $dibuatPada;
    protected string $namaCustomer; // hasil JOIN, hanya untuk tampilan
    protected bool $terverifikasi;

    public function __construct(
        ?int $id,
        int $filmId,
        int $customerId,
        int $rating,
        string $komentar,
        string $dibuatPada = '',
        string $namaCustomer = '',
        bool $terverifikasi = true,
    ) {
        $this->id = $id;
        $this->filmId = $filmId;
        $this->customerId = $customerId;
        $this->rating = max(1, min(5, $rating));
        $this->komentar = $komentar;
        $this->dibuatPada = $dibuatPada;
        $this->namaCustomer = $namaCustomer;
        $this->terverifikasi = $terverifikasi;
    }

    public function getRating(): int
    {
        return $this->rating;
    }
    public function getKomentar(): string
    {
        return $this->komentar;
    }
    public function getDibuatPada(): string
    {
        return $this->dibuatPada;
    }
    public function getNamaCustomer(): string
    {
        return $this->namaCustomer;
    }
    public function isTerverifikasi(): bool
    {
        return $this->terverifikasi;
    }

    private static function dariBaris(array $b): Ulasan
    {
        return new self(
            (int) $b['id'],
            (int) $b['film_id'],
            (int) $b['customer_id'],
            (int) $b['rating'],
            $b['komentar'],
            $b['dibuat_pada'],
            $b['nama'] ?? '',
            (bool) ($b['terverifikasi'] ?? 0),
        );
    }

    /** @return Ulasan[] */
    public static function untukFilm(int $filmId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare(
            'SELECT u.*, p.nama FROM ulasan u JOIN pengguna p ON p.id = u.customer_id WHERE u.film_id = ? ORDER BY u.id DESC',
        );
        $stmt->execute([$filmId]);
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare(
            'INSERT INTO ulasan (film_id, customer_id, rating, komentar, terverifikasi) VALUES (?,?,?,?,?)',
        );
        $stmt->execute([$this->filmId, $this->customerId, $this->rating, $this->komentar, (int) $this->terverifikasi]);
        $this->id = (int) $pdo->lastInsertId();
        return $this->id;
    }
}
