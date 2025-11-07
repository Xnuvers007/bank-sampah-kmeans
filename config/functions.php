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

/**
 * Validate CSRF token dan redirect jika invalid
 * 
 * @param string|null $token Token dari request
 * @param string $redirectUrl URL untuk redirect
 */
function validate_csrf($token = null, $redirectUrl = 'index.php') {
    require_once __DIR__ . '/csrf.php';
    
    if (!CSRF::validateToken($token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Token keamanan tidak valid. Silakan coba lagi.";
        header("Location: $redirectUrl");
        exit;
    }
}

/**
 * Check if request is valid POST with CSRF
 * 
 * @param string $redirectUrl URL untuk redirect jika invalid
 * @return bool
 */
function is_valid_post($redirectUrl = null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    require_once __DIR__ . '/csrf.php';
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Token keamanan tidak valid. Silakan coba lagi.";
        if ($redirectUrl) {
            header("Location: $redirectUrl");
            exit;
        }
        return false;
    }

    return true;
}

/**
 * Sanitize string untuk output HTML
 * 
 * @param string $string String yang akan di-sanitize
 * @return string
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array (recursive)
 * 
 * @param mixed $data Data yang akan di-sanitize
 * @return mixed
 */
function sanitize_data($data) {
    if (is_array($data)) {
        return array_map('sanitize_data', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

?>