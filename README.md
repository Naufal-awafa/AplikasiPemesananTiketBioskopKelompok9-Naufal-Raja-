# 🎬 Sineverse Cinema — Sistem Pemesanan Tiket Bioskop

Aplikasi web **PHP Native (Vanilla PHP, tanpa framework)** dengan UI/UX dark‑mode,
efek **Aurora background**, dan **Glassmorphism**, terinspirasi dari referensi visual
gendesignid.web.id. Backend menerapkan penuh 4 pilar **OOP (Object-Oriented Programming)**.

## 🚀 Cara Menjalankan

Proyek ini memakai **MySQL/MariaDB** melalui PDO. Pada XAMPP, aktifkan Apache dan MySQL;
aplikasi otomatis memakai database `pemesanantiket2` serta melengkapi skemanya saat diakses.

Syarat: PHP 8.0+, ekstensi `pdo_mysql`, dan MySQL/MariaDB. Koneksi bawaan memakai
`127.0.0.1:3306`, pengguna `root`, tanpa password. Nilainya dapat diganti lewat environment
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, dan `DB_PASS`.

```bash
cd bioskop
php -S localhost:8000
```

Lalu buka `http://localhost:8000/index.php` di browser.

> Jika ingin pakai Apache/XAMPP/Laragon, cukup taruh folder `bioskop/` ke dalam `htdocs`/`www`,
> lalu akses via `http://localhost/bioskop/index.php`.

## 🔑 Akun Demo

Password untuk **semua akun** di bawah ini adalah: **`password123`**

| Peran     | Email                     |
|-----------|---------------------------|
| Customer  | customer@sineverse.id     |
| Admin     | admin@sineverse.id        |
| Kasir     | kasir@sineverse.id        |
| Manajer   | manajer@sineverse.id      |

Atau daftar akun customer baru sendiri lewat halaman **Register**.

## 🗂️ Struktur Folder

```
bioskop/
├── index.php                 Beranda + daftar film
├── login.php / register.php / logout.php
├── film-detail.php           Detail film, jadwal, ulasan
├── booking-kursi.php         Pemilihan kursi interaktif
├── checkout.php              Ringkasan pesanan + metode bayar + promo
├── payment-process.php       Eksekusi objek LayananPembayaran (dummy gateway)
├── e-tiket.php               E-tiket + QR Code
├── riwayat.php / batalkan.php
│
├── admin/                    Panel Admin (kelola film, jadwal, studio, promo, laporan)
├── kasir/                    Panel Kasir (validasi QR, walk-in booking)
├── manajer/                  Panel Manajer (laporan & statistik)
│
├── core/                     ============ CLASS OOP ============
│   ├── Pengguna.php          Abstract parent class akun (Enkapsulasi + Pewarisan)
│   ├── Customer.php / Admin.php / Kasir.php / Manajer.php   (child class Pengguna)
│   ├── Film.php / Studio.php / Jadwal.php / Tiket.php
│   ├── LayananPembayaran.php Abstract parent class metode bayar
│   ├── PembayaranTransferBank.php / PembayaranEwallet.php
│   ├── PembayaranKartuKredit.php / PembayaranTunai.php      (child class LayananPembayaran)
│   ├── SistemPembayaran.php  Aktor "Sistem Pembayaran" (dummy gateway ala Midtrans/OVO)
│   ├── Promo.php / Ulasan.php / Laporan.php
│
├── config/Database.php       Singleton koneksi PDO (auto-init dari schema.sql)
├── includes/                 header, footer, navbar, sidebar dashboard, helper auth
├── assets/css/style.css      Dark mode + Aurora + Glassmorphism + micro-interactions
├── assets/js/script.js       Mouse-glow, scroll-reveal, seat picker, tabs, toast
└── database/schema.sql       Skema + seed data awal
```

## 🧩 Penerapan 4 Pilar OOP

1. **Class & Objek** — `Film`, `Studio`, `Kursi`, `Jadwal`, `Tiket`, `Promo`, `Ulasan`, dll.
   masing-masing merepresentasikan entitas nyata pada sistem tiket bioskop.
2. **Method dalam Class** — setiap class punya `__construct()` untuk inisialisasi properti,
   ditambah method terstruktur (`hitungHargaTiket()`, `generateKodeQr()`, `proses()`, dst).
3. **Enkapsulasi** — visibility diterapkan ketat:
   - `private`: `Pengguna::$password`, `Film::$hargaDasar`, `LayananPembayaran::$kodeTransaksi`,
     `Tiket::$kodeQr`, `PembayaranKartuKredit::$nomorKartu`.
   - `protected`: `Pengguna::$id/$nama/$email`, properti pada child class LayananPembayaran, dll.
   - `public`: seluruh method akses (`getNama()`, `verifikasiPassword()`, `proses()`, dst).
4. **Pewarisan** — dua hierarki:
   - `Pengguna` (abstract) → `Customer`, `Admin`, `Kasir`, `Manajer`
   - `LayananPembayaran` (abstract) → `PembayaranTransferBank`, `PembayaranEwallet`,
     `PembayaranKartuKredit`, `PembayaranTunai`

## 🆕 Fitur Tambahan (update kedua)

- **Preloader/splash logo** saat pertama membuka halaman beranda.
- Bagian "Tentang" diubah agar berorientasi produksi (bukan studi kasus).
- **Kasir** kini bisa memilih metode pembayaran walk-in: Tunai, Transfer Bank, E-Wallet, atau Kartu — tidak hanya tunai.
- **Struk transaksi walk-in** bisa langsung **dicetak** (tombol "Cetak / Simpan sebagai PDF" via dialog print browser) maupun **diunduh langsung** sebagai file `.html` mandiri (tombol "Unduh Struk", memicu download otomatis lewat header `Content-Disposition: attachment`).
- **Riwayat Walk-in** di akun Kasir — menampilkan seluruh transaksi walk-in yang pernah diproses kasir tersebut, lengkap dengan tombol lihat/unduh struk.
- **Kelola Pengguna** di akun Admin:
  - Membuat akun staff baru (Admin / Kasir / Manajer) — tersimpan di database.
  - Menghapus akun staff (kecuali akun sendiri).
  - **Memblokir (ban) / mengaktifkan kembali** akun Customer. Akun yang diblokir akan ditolak saat login, dan jika sedang aktif sesi-nya akan otomatis logout paksa.

Seluruh data akun (staff & customer) disimpan di tabel `pengguna` pada database MySQL yang sama — tidak ada data akun aktif yang disimpan di source code.

## 🎬 Integrasi TMDB API (data film asli)

- **Admin** kini punya menu **"Impor dari TMDB"** — bisa browse film yang sedang tayang/akan datang di bioskop sungguhan, atau cari judul tertentu, langsung dari [TMDB (The Movie Database)](https://www.themoviedb.org/).
- Sekali klik **"Impor"**, data judul, genre, sinopsis, poster asli, rating, dan **trailer YouTube resmi** otomatis masuk ke tabel `film` lokal. Admin tinggal menyesuaikan **harga tiket** & **status tayang/segera** seperti biasa di menu Kelola Film, lalu atur jadwalnya di Kelola Jadwal — urusan bisnis (harga, jadwal, studio) tetap sepenuhnya di tangan Admin, TMDB hanya sumber data film.
- Poster asli otomatis ditampilkan menggantikan poster gradient dummy di Beranda & Detail Film. Trailer YouTube resmi otomatis di-embed di halaman Detail Film jika tersedia.
- API key TMDB disimpan lokal: salin `config/Tmdb.example.php` menjadi
  `config/Tmdb.local.php`, lalu isi API Key v3 milikmu. File lokal tersebut
  diabaikan Git agar kunci tidak ikut terunggah.
- Kolom baru di tabel `film`: `tmdb_id`, `poster_url`, `trailer_key` — otomatis dilengkapi pada database MySQL lama tanpa menghapus data (lihat `Database::migrasiSkema()`).
- Sesuai ketentuan TMDB, atribusi "Data & poster sebagian film dari TMDB" dicantumkan di footer setiap halaman.

## ⚠️ Catatan Penting

- **Seluruh proses pembayaran bersifat SIMULASI/DUMMY** (ecek-ecek) — tidak terhubung ke
  Midtrans, OVO, bank, atau penyedia pembayaran manapun yang sesungguhnya.
- QR Code di-generate memakai library client-side [qrcodejs](https://github.com/davidshimjs/qrcodejs)
  via CDN — hanya untuk visualisasi, isinya adalah kode unik internal sistem.
- Cocok dijadikan bahan belajar OOP PHP native, bukan untuk produksi sungguhan tanpa
  hardening keamanan tambahan (validasi lebih ketat, CSRF token, rate limiting, dll).
