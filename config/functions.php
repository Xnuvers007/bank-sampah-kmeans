<?php
// /config/functions.php

session_start();

/**
 * Fungsi untuk mengecek apakah user sudah login dan memiliki role yang sesuai.
 * Jika tidak, akan diredirect ke halaman login.
 *
 * @param string $required_role Role yang dibutuhkan ('admin' atau 'nasabah')
 */
function require_login($required_role) {
    // Cek apakah user sudah login
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: ../index.php?error=Sesi tidak valid. Silakan login.");
        exit;
    }

    // Cek apakah role-nya sesuai
    if ($_SESSION['role'] != $required_role) {
        header("Location: ../index.php?error=Anda tidak memiliki hak akses.");
        exit;
    }
}

/**
 * Fungsi untuk memformat angka menjadi format Rupiah.
 *
 * @param int $number
 * @return string
 */
function format_rupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}
?>