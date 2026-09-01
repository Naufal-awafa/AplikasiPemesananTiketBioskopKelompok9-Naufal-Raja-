<?php
/**
 * =====================================================================
 * config/Tmdb.php
 * ---------------------------------------------------------------------
 * Konfigurasi kunci API TMDB (The Movie Database).
 * Kunci ini adalah API Key v3 dari akun TMDB — dipakai untuk mengambil
 * data film asli (judul, sinopsis, poster, trailer YouTube resmi, dll)
 * yang bisa diimpor Admin ke database lokal lewat menu
 * "Impor Film dari TMDB".
 *
 * Prioritas konfigurasi:
 * 1. config/Tmdb.local.php (hanya untuk mesin lokal, diabaikan Git)
 * 2. environment variable TMDB_API_KEY
 * =====================================================================
 */

if (file_exists(__DIR__ . '/Tmdb.local.php')) {
    require_once __DIR__ . '/Tmdb.local.php';
}

if (!defined('TMDB_API_KEY')) {
    define('TMDB_API_KEY', getenv('TMDB_API_KEY') ?: '');
}
