<?php
require_once __DIR__ . '/FiturBioskop.php';
/**
 * =====================================================================
 * ABSTRACT CLASS Pengguna
 * ---------------------------------------------------------------------
 * Ini adalah PARENT CLASS dari seluruh aktor bertipe akun pada sistem:
 * Customer, Admin, Kasir, dan Manajer (lihat core/Customer.php, dst).
 *
 * >> PILAR OOP #1 - CLASS & OBJEK
 *    Class ini adalah cetak biru (blueprint) untuk merepresentasikan
 *    data utama "akun pengguna" pada sistem tiket bioskop.
 *
 * >> PILAR OOP #3 - ENKAPSULASI
 *    - private   $password   -> properti paling sensitif, TIDAK BOLEH
 *                                diakses/diubah langsung dari luar class,
 *                                hanya lewat method verifikasiPassword().
 *    - protected $id, $nama, $email, $noHp, $role -> hanya boleh dibaca/
 *                                diubah oleh class ini sendiri ATAU
 *                                child class-nya (Customer, Admin, dst),
 *                                tidak bisa diakses langsung dari luar.
 *    - public method (getNama, getEmail, dst) -> pintu resmi bagi kode
 *                                di luar class untuk mengambil data.
 *
 * >> PILAR OOP #4 - PEWARISAN (di file lain)
 *    Class ini bersifat ABSTRACT (tidak bisa di-instantiate langsung)
 *    dan berisi method abstrak getRole() & getDashboardUrl() yang WAJIB
 *    di-override oleh setiap child class sesuai peran masing-masing.
 * =====================================================================
 */
abstract class Pengguna
{
    protected ?int $id;
    protected string $nama;
    protected string $email;
    private string $password; // ENKAPSULASI: hanya boleh diverifikasi, tidak dibaca langsung
    protected string $noHp;
    protected string $role;
    protected string $status; // aktif / banned -> dipakai fitur ban akun oleh Admin
    protected string $dibuatPada;

    /**
     * >> PILAR OOP #2 - METHOD DALAM CLASS (__construct)
     * Constructor dipanggil otomatis setiap kali objek baru dibuat,
     * bertugas menginisialisasi seluruh properti di atas.
     */
    public function __construct(
        ?int $id,
        string $nama,
        string $email,
        string $password,
        string $noHp = '',
        string $role = 'customer',
        string $dibuatPada = '',
        string $status = 'aktif',
    ) {
        $this->id = $id;
        $this->nama = $nama;
        $this->email = $email;
        $this->password = $password; // sudah dalam bentuk hash saat disimpan
        $this->noHp = $noHp;
        $this->role = $role;
        $this->dibuatPada = $dibuatPada;
        $this->status = $status ?: 'aktif';
    }

    // ---------------------------------------------------------------
    // METHOD PUBLIC -> "pintu resmi" mengakses properti protected/private
    // ---------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNoHp(): string
    {
        return $this->noHp;
    }

    /**
     * Method public untuk mengubah nomor HP tanpa mengekspos properti
     * protected secara langsung ke luar class.
     */
    public function setNoHp(string $noHpBaru): void
    {
        $this->noHp = trim($noHpBaru);
    }

    /** Mengembalikan path relatif foto profil, atau '' bila belum diunggah. */
    public function getFoto(): string
    {
        return $this->foto ?? '';
    }

    /** Menyimpan path relatif foto profil (mis. assets/img/profil/xxx.jpg). */
    public function setFoto(string $path): void
    {
        $this->foto = $path;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPoin(): int
    {
        if (!$this->id) {
            return 0;
        }
        $stmt = Database::getInstance()->getKoneksi()->prepare('SELECT poin FROM pengguna WHERE id=?');
        $stmt->execute([$this->id]);
        return (int) $stmt->fetchColumn();
    }

    /** Dipakai sebelum login/akses halaman untuk menolak akun yang diblokir Admin. */
    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    /** Semua akun aktif dapat memesan tiket untuk dirinya sendiri. */
    public function bolehMemesan(int $jumlahKursiDipilih): bool
    {
        return $jumlahKursiDipilih > 0 && $jumlahKursiDipilih <= 6;
    }

    /** Persentase benefit pegawai. Customer biasa tidak mendapat diskon jabatan. */
    public function getDiskonJabatanPersen(): int
    {
        return 0;
    }

    protected function diskonDariPengaturan(string $kunci, int $fallback): int
    {
        $batas = max(0, (int) FiturBioskop::pengaturan('batas_diskon_staff_bulanan', '8'));
        if ($batas > 0 && $this->id) {
            $stmt = Database::getInstance()
                ->getKoneksi()
                ->prepare(
                    "SELECT COUNT(*) FROM tiket WHERE customer_id=? AND potongan_jabatan>0 AND DATE_FORMAT(dibuat_pada,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') AND status!='batal'",
                );
            $stmt->execute([$this->id]);
            if ((int) $stmt->fetchColumn() >= $batas) {
                return 0;
            }
        }
        return max(0, min(100, (int) FiturBioskop::pengaturan($kunci, (string) $fallback)));
    }

    /**
     * Method public untuk mengubah nama tanpa mengekspos properti
     * protected secara langsung ke luar class (validasi bisa ditambah
     * di sini kapan saja tanpa mengubah kode pemanggil).
     */
    public function updateNama(string $namaBaru): void
    {
        $this->nama = trim($namaBaru);
    }

    /**
     * Membuat hash password baru (dipakai saat registrasi/reset).
     * Static agar bisa dipanggil tanpa instance: Pengguna::buatHash(...)
     */
    public static function buatHash(string $passwordPolos): string
    {
        return password_hash($passwordPolos, PASSWORD_BCRYPT);
    }

    /**
     * >> DEMONSTRASI ENKAPSULASI KUNCI:
     * Properti $password bersifat PRIVATE, satu-satunya cara memeriksa
     * kecocokan password adalah lewat method public ini. Kode di luar
     * class TIDAK PERNAH bisa membaca isi $password secara langsung.
     */
    public function verifikasiPassword(string $passwordInput): bool
    {
        return password_verify($passwordInput, $this->password);
    }

    /**
     * Representasi ringkas untuk ditampilkan di UI (tanpa data sensitif).
     */
    public function keArray(): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'email' => $this->email,
            'no_hp' => $this->noHp,
            'role' => $this->role,
        ];
    }

    // ---------------------------------------------------------------
    // METHOD ABSTRAK -> WAJIB di-override oleh setiap child class.
    // Inilah inti PEWARISAN: setiap turunan Pengguna berperilaku
    // berbeda sesuai perannya masing-masing di sistem bioskop.
    // ---------------------------------------------------------------

    /** Menentukan halaman dashboard sesuai peran akun. */
    abstract public function getDashboardUrl(): string;

    /** Label peran yang ditampilkan di UI (badge role). */
    abstract public function getLabelPeran(): string;
}
