# Update Terbaru — Sineverse Cinema

Perubahan yang dilakukan berdasarkan permintaan:

## 1. Animasi Loading (Preloader)
- Diubah dari sekadar fade-timeout menjadi **hitungan persen 0→100% sungguhan**
  (naik acak natural, bukan linear kaku) + partikel mengambang di background.
- **Logo baru muncul SETELAH loading mencapai 100%** (fade+scale-in), lalu
  seluruh preloader hilang dan masuk ke Beranda — persis alur di web referensi.
- File: `includes/header.php`, `assets/js/script.js`, `assets/css/style.css`.

## 2. Font
- Font utama diganti dari **Plus Jakarta Sans** menjadi **Poppins**
  (sama seperti font utama di web referensi gendesign.id).

## 3. Tombol "Dashboard" untuk Customer
- Dihapus dari navbar. Sebelumnya customer punya link "Dashboard" yang
  sebenarnya cuma mengarah balik ke Beranda (`getDashboardUrl()` Customer
  memang `index.php`) — jadi link itu redundan dan sekarang disembunyikan
  khusus untuk role customer. Admin/Kasir/Manajer tetap punya link Dashboard
  seperti biasa karena dashboard mereka memang halaman terpisah.

## 4. CSS Mobile / Responsif
- **Bug diperbaiki**: tombol hamburger di HP sebelumnya tidak berfungsi sama
  sekali (JS men-toggle class `.mobile-open` tapi CSS-nya belum ada) — sekarang
  menu mobile benar-benar terbuka sebagai dropdown.
- **Peta kursi (seat map)** studio IMAX (12 kolom) yang tadinya overflow/rusak
  di layar sempit, sekarang scroll horizontal + ukuran kursi mengecil otomatis.
- Beberapa halaman (checkout, booking kursi, walk-in kasir, semua panel admin)
  memakai `style="grid-template-columns: 1fr 340px"` inline di HTML yang
  sebelumnya TIDAK bisa ditumpuk jadi 1 kolom di HP (inline style menang atas
  CSS biasa) — sekarang dipaksa 1 kolom di layar ≤780px.
- Tabel data, tombol hero, navbar, dan padding container dirapikan lebih lanjut
  untuk layar ≤640px dan ≤400px.

## Fitur yang TERNYATA sudah ada sebelumnya (sudah diuji ulang & dikonfirmasi jalan)
Beberapa permintaan sebelumnya sudah terimplementasi di file yang diupload:
- Kasir bisa pilih metode bayar: Tunai, Transfer Bank, E-Wallet, Kartu — sudah ada di `kasir/walkin.php`.
- Struk walk-in beneran bisa **diunduh** (`kasir/unduh-struk.php`, header `Content-Disposition: attachment`) dan **dicetak** (`kasir/struk.php`, tombol print browser).
- Riwayat walk-in di akun Kasir — sudah ada di `kasir/riwayat.php`.
- Admin bisa membuat akun staff baru (Admin/Kasir/Manajer) dan mem-blokir/aktifkan akun Customer — sudah ada di `admin/pengguna.php`.
- Seluruh akun (staff & customer) tersimpan di tabel `pengguna` pada database MySQL — tidak ada data akun aktif yang disimpan di source code.

Semua fitur di atas sudah saya uji ulang secara langsung (login, buat akun,
ban akun, transaksi walk-in, unduh struk) lewat server PHP sungguhan — bukan
cuma dibaca kodenya — dan semuanya berjalan sesuai catatan di atas.
