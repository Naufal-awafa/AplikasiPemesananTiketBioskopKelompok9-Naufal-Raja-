<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * CLASS Tiket
 * >> ENKAPSULASI: $kodeQr bersifat private karena merupakan token unik
 *    sistem yang tidak boleh diset sembarangan dari luar - hanya boleh
 *    dibuat lewat method internal generateKodeQr() saat tiket dibuat.
 */
class Tiket
{
    private ?int $id;
    protected string $kodeTiket;
    protected int $jadwalId;
    protected ?int $customerId;
    protected ?int $kasirId;
    protected array $kursiIds;
    protected int $totalHarga;
    protected string $metodeBayar;
    protected string $rolePemesan;
    protected int $subtotalHarga;
    protected int $diskonJabatanPersen;
    protected int $potonganJabatan;
    protected string $kodePromo;
    protected int $potonganPromo;
    protected int $poinDigunakan;
    protected int $potonganPoin;
    protected int $totalProduk;
    protected string $refundStatus;
    protected string $status; // pending | lunas | batal | terpakai
    private string $kodeQr;
    protected string $dibuatPada;

    public function __construct(
        ?int $id,
        string $kodeTiket,
        int $jadwalId,
        ?int $customerId,
        array $kursiIds,
        int $totalHarga,
        string $metodeBayar,
        string $status = 'pending',
        string $kodeQr = '',
        string $dibuatPada = '',
        ?int $kasirId = null,
        string $rolePemesan = '',
        int $subtotalHarga = 0,
        int $diskonJabatanPersen = 0,
        int $potonganJabatan = 0,
        string $kodePromo = '',
        int $potonganPromo = 0,
        int $poinDigunakan = 0,
        int $potonganPoin = 0,
        int $totalProduk = 0,
        string $refundStatus = '',
    ) {
        $this->id = $id;
        $this->kodeTiket = $kodeTiket;
        $this->jadwalId = $jadwalId;
        $this->customerId = $customerId;
        $this->kasirId = $kasirId;
        $this->kursiIds = $kursiIds;
        $this->totalHarga = $totalHarga;
        $this->metodeBayar = $metodeBayar;
        $this->rolePemesan = $rolePemesan;
        $this->subtotalHarga = $subtotalHarga;
        $this->diskonJabatanPersen = $diskonJabatanPersen;
        $this->potonganJabatan = $potonganJabatan;
        $this->kodePromo = $kodePromo;
        $this->potonganPromo = $potonganPromo;
        $this->poinDigunakan = $poinDigunakan;
        $this->potonganPoin = $potonganPoin;
        $this->totalProduk = $totalProduk;
        $this->refundStatus = $refundStatus;
        $this->status = $status;
        $this->kodeQr = $kodeQr ?: $this->generateKodeQr($kodeTiket);
        $this->dibuatPada = $dibuatPada;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getKodeTiket(): string
    {
        return $this->kodeTiket;
    }
    public function getJadwalId(): int
    {
        return $this->jadwalId;
    }
    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }
    public function getKasirId(): ?int
    {
        return $this->kasirId;
    }
    public function getKursiIds(): array
    {
        return $this->kursiIds;
    }
    public function getTotalHarga(): int
    {
        return $this->totalHarga;
    }
    public function getMetodeBayar(): string
    {
        return $this->metodeBayar;
    }
    public function getRolePemesan(): string
    {
        return $this->rolePemesan;
    }
    public function getSubtotalHarga(): int
    {
        return $this->subtotalHarga ?: $this->totalHarga;
    }
    public function getDiskonJabatanPersen(): int
    {
        return $this->diskonJabatanPersen;
    }
    public function getPotonganJabatan(): int
    {
        return $this->potonganJabatan;
    }
    public function getKodePromo(): string
    {
        return $this->kodePromo;
    }
    public function getPotonganPromo(): int
    {
        return $this->potonganPromo;
    }
    public function getPoinDigunakan(): int
    {
        return $this->poinDigunakan;
    }
    public function getPotonganPoin(): int
    {
        return $this->potonganPoin;
    }
    public function getTotalProduk(): int
    {
        return $this->totalProduk;
    }
    public function getRefundStatus(): string
    {
        return $this->refundStatus;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getKodeQr(): string
    {
        return $this->kodeQr;
    }
    public function getDibuatPada(): string
    {
        return $this->dibuatPada;
    }

    /** Membuat token QR unik berbasis kode tiket + random string. */
    private function generateKodeQr(string $kodeTiket): string
    {
        return strtoupper(substr(hash('sha256', $kodeTiket . microtime()), 0, 24));
    }

    public static function buatKodeTiketBaru(): string
    {
        return 'TIX-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    }

    private static function dariBaris(array $b): Tiket
    {
        return new self(
            (int) $b['id'],
            $b['kode_tiket'],
            (int) $b['jadwal_id'],
            $b['customer_id'] !== null ? (int) $b['customer_id'] : null,
            $b['kursi_ids'] !== '' ? array_map('intval', explode(',', $b['kursi_ids'])) : [],
            (int) $b['total_harga'],
            $b['metode_bayar'],
            $b['status'],
            $b['kode_qr'],
            $b['dibuat_pada'],
            isset($b['kasir_id']) && $b['kasir_id'] !== null ? (int) $b['kasir_id'] : null,
            $b['role_pemesan'] ?? '',
            (int) ($b['subtotal_harga'] ?? 0),
            (int) ($b['diskon_jabatan_persen'] ?? 0),
            (int) ($b['potongan_jabatan'] ?? 0),
            $b['kode_promo'] ?? '',
            (int) ($b['potongan_promo'] ?? 0),
            (int) ($b['poin_digunakan'] ?? 0),
            (int) ($b['potongan_poin'] ?? 0),
            (int) ($b['total_produk'] ?? 0),
            $b['refund_status'] ?? '',
        );
    }

    public static function cariById(int $id): ?Tiket
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM tiket WHERE id = ?');
        $stmt->execute([$id]);
        $b = $stmt->fetch();
        return $b ? self::dariBaris($b) : null;
    }

    public static function cariByKode(string $kode): ?Tiket
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM tiket WHERE kode_tiket = ? OR kode_qr = ?');
        $stmt->execute([$kode, $kode]);
        $b = $stmt->fetch();
        return $b ? self::dariBaris($b) : null;
    }

    /** @return Tiket[] */
    public static function untukCustomer(int $customerId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM tiket WHERE customer_id = ? ORDER BY id DESC');
        $stmt->execute([$customerId]);
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    /** @return Tiket[] Riwayat transaksi walk-in yang diproses oleh kasir tertentu */
    public static function untukKasir(int $kasirId): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->prepare('SELECT * FROM tiket WHERE kasir_id = ? ORDER BY id DESC');
        $stmt->execute([$kasirId]);
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    /** @return Tiket[] */
    public static function semua(): array
    {
        $pdo = Database::getInstance()->getKoneksi();
        $stmt = $pdo->query('SELECT * FROM tiket ORDER BY id DESC');
        return array_map([self::class, 'dariBaris'], $stmt->fetchAll());
    }

    public function simpan(): int
    {
        $pdo = Database::getInstance()->getKoneksi();
        if ($this->id) {
            $stmt = $pdo->prepare('UPDATE tiket SET status=? WHERE id=?');
            $stmt->execute([$this->status, $this->id]);
            return $this->id;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO tiket (kode_tiket, jadwal_id, customer_id, kasir_id, kursi_ids, total_harga, metode_bayar, status, kode_qr, role_pemesan, subtotal_harga, diskon_jabatan_persen, potongan_jabatan, kode_promo, potongan_promo, poin_digunakan, potongan_poin, total_produk, refund_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        );
        $stmt->execute([
            $this->kodeTiket,
            $this->jadwalId,
            $this->customerId,
            $this->kasirId,
            implode(',', $this->kursiIds),
            $this->totalHarga,
            $this->metodeBayar,
            $this->status,
            $this->kodeQr,
            $this->rolePemesan,
            $this->subtotalHarga,
            $this->diskonJabatanPersen,
            $this->potonganJabatan,
            $this->kodePromo,
            $this->potonganPromo,
            $this->poinDigunakan,
            $this->potonganPoin,
            $this->totalProduk,
            $this->refundStatus,
        ]);
        $this->id = (int) $pdo->lastInsertId();
        return $this->id;
    }

    public function tandaiLunas(): void
    {
        $this->status = 'lunas';
        $this->simpan();
    }

    public function batalkan(): void
    {
        $this->status = 'batal';
        $this->simpan();
    }

    public function tandaiTerpakai(): void
    {
        $this->status = 'terpakai';
        $this->simpan();
    }
}
