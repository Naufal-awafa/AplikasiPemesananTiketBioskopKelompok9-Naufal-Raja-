<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * =====================================================================
 * CLASS Film
 * ---------------------------------------------------------------------
 * >> ENKAPSULASI: $hargaDasar bersifat PRIVATE karena termasuk data
 *    sensitif bisnis (dasar perhitungan harga jual). Kode di luar class
 *    tidak boleh menimpanya sembarangan; harus lewat method public
 *    hitungHargaTiket() yang sudah menyertakan logika markup studio.
 * =====================================================================
 */
class Film
{
    private ?int $id;
    protected string $judul;
    protected string $genre;
    protected int $durasi;
    protected string $sinopsis;
    protected string $posterWarna;
    protected float $rating;
    protected string $status; // tayang | segera
    private int $hargaDasar; // properti sensitif -> private

    // Properti hasil impor dari TMDB (opsional -> punya default kosong
    // supaya seluruh pemanggilan `new Film(...)` yang sudah ada di project
    // tetap kompatibel tanpa perlu diubah satu per satu).
    protected ?int $tmdbId;
    protected string $posterUrl;
    protected string $trailerKey;
    protected string $bahasa;
    protected string $ratingUsia;

    public function __construct(
        ?int $id,
        string $judul,
        string $genre,
        int $durasi,
        string $sinopsis,
        string $posterWarna,
        float $rating,
        string $status,
        int $hargaDasar,
        ?int $tmdbId = null,
        string $posterUrl = '',
        string $trailerKey = '',
        string $bahasa = 'Indonesia',
        string $ratingUsia = 'SU',
    ) {
        $this->id = $id;
        $this->judul = $judul;
        $this->genre = $genre;
        $this->durasi = $durasi;
        $this->sinopsis = $sinopsis;
        $this->posterWarna = $posterWarna;
        $this->rating = $rating;
        $this->status = $status;
        $this->hargaDasar = $hargaDasar;
        $this->tmdbId = $tmdbId;
        $this->posterUrl = $posterUrl;
        $this->trailerKey = $trailerKey;
        $this->bahasa = $bahasa;
        $this->ratingUsia = $ratingUsia;
    }

    // ------------------ Getter publik (akses terkontrol) ------------------
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getJudul(): string
    {
        return $this->judul;
    }
    public function getGenre(): string
    {
        return $this->genre;
    }
    public function getDurasi(): int
    {
        return $this->durasi;
    }
    public function getSinopsis(): string
    {
        return $this->sinopsis;
    }
    public function getPosterWarna(): string
    {
        return $this->posterWarna;
    }
    public function getRating(): float
    {
        return $this->rating;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getTmdbId(): ?int
    {
        return $this->tmdbId;
    }
    public function getPosterUrl(): string
    {
        return $this->posterUrl;
    }
    public function getTrailerKey(): string
    {
        return $this->trailerKey;
    }
    public function getBahasa(): string
    {
        return $this->bahasa;
    }
    public function getRatingUsia(): string
    {
        return $this->ratingUsia;
    }

    /** True jika film ini punya poster asli hasil impor TMDB (bukan poster gradient dummy). */
    public function punyaPosterAsli(): bool
    {
        return $this->posterUrl !== '';
    }

    /** True jika film ini punya trailer YouTube resmi untuk ditampilkan. */
    public function punyaTrailer(): bool
    {
        return $this->trailerKey !== '';
    }

    /**
     * Method public untuk mengambil harga dasar TANPA mengekspos
     * properti private secara langsung (tetap lewat "pintu resmi").
     */
    public function getHargaDasar(): int
    {
        return $this->hargaDasar;
    }

    /**
     * Logika bisnis: menghitung harga tiket final berdasarkan tipe
     * studio (2D/3D/IMAX). Properti $hargaDasar yang private tetap
     * aman, hanya diproses secara internal di dalam method ini.
     */
    public function hitungHargaTiket(string $tipeStudio): int
    {
        $markup = match (strtoupper($tipeStudio)) {
            'IMAX' => 1.6,
            '3D' => 1.3,
            default => 1.0,
        };
        return (int) round($this->hargaDasar * $markup, -3); // dibulatkan ke ribuan
    }

    public function keArray(): array
    {
        return [
            'id' => $this->id,
            'judul' => $this->judul,
            'genre' => $this->genre,
            'durasi' => $this->durasi,
            'sinopsis' => $this->sinopsis,
            'poster_warna' => $this->posterWarna,
            'rating' => $this->rating,
            'status' => $this->status,
            'harga_dasar' => $this->hargaDasar,
            'tmdb_id' => $this->tmdbId,
            'poster_url' => $this->posterUrl,
            'trailer_key' => $this->trailerKey,
        ];
    }

    // ===================================================================
    // METHOD STATIC -> berinteraksi dengan database (mini Data Access)
    // ===================================================================

    private static function dariBaris(array $b): Film
    {
        return new self(
            (int) $b['id'],
            $b['judul'],
            $b['genre'],
            (int) $b['durasi'],
            $b['sinopsis'],
            $b['poster_warna'],
            (float) $b['rating'],
            $b['status'],
            (int) $b['harga_dasar'],
            isset($b['tmdb_id']) && $b['tmdb_id'] !== null ? (int) $b['tmdb_id'] : null,
            $b['poster_url'] ?? '',
            $b['trailer_key'] ?? '',
            $b['bahasa'] ?? 'Indonesia',
            $b['rating_usia'] ?? 'SU',
        );
    }

    /** @return Film[] */
    public static function semua(string $status = ''): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        if ($status !== '') {
            $stmt = $pdo->prepare('SELECT * FROM film WHERE status = ? ORDER BY id DESC');
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query('SELECT * FROM film ORDER BY id DESC');
        }
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public static function cariById(int $id): ?Film
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM film WHERE id = ?');
        $stmt->execute([$id]);
        $baris = $stmt->fetch();
        return $baris ? self::dariBaris($baris) : null;
    }

    /** Mencari film lokal yang sudah pernah diimpor dari tmdb_id tertentu (cegah duplikat impor). */
    public static function cariByTmdbId(int $tmdbId): ?Film
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM film WHERE tmdb_id = ?');
        $stmt->execute([$tmdbId]);
        $baris = $stmt->fetch();
        return $baris ? self::dariBaris($baris) : null;
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        if ($this->id) {
            $stmt = $pdo->prepare(
                'UPDATE film SET judul=?, genre=?, durasi=?, sinopsis=?, poster_warna=?, rating=?, status=?, harga_dasar=?, tmdb_id=?, poster_url=?, trailer_key=?, bahasa=?, rating_usia=? WHERE id=?',
            );
            $stmt->execute([
                $this->judul,
                $this->genre,
                $this->durasi,
                $this->sinopsis,
                $this->posterWarna,
                $this->rating,
                $this->status,
                $this->hargaDasar,
                $this->tmdbId,
                $this->posterUrl,
                $this->trailerKey,
                $this->bahasa,
                $this->ratingUsia,
                $this->id,
            ]);
            return $this->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO film (judul, genre, durasi, sinopsis, poster_warna, rating, status, harga_dasar, tmdb_id, poster_url, trailer_key, bahasa, rating_usia) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        );
        $stmt->execute([
            $this->judul,
            $this->genre,
            $this->durasi,
            $this->sinopsis,
            $this->posterWarna,
            $this->rating,
            $this->status,
            $this->hargaDasar,
            $this->tmdbId,
            $this->posterUrl,
            $this->trailerKey,
            $this->bahasa,
            $this->ratingUsia,
        ]);
        $this->id = (int) $pdo->lastInsertId();
        return $this->id;
    }

    /**
     * Menghapus film beserta SELURUH data anak yang masih mereferensikan
     * film ini (pembayaran, tiket, jadwal, ulasan) agar tidak melanggar
     * FOREIGN KEY constraint di MySQL. Dibungkus dalam satu transaksi
     * supaya konsisten: kalau ada langkah yang gagal, semua dibatalkan.
     */
    public static function hapus(int $id): void
    {
        $pdo = Database::getInstance()->getKoneksi();
        $pdo->beginTransaction();
        try {
            // 1) Hapus pembayaran milik tiket-tiket dari jadwal film ini
            $stmt = $pdo->prepare(
                'DELETE FROM pembayaran WHERE tiket_id IN (
                    SELECT id FROM tiket WHERE jadwal_id IN (
                        SELECT id FROM jadwal WHERE film_id = ?
                    )
                )',
            );
            $stmt->execute([$id]);

            // 2) Hapus tiket-tiket dari jadwal film ini
            $stmt = $pdo->prepare(
                'DELETE FROM tiket WHERE jadwal_id IN (
                    SELECT id FROM jadwal WHERE film_id = ?
                )',
            );
            $stmt->execute([$id]);

            // 3) Hapus seluruh jadwal tayang film ini
            $stmt = $pdo->prepare('DELETE FROM jadwal WHERE film_id = ?');
            $stmt->execute([$id]);

            // 4) Hapus seluruh ulasan/review film ini
            $stmt = $pdo->prepare('DELETE FROM ulasan WHERE film_id = ?');
            $stmt->execute([$id]);

            // 5) Baru hapus film-nya sendiri
            $stmt = $pdo->prepare('DELETE FROM film WHERE id = ?');
            $stmt->execute([$id]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
