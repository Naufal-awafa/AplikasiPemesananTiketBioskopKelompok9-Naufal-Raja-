<?php
require_once __DIR__ . '/../config/Tmdb.php';

/**
 * =====================================================================
 * CLASS TmdbService
 * ---------------------------------------------------------------------
 * Wrapper untuk TMDB API (The Movie Database) — dipakai Admin untuk
 * mengimpor data film ASLI (judul, genre, sinopsis, poster, trailer
 * YouTube resmi) ke database lokal, alih-alih mengetik manual/dummy.
 *
 * Seluruh method bersifat static (murni "jembatan" ke API luar, tidak
 * menyimpan state) — selaras dengan gaya class Laporan di project ini.
 *
 * Endpoint yang dipakai:
 *  - GET /movie/now_playing  -> film yang sedang tayang di bioskop
 *  - GET /movie/upcoming     -> film yang akan datang
 *  - GET /search/movie       -> pencarian judul
 *  - GET /movie/{id}?append_to_response=videos -> detail + trailer
 *  - GET /genre/movie/list   -> pemetaan genre_id -> nama genre
 * =====================================================================
 */
class TmdbService
{
    private const BASE_URL = 'https://api.themoviedb.org/3';
    private const IMG_BASE = 'https://image.tmdb.org/t/p/w500';

    /** @var array<int,string>|null cache pemetaan genre_id -> nama, supaya tidak fetch berulang */
    private static ?array $genreCache = null;

    // ===================================================================
    // PEMANGGIL HTTP DASAR
    // ===================================================================

    /**
     * Memanggil satu endpoint TMDB dan mengembalikan hasil decode JSON.
     * Melempar RuntimeException jika koneksi gagal / API mengembalikan error,
     * supaya halaman pemanggil bisa menampilkan pesan yang jelas ke Admin.
     */
    private static function panggil(string $endpoint, array $query = []): array
    {
        $query['api_key'] = TMDB_API_KEY;
        $query['language'] = $query['language'] ?? 'id-ID';

        $url = self::BASE_URL . $endpoint . '?' . http_build_query($query);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $isi = curl_exec($ch);
            $error = curl_error($ch);
            $kode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($isi === false) {
                throw new RuntimeException('Gagal terhubung ke TMDB: ' . $error);
            }
        } else {
            $konteks = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]);
            $isi = @file_get_contents($url, false, $konteks);
            $kode = 200;
            if (isset($http_response_header[0]) && preg_match('/(\d{3})/', $http_response_header[0], $m)) {
                $kode = (int) $m[1];
            }
            if ($isi === false) {
                throw new RuntimeException('Gagal terhubung ke TMDB (file_get_contents).');
            }
        }

        $data = json_decode($isi, true);

        if ($kode >= 400 || !is_array($data)) {
            $pesan = $data['status_message'] ?? 'Respons TMDB tidak valid (HTTP ' . $kode . ').';
            throw new RuntimeException('TMDB API error: ' . $pesan);
        }

        return $data;
    }

    /** Peta genre_id -> nama genre Bahasa Indonesia, di-cache dalam satu request. */
    private static function daftarGenre(): array
    {
        if (self::$genreCache !== null) {
            return self::$genreCache;
        }
        try {
            $data = self::panggil('/genre/movie/list');
            $peta = [];
            foreach ($data['genres'] ?? [] as $g) {
                $peta[(int) $g['id']] = $g['name'];
            }
            self::$genreCache = $peta;
        } catch (RuntimeException $e) {
            self::$genreCache = [];
        }
        return self::$genreCache;
    }

    /** Mengubah satu baris hasil list TMDB menjadi array ringkas siap pakai UI/impor. */
    private static function normalisasi(array $item, string $statusSaran): array
    {
        $peta = self::daftarGenre();
        $namaGenre = [];
        foreach ($item['genre_ids'] ?? [] as $gid) {
            if (isset($peta[$gid])) {
                $namaGenre[] = $peta[$gid];
            }
        }

        return [
            'tmdb_id' => (int) $item['id'],
            'judul' => $item['title'] ?? '(Tanpa judul)',
            'genre' => $namaGenre ? implode(', ', $namaGenre) : 'Umum',
            'sinopsis' => $item['overview'] ?? '',
            'poster_url' => self::urlPoster($item['poster_path'] ?? null),
            'rating' => round(((float) ($item['vote_average'] ?? 0)) / 2, 1), // TMDB skala 10 -> skala 5
            'tanggal_rilis' => $item['release_date'] ?? '',
            'status_saran' => $statusSaran,
        ];
    }

    // ===================================================================
    // METHOD PUBLIK
    // ===================================================================

    /** @return array<int,array> daftar film yang sedang tayang di bioskop (real-time dari TMDB) */
    public static function sedangTayang(int $halaman = 1): array
    {
        $data = self::panggil('/movie/now_playing', ['page' => $halaman]);
        return [
            'hasil' => array_map(fn($it) => self::normalisasi($it, 'tayang'), $data['results'] ?? []),
            'halaman' => (int) ($data['page'] ?? 1),
            'total_halaman' => (int) ($data['total_pages'] ?? 1),
        ];
    }

    /** @return array<int,array> daftar film yang akan datang */
    public static function akanDatang(int $halaman = 1): array
    {
        $data = self::panggil('/movie/upcoming', ['page' => $halaman]);
        return [
            'hasil' => array_map(fn($it) => self::normalisasi($it, 'segera'), $data['results'] ?? []),
            'halaman' => (int) ($data['page'] ?? 1),
            'total_halaman' => (int) ($data['total_pages'] ?? 1),
        ];
    }

    /** @return array<int,array> hasil pencarian judul film */
    public static function cari(string $kataKunci, int $halaman = 1): array
    {
        $data = self::panggil('/search/movie', ['query' => $kataKunci, 'page' => $halaman, 'include_adult' => 'false']);
        return [
            'hasil' => array_map(fn($it) => self::normalisasi($it, 'tayang'), $data['results'] ?? []),
            'halaman' => (int) ($data['page'] ?? 1),
            'total_halaman' => (int) ($data['total_pages'] ?? 1),
        ];
    }

    /**
     * Detail lengkap 1 film TMDB, termasuk key trailer YouTube resmi
     * (jika tersedia). Dipakai persis sebelum impor ke database lokal.
     */
    public static function detail(int $tmdbId): array
    {
        $data = self::panggil('/movie/' . $tmdbId, ['append_to_response' => 'videos']);

        $namaGenre = array_map(fn($g) => $g['name'], $data['genres'] ?? []);
        $trailerKey = self::ambilTrailerKey($data['videos']['results'] ?? []);

        // Banyak film tidak memiliki metadata video berbahasa Indonesia.
        // Ambil trailer internasional sebagai fallback agar katalog tetap lengkap.
        if ($trailerKey === '') {
            $videoInternasional = self::panggil('/movie/' . $tmdbId . '/videos', ['language' => 'en-US']);
            $trailerKey = self::ambilTrailerKey($videoInternasional['results'] ?? []);
        }

        return [
            'tmdb_id' => (int) $data['id'],
            'judul' => $data['title'] ?? '(Tanpa judul)',
            'genre' => $namaGenre ? implode(', ', $namaGenre) : 'Umum',
            'durasi' => (int) ($data['runtime'] ?? 100) ?: 100,
            'sinopsis' => $data['overview'] ?? '',
            'poster_url' => self::urlPoster($data['poster_path'] ?? null),
            'rating' => round(((float) ($data['vote_average'] ?? 0)) / 2, 1),
            'tanggal_rilis' => $data['release_date'] ?? '',
            'trailer_key' => $trailerKey,
        ];
    }

    /** Mencari key video trailer YouTube resmi dari daftar "videos" TMDB. */
    private static function ambilTrailerKey(array $videos): string
    {
        // Prioritas 1: trailer resmi YouTube
        foreach ($videos as $v) {
            if (($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer' && !empty($v['official'])) {
                return $v['key'];
            }
        }
        // Prioritas 2: trailer YouTube apapun
        foreach ($videos as $v) {
            if (($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer') {
                return $v['key'];
            }
        }
        // Prioritas 3: teaser YouTube (fallback)
        foreach ($videos as $v) {
            if (($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Teaser') {
                return $v['key'];
            }
        }
        return '';
    }

    public static function urlPoster(?string $path): string
    {
        return $path ? self::IMG_BASE . $path : '';
    }
}
