<?php
// filepath: /C:/xampp/htdocs/bank_sampah/nasabah/index.php
// /nasabah/index.php
require '../config/db.php';
require '../config/functions.php';

require_login('nasabah');
$id_nasabah = $_SESSION['id_nasabah'];

try {
    // 1. Ambil data profil nasabah
    $stmt_profil = $pdo->prepare("
        SELECT n.*, ki.nama_klaster, ki.deskripsi
        FROM nasabah n
        LEFT JOIN klaster_info ki ON n.id_klaster = ki.id_klaster
        WHERE n.id_nasabah = ?
    ");
    $stmt_profil->execute([$id_nasabah]);
    $profil = $stmt_profil->fetch();

    // 2. Ambil data agregat (total setor, frekuensi)
    $stmt_agg = $pdo->prepare("
        SELECT COALESCE(SUM(berat), 0) AS total_berat, COALESCE(COUNT(id_setor), 0) AS frekuensi_setor
        FROM transaksi_setor WHERE id_nasabah = ?
    ");
    $stmt_agg->execute([$id_nasabah]);
    $agregat = $stmt_agg->fetch();

    // 3. Ambil 5 riwayat terakhir (campuran)
    $stmt_riwayat = $pdo->prepare("
        SELECT tanggal_setor AS tanggal, 'Setor Sampah' AS jenis, total_harga AS jumlah, NULL AS berat
        FROM transaksi_setor WHERE id_nasabah = :id1
        UNION
        SELECT tanggal_tarik AS tanggal, 'Tarik Saldo' AS jenis, jumlah_tarik AS jumlah, NULL AS berat
        FROM transaksi_tarik WHERE id_nasabah = :id2
        ORDER BY tanggal DESC LIMIT 5
    ");
    $stmt_riwayat->execute(['id1' => $id_nasabah, 'id2' => $id_nasabah]);
    $riwayat = $stmt_riwayat->fetchAll();
    
    // --- 4. (FITUR BARU) Ambil data Leaderboard ---
    $stmt_leaderboard = $pdo->query("
        SELECT n.nama_lengkap, SUM(ts.berat) AS total_kg
        FROM transaksi_setor ts
        JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
        GROUP BY ts.id_nasabah
        ORDER BY total_kg DESC
        LIMIT 5
    ");
    $leaderboard = $stmt_leaderboard->fetchAll();

} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nasabah - Bank Sampah Bahrul Ulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0d6efd;
            --success: #20c997;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --shadow-soft: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-hover: 0 15px 35px rgba(0,0,0,0.15);
        }

        * { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #11998e 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .main-content {
            position: relative;
            z-index: 1;
        }

        /* Modern Navbar */
        .navbar {
            background: rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: #ffffff !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: #ffffff !important;
            transform: translateY(-2px);
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            transition: all 0.4s ease;
            overflow: hidden;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(255,255,255,0.3);
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.3);
            color: #ffffff;
            padding: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(45deg, #667eea, #764ba2, #11998e);
            border-radius: 20px;
            z-index: -1;
            opacity: 0.3;
        }

        /* Balance Card */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 24px;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
            border-radius: 50%;
        }

        .balance-amount {
            font-size: 3.5rem;
            font-weight: 800;
            text-shadow: 0 4px 8px rgba(0,0,0,0.2);
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .balance-label {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Status Card */
        .status-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
        }

        .cluster-badge {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        /* Stats Cards */
        .stat-card {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.weight {
            background: var(--gradient-success);
        }

        .stat-icon.frequency {
            background: var(--gradient-warning);
        }

        /* Leaderboard */
        .leaderboard-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 20px;
            overflow: hidden;
        }

        .leaderboard-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .leaderboard-item {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }

        .rank-badge {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
        }

        .kg-badge {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 20px;
            padding: 0.3rem 0.8rem;
            font-weight: 600;
        }

        /* Transaction History */
        .transaction-item {
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .transaction-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .transaction-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .transaction-icon.income {
            background: var(--gradient-success);
        }

        .transaction-icon.expense {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
        }

        /* Buttons */
        .btn-glass {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-glass:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .balance-amount { font-size: 2.5rem; }
            .container { padding: 0 1rem; }
            .glass-card { margin-bottom: 1.5rem; }
        }

        /* Floating Animation */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 6s ease-in-out infinite;
        }

        /* Scroll Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-leaf"></i> Bank Sampah Bahrul Ulum
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="riwayat.php">
                            <i class="fas fa-history"></i> Riwayat
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container mt-4">
            <!-- Welcome Banner -->
            <div class="welcome-banner fade-in">
                <h4 class="mb-1">
                    <i class="fas fa-hand-wave me-2"></i>
                    Selamat datang, <strong><?= htmlspecialchars($profil['nama_lengkap']) ?></strong>!
                </h4>
                <p class="mb-0 opacity-75">Kelas: <?= htmlspecialchars($profil['kelas']) ?></p>
            </div>

            <div class="row g-4">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Balance Card -->
                    <div class="balance-card glass-card mb-4 fade-in floating">
                        <div class="balance-label">Saldo Anda Saat Ini</div>
                        <h1 class="balance-amount"><?= format_rupiah($profil['saldo']) ?></h1>
                        <div class="mt-3">
                            <i class="fas fa-wallet me-2"></i>
                            Bank Sampah Digital Bahrul Ulum
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="stat-card fade-in">
                                <div class="stat-icon weight">
                                    <i class="fas fa-weight"></i>
                                </div>
                                <h3 class="mb-1"><?= number_format($agregat['total_berat'], 2) ?> kg</h3>
                                <p class="text-muted mb-0">Total Sampah Disetor</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card fade-in">
                                <div class="stat-icon frequency">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <h3 class="mb-1"><?= $agregat['frekuensi_setor'] ?></h3>
                                <p class="text-muted mb-0">Frekuensi Setoran</p>
                            </div>
                        </div>
                    </div>

                    <!-- Status Card -->
                    <div class="status-card mb-4 fade-in">
                        <h5 class="mb-3">
                            <i class="fas fa-chart-line me-2"></i>
                            Status Keaktifan Anda
                        </h5>
                        <?php if ($profil['id_klaster'] !== null): ?>
                            <div class="cluster-badge">
                                <i class="fas fa-star"></i>
                                <?= htmlspecialchars($profil['nama_klaster']) ?>
                            </div>
                            <p class="mt-3 mb-0 opacity-90"><?= htmlspecialchars($profil['deskripsi']) ?></p>
                        <?php else: ?>
                            <div class="cluster-badge">
                                <i class="fas fa-question-circle"></i>
                                Belum Terklasifikasi
                            </div>
                            <p class="mt-3 mb-0 opacity-90">Status Anda akan muncul setelah admin melakukan proses clustering data.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Leaderboard -->
                    <div class="leaderboard-card glass-card mb-4 fade-in">
                        <div class="leaderboard-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy me-2"></i>
                                Top 5 Nasabah
                            </h5>
                            <small class="opacity-75">Berdasarkan Berat Sampah</small>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($leaderboard as $index => $item): ?>
                            <div class="leaderboard-item d-flex align-items-center">
                                <div class="rank-badge"><?= $index + 1 ?></div>
                                <div class="flex-grow-1 text-start">
                                    <div class="fw-bold"><?= htmlspecialchars($item['nama_lengkap']) ?></div>
                                    <?php if ($item['nama_lengkap'] == $profil['nama_lengkap']): ?>
                                        <small class="opacity-75">
                                            <i class="fas fa-user me-1"></i>Anda
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <span class="kg-badge"><?= number_format($item['total_kg'], 1) ?> kg</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="glass-card fade-in">
                        <div class="card-body p-4">
                            <h5 class="text-white mb-3">
                                <i class="fas fa-clock me-2"></i>
                                Transaksi Terakhir
                            </h5>
                            <?php if (!empty($riwayat)): ?>
                                <?php foreach ($riwayat as $r): ?>
                                <div class="transaction-item d-flex align-items-center">
                                    <div class="transaction-icon <?= $r['jenis'] == 'Setor Sampah' ? 'income' : 'expense' ?>">
                                        <i class="fas fa-<?= $r['jenis'] == 'Setor Sampah' ? 'arrow-down' : 'arrow-up' ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark"><?= $r['jenis'] ?></div>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></small>
                                    </div>
                                    <div class="text-<?= $r['jenis'] == 'Setor Sampah' ? 'success' : 'danger' ?> fw-bold">
                                        <?= $r['jenis'] == 'Setor Sampah' ? '+' : '-' ?><?= format_rupiah($r['jumlah']) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <a href="riwayat.php" class="btn btn-glass w-100 mt-3">
                                    <i class="fas fa-list me-2"></i>
                                    Lihat Semua Riwayat
                                </a>
                            <?php else: ?>
                                <div class="text-center text-white-50 py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    Belum ada transaksi
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fade in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Add initial visibility to first elements
        setTimeout(() => {
            document.querySelectorAll('.fade-in').forEach((el, index) => {
                if (index < 2) el.classList.add('visible');
            });
        }, 100);
    </script>
</body>
</html>