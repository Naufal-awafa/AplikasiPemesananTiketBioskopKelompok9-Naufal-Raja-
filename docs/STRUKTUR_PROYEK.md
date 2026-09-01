# Struktur Proyek

Struktur ini mempertahankan berkas halaman publik di root agar URL aplikasi tidak berubah.

```text
aplikasiPemesananTiketBioskop/
├── admin/             Halaman dan proses khusus administrator
├── assets/            CSS, JavaScript, gambar, dan aset tampilan
├── config/            Konfigurasi database dan layanan eksternal
├── core/              Class domain dan logika bisnis
├── database/          Skema serta data awal database
├── docs/              Dokumentasi tambahan proyek
├── includes/          Layout, helper, dan komponen PHP bersama
├── kasir/             Halaman dan proses khusus kasir
├── manajer/           Halaman dan proses khusus manajer
├── storage/backups/   Salinan lama yang tidak digunakan saat runtime
└── *.php               Entry point publik agar URL lama tetap kompatibel
```

## Pedoman penempatan file

- Simpan class dan logika bisnis baru di `core/`.
- Simpan konfigurasi di `config/` dan jangan mencampurnya dengan tampilan.
- Simpan komponen tampilan bersama di `includes/`.
- Simpan aset berdasarkan jenisnya di dalam `assets/`.
- Simpan dokumentasi selain `README.md` dan `CHANGELOG.md` di `docs/`.
- Jangan memindahkan entry point publik tanpa menambahkan routing atau redirect yang kompatibel.

Format dasar proyek ditentukan dalam `.editorconfig`: PHP memakai indentasi 4 spasi, sedangkan CSS, JavaScript, dan Markdown memakai 2 spasi.
