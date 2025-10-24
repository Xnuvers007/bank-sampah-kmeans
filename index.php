<?php
session_start();
// Jika sudah login, redirect ke dashboard yang sesuai
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: nasabah/index.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Sampah Bahrul Ulum - Masuk atau Daftar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2e8b57;
            --secondary: #3cb371;
            --accent: #20c997;
            --dark: #1c1e21;
            --gray: #65676b;
            --light-gray: #e4e6eb;
            --bg-light: #f0f2f5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Full Background with Logo */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/img/logo.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.08;
            z-index: 0;
            animation: zoomInOut 30s ease-in-out infinite;
        }

        @keyframes zoomInOut {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        /* Gradient Overlay */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* background: linear-gradient(135deg, rgba(46, 139, 87, 0.85) 0%, rgba(60, 179, 113, 0.85) 100%); */
            background:linear-gradient(135deg, rgb(0 0 0 / 0%) 0%, rgb(60 179 113 / 0%) 100%);
            z-index: 0;
        }

        .main-container {
            width: 100%;
            max-width: 1000px;
            display: flex;
            align-items: center;
            gap: 80px;
            position: relative;
            z-index: 1;
        }

        /* Left Section */
        .left-section {
            flex: 1;
            padding-right: 32px;
            color: white;
        }

        .logo-section {
            margin-bottom: 16px;
        }

        .logo-section img {
            height: 100px;
            animation: fadeInDown 0.6s ease-out;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-name {
            font-size: 3.5rem;
            font-weight: 800;
            color: green;
            margin-bottom: 16px;
            line-height: 1.1;
            animation: fadeInLeft 0.6s ease-out;
            text-shadow: 2px 4px 8px rgba(0,0,0,0.3);
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .tagline {
            font-size: 1.75rem;
            color: green;
            line-height: 1.4;
            font-weight: 400;
            animation: fadeInLeft 0.6s ease-out 0.1s backwards;
            text-shadow: 1px 2px 4px rgba(0,0,0,0.2);
        }

        .features-list {
            margin-top: 40px;
            animation: fadeInLeft 0.6s ease-out 0.2s backwards;
        }

        .feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 50px;
            margin: 8px 8px 8px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
            color: black;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .feature-badge:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }

        .feature-badge i {
            color: var(--accent);
            font-size: 1.1rem;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        /* Right Section - Login Card */
        .right-section {
            flex: 1;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            padding: 40px 32px;
            animation: fadeInUp 0.6s ease-out 0.3s backwards;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 16px;
        }

        .form-control {
            width: 100%;
            padding: 16px 16px 16px 48px;
            border: 2px solid var(--light-gray);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-light);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(46, 139, 87, 0.1);
        }

        .form-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus ~ .form-icon {
            color: var(--primary);
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(46, 139, 87, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.4s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-danger {
            background: #fee;
            color: #c00;
            border-left: 4px solid #c00;
        }

        .alert-success {
            background: #efe;
            color: #0a0;
            border-left: 4px solid #0a0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--light-gray);
        }

        .stat-item {
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            background: var(--bg-light);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 4px;
        }

        .footer-text {
            text-align: center;
            margin-top: 32px;
            color: var(--gray);
            font-size: 0.85rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-container {
                flex-direction: column;
                gap: 40px;
                max-width: 480px;
            }

            .left-section {
                text-align: center;
                padding-right: 0;
            }

            .brand-name {
                font-size: 2.5rem;
            }

            .tagline {
                font-size: 1.3rem;
            }

            .features-list {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }

            .right-section {
                width: 100%;
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            body::before {
                background-size: contain;
            }

            .brand-name {
                font-size: 2rem;
            }

            .tagline {
                font-size: 1.1rem;
            }

            .login-card {
                padding: 32px 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .feature-badge {
                font-size: 0.85rem;
                padding: 8px 16px;
            }
        }

        html {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Left Section -->
        <div class="left-section">
            <div class="logo-section">
                <!-- <img src="assets/img/logo.png" alt="Logo Bank Sampah"> -->
            </div>
            <h1 class="brand-name">Bank Sampah<br>Bahrul Ulum</h1>
            <p class="tagline">Kelola sampah jadi berkah, wujudkan lingkungan bersih dan saldo yang bertambah.</p>
            
            <div class="features-list">
                <span class="feature-badge">
                    <i class="fas fa-recycle"></i>
                    Setor Sampah
                </span>
                <span class="feature-badge">
                    <i class="fas fa-coins"></i>
                    Tarik Saldo
                </span>
                <span class="feature-badge">
                    <i class="fas fa-chart-line"></i>
                    Laporan Real-time
                </span>
                <span class="feature-badge">
                    <i class="fas fa-mobile-alt"></i>
                    Akses Mudah
                </span>
                <span class="feature-badge">
                    <i class="fas fa-shield-check"></i>
                    Aman & Terpercaya
                </span>
            </div>
        </div>

        <!-- Right Section - Login Card -->
        <div class="right-section">
            <div class="login-card">
                <div class="login-header">
                    <h2>Masuk ke Akun Anda</h2>
                    <p>Silakan login untuk melanjutkan</p>
                </div>

                <!-- Error/Success Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $_SESSION['error'] ?></span>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $_SESSION['success'] ?></span>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Login Form -->
                <form action="login_process.php" method="POST">
                    <div class="form-group">
                        <input type="text" class="form-control" name="username" placeholder="Username atau Email" required autofocus>
                        <i class="fas fa-user form-icon"></i>
                    </div>

                    <div class="form-group">
                        <input type="password" class="form-control" name="password" placeholder="Password" required>
                        <i class="fas fa-lock form-icon"></i>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Masuk
                    </button>
                </form>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value">250+</span>
                        <span class="stat-label">Nasabah Aktif</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">2.5T</span>
                        <span class="stat-label">Sampah Terkumpul</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">5JT+</span>
                        <span class="stat-label">Total Transaksi</span>
                    </div>
                </div>

                <!-- Footer -->
                <div class="footer-text">
                    <p>&copy; <?= date('Y') ?> Bank Sampah Bahrul Ulum. <br>
                    Dibuat dengan <i class="fas fa-heart text-danger"></i> untuk lingkungan yang lebih baik.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>