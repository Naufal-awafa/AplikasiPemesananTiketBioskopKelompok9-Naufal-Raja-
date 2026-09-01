<?php
require_once __DIR__ . '/../config/Database.php';

/** CLASS Promo - kode diskon yang bisa dikelola Admin & dipakai Customer saat checkout. */
class Promo
{
    private ?int $id;
    protected string $kode;
    protected string $deskripsi;
    protected int $diskonPersen;
    protected string $berlakuHingga;
    protected bool $aktif;

    public function __construct(
        ?int $id,
        string $kode,
        string $deskripsi,
        int $diskonPersen,
        string $berlakuHingga,
        bool $aktif = true,
    ) {
        $this->id = $id;
        $this->kode = strtoupper($kode);
        $this->deskripsi = $deskripsi;
        $this->diskonPersen = $diskonPersen;
        $this->berlakuHingga = $berlakuHingga;
        $this->aktif = $aktif;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getKode(): string
    {
        return $this->kode;
    }
    public function getDeskripsi(): string
    {
        return $this->deskripsi;
    }
    public function getDiskonPersen(): int
    {
        return $this->diskonPersen;
    }
    public function getBerlakuHingga(): string
    {
        return $this->berlakuHingga;
    }
    public function isAktif(): bool
    {
        return $this->aktif;
    }

    public function hitungPotongan(int $totalHarga): int
    {
        return (int) round(($totalHarga * $this->diskonPersen) / 100);
    }

    private static function dariBaris(array $b): Promo
    {
        return new self(
            (int) $b['id'],
            $b['kode'],
            $b['deskripsi'],
            (int) $b['diskon_persen'],
            $b['berlaku_hingga'],
            (bool) $b['aktif'],
        );
    }

    /** @return Promo[] */
    public static function semua(): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query('SELECT * FROM promo ORDER BY id DESC');
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public static function cariByKode(string $kode): ?Promo
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM promo WHERE kode = ? AND aktif = 1 AND berlaku_hingga >= CURDATE()');
        $stmt->execute([strtoupper($kode)]);
        $b = $stmt->fetch();
        return $b ? self::dariBaris($b) : null;
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        if ($this->id) {
            $stmt = $pdo->prepare(
                'UPDATE promo SET kode=?, deskripsi=?, diskon_persen=?, berlaku_hingga=?, aktif=? WHERE id=?',
            );
            $stmt->execute([
                $this->kode,
                $this->deskripsi,
                $this->diskonPersen,
                $this->berlakuHingga,
                (int) $this->aktif,
                $this->id,
            ]);
            return $this->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO promo (kode, deskripsi, diskon_persen, berlaku_hingga, aktif) VALUES (?,?,?,?,?)',
        );
        $stmt->execute([$this->kode, $this->deskripsi, $this->diskonPersen, $this->berlakuHingga, (int) $this->aktif]);
        $this->id = (int) $pdo->lastInsertId();
        return $this->id;
    }

    public static function hapus(int $id): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('DELETE FROM promo WHERE id = ?');
        $stmt->execute([$id]);
    }
}
