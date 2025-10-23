<?php
// /login_process.php

session_start();
require_once __DIR__ . '/config/db.php';
$db = $pdo; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? ''); // Password dari form (plain text)

    // Ambil data pengguna dari database
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    $login_success = false;

    // PERINGATAN: INI SANGAT TIDAK AMAN
    // Membandingkan password plain text dari form
    // dengan password plain text dari database
    if ($user && $user['password'] === $password) {
        $login_success = true;
    }

    if ($login_success) {
        session_regenerate_id(true);

        // Simpan sesi
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect sesuai role
        if ($user['role'] == 'admin') {
            header("Location: admin/index.php");
            exit;
        } else {
            // Ambil id_nasabah
            $stmt_nasabah = $db->prepare("SELECT id_nasabah FROM nasabah WHERE id_user = :id_user");
            $stmt_nasabah->execute(['id_user' => $user['id_user']]);
            $nasabah = $stmt_nasabah->fetch();
            
            if ($nasabah) {
                $_SESSION['id_nasabah'] = $nasabah['id_nasabah'];
                header("Location: nasabah/index.php");
                exit;
            } else {
                session_unset();
                session_destroy();
                header("Location: index.php?error=Akun nasabah tidak terdaftar lengkap.");
                exit;
            }
        }

    } else {
        // Jika login gagal
        header("Location: index.php?error=Username atau password salah.");
        exit;
    }
}
?>