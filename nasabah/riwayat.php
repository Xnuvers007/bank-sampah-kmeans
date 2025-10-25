<?php
// filepath: /C:/xampp/htdocs/bank_sampah/nasabah/riwayat.php
// /nasabah/riwayat.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai nasabah
require_login('nasabah');

$id_nasabah = $_SESSION['id_nasabah'];

// Filter parameters
$filter_type = $_GET['type'] ?? 'all'; // all, setor, tarik
$search = $_GET['search'] ?? '';
$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        // Get all transactions for export (no limit)
        $export_query = "
            (SELECT ts.tanggal_setor AS tanggal, 'Setor Sampah' AS jenis, 
                    js.nama_sampah AS detail, ts.berat, ts.total_harga AS jumlah
             FROM transaksi_setor ts
             JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
             WHERE ts.id_nasabah = ?)
            UNION
            (SELECT tanggal_tarik AS tanggal, 'Tarik Saldo' AS jenis,
                    'Penarikan Saldo' AS detail, NULL AS berat, jumlah_tarik AS jumlah
             FROM transaksi_tarik
             WHERE id_nasabah = ?)
            ORDER BY tanggal DESC
        ";
        
        $stmt_export = $pdo->prepare($export_query);
        $stmt_export->execute([$id_nasabah, $id_nasabah]);
        $export_data = $stmt_export->fetchAll();
        
        // Get profile for filename
        $stmt_profile = $pdo->prepare("SELECT nama_lengkap FROM nasabah WHERE id_nasabah = ?");
        $stmt_profile->execute([$id_nasabah]);
        $profile = $stmt_profile->fetch();
        
        // Set CSV headers
        $filename = 'riwayat_transaksi_' . preg_replace('/[^a-zA-Z0-9]/', '_', $profile['nama_lengkap']) . '_' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Header
        fputcsv($output, ['Tanggal', 'Jenis Transaksi', 'Detail', 'Berat (kg)', 'Jumlah (Rp)'], ';');
        
        // CSV Data
        foreach ($export_data as $row) {
            fputcsv($output, [
                date('d/m/Y H:i', strtotime($row['tanggal'])),
                $row['jenis'],
                $row['detail'],
                $row['berat'] ? number_format($row['berat'], 2, ',', '.') : '-',
                number_format($row['jumlah'], 0, ',', '.')
            ], ';');
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        die("Error exporting data: " . $e->getMessage());
    }
}

try {
    // Get profile info for display
    $stmt_profile = $pdo->prepare("SELECT nama_lengkap, saldo FROM nasabah WHERE id_nasabah = ?");
    $stmt_profile->execute([$id_nasabah]);
    $profile = $stmt_profile->fetch();

    // Build WHERE conditions
    $where_conditions = [];
    $params = [$id_nasabah];

    if ($search) {
        $where_conditions[] = "(js.nama_sampah LIKE ? OR 'setor sampah' LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $where_setor = $where_conditions ? 'AND ' . implode(' AND ', $where_conditions) : '';
    $where_tarik = $search ? "AND 'tarik saldo' LIKE ?" : '';
    if ($search && $where_tarik) $params[] = "%$search%";

    // Combined query based on filter
    if ($filter_type === 'setor') {
        $query = "
            SELECT ts.tanggal_setor AS tanggal, 'Setor Sampah' AS jenis, 
                   js.nama_sampah AS detail, ts.berat, ts.total_harga AS jumlah
            FROM transaksi_setor ts
            JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
            WHERE ts.id_nasabah = ? $where_setor
            ORDER BY ts.tanggal_setor DESC
            LIMIT $limit OFFSET $offset
        ";
    } elseif ($filter_type === 'tarik') {
        $query = "
            SELECT tanggal_tarik AS tanggal, 'Tarik Saldo' AS jenis,
                   'Penarikan Saldo' AS detail, NULL AS berat, jumlah_tarik AS jumlah
            FROM transaksi_tarik
            WHERE id_nasabah = ? $where_tarik
            ORDER BY tanggal_tarik DESC
            LIMIT $limit OFFSET $offset
        ";
    } else {
        $query = "
            (SELECT ts.tanggal_setor AS tanggal, 'Setor Sampah' AS jenis, 
                    js.nama_sampah AS detail, ts.berat, ts.total_harga AS jumlah
             FROM transaksi_setor ts
             JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
             WHERE ts.id_nasabah = ? $where_setor)
            UNION
            (SELECT tanggal_tarik AS tanggal, 'Tarik Saldo' AS jenis,
                    'Penarikan Saldo' AS detail, NULL AS berat, jumlah_tarik AS jumlah
             FROM transaksi_tarik
             WHERE id_nasabah = ? $where_tarik)
            ORDER BY tanggal DESC
            LIMIT $limit OFFSET $offset
        ";
        if ($filter_type === 'all') {
            $params = array_merge([$id_nasabah], array_slice($params, 1), [$id_nasabah], 
                                $search ? ["%$search%"] : []);
        }
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Get statistics
    $stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM transaksi_setor WHERE id_nasabah = ?) as total_setor,
            (SELECT COUNT(*) FROM transaksi_tarik WHERE id_nasabah = ?) as total_tarik,
            (SELECT COALESCE(SUM(total_harga), 0) FROM transaksi_setor WHERE id_nasabah = ?) as total_earned,
            (SELECT COALESCE(SUM(jumlah_tarik), 0) FROM transaksi_tarik WHERE id_nasabah = ?) as total_withdrawn
    ";
    $stmt_stats = $pdo->prepare($stats_query);
    $stmt_stats->execute([$id_nasabah, $id_nasabah, $id_nasabah, $id_nasabah]);
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Bank Sampah Bahrul Ulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ...existing CSS styles... */
        :root {
            --primary: #0d6efd;
            --success: #20c997;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.08) 0%, transparent 50%);
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
            border-color: rgba(255,255,255,0.3);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.3);
            color: #ffffff;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            background: linear-gradient(45deg, #667eea, #764ba2, #11998e);
            border-radius: 20px;
            z-index: -1;
            opacity: 0.3;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.2rem;
            color: white;
        }

        .stat-icon.setor { background: var(--gradient-success); }
        .stat-icon.tarik { background: var(--gradient-danger); }
        .stat-icon.earned { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.withdrawn { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        /* Filter Section */
        .filter-section {
            background: rgba(255,255,255,0.95);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .filter-btn {
            border: 2px solid rgba(102,126,234,0.3);
            background: transparent;
            color: #667eea;
            border-radius: 25px;
            padding: 0.5rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .filter-btn:hover, .filter-btn.active {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        /* Transaction Timeline */
        .transaction-timeline {
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .timeline-item {
            display: flex;
            align-items: center;
            padding: 1.2rem;
            border-radius: 16px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: rgba(248,250,252,0.8);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .timeline-item:hover {
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            background: white;
        }

        .timeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
            font-size: 1.1rem;
        }

        .timeline-icon.setor {
            background: var(--gradient-success);
        }

        .timeline-icon.tarik {
            background: var(--gradient-danger);
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 0.2rem 0;
        }

        .timeline-detail {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }

        .timeline-amount {
            text-align: right;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .timeline-date {
            color: #9ca3af;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        .amount-positive {
            color: #059669;
        }

        .amount-negative {
            color: #dc2626;
        }

        /* Search Box */
        .search-box {
            position: relative;
        }

        .search-input {
            border: 2px solid rgba(102,126,234,0.2);
            border-radius: 12px;
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            background: rgba(255,255,255,0.9);
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title { font-size: 2rem; }
            .timeline-item { padding: 1rem; }
            .timeline-icon { width: 40px; height: 40px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        /* Animation */
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Export Button */
        .btn-export {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(240,147,251,0.4);
            color: white;
        }
    </style>
</head>
<body>
    <!-- ...existing HTML structure... -->
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
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="riwayat.php">
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
            <!-- Page Header -->
            <div class="page-header fade-in">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h1 class="page-title">Riwayat Transaksi</h1>
                        <p class="mb-0 opacity-90">
                            <i class="fas fa-user me-2"></i>
                            <?= htmlspecialchars($profile['nama_lengkap']) ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <button class="btn btn-export" onclick="exportData()">
                            <i class="fas fa-download me-2"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid fade-in">
                <div class="stat-card">
                    <div class="stat-icon setor">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <h3 class="mb-1"><?= $stats['total_setor'] ?></h3>
                    <p class="text-muted mb-0">Total Setoran</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon tarik">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <h3 class="mb-1"><?= $stats['total_tarik'] ?></h3>
                    <p class="text-muted mb-0">Total Penarikan</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon earned">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h3 class="mb-1"><?= format_rupiah($stats['total_earned']) ?></h3>
                    <p class="text-muted mb-0">Total Diterima</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon withdrawn">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h3 class="mb-1"><?= format_rupiah($stats['total_withdrawn']) ?></h3>
                    <p class="text-muted mb-0">Total Ditarik</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section fade-in">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Cari Transaksi</label>
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" class="form-control search-input" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Cari berdasarkan jenis sampah atau transaksi...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filter Jenis</label>
                        <div>
                            <button type="submit" name="type" value="all" 
                                    class="filter-btn <?= $filter_type === 'all' ? 'active' : '' ?>">
                                Semua
                            </button>
                            <button type="submit" name="type" value="setor" 
                                    class="filter-btn <?= $filter_type === 'setor' ? 'active' : '' ?>">
                                Setoran
                            </button>
                            <button type="submit" name="type" value="tarik" 
                                    class="filter-btn <?= $filter_type === 'tarik' ? 'active' : '' ?>">
                                Penarikan
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Transaction Timeline -->
            <div class="transaction-timeline fade-in">
                <h5 class="mb-4">
                    <i class="fas fa-clock me-2"></i>
                    Timeline Transaksi
                </h5>
                
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon <?= strtolower(explode(' ', $transaction['jenis'])[0]) ?>">
                            <i class="fas fa-<?= $transaction['jenis'] === 'Setor Sampah' ? 'arrow-down' : 'arrow-up' ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h6 class="timeline-title"><?= htmlspecialchars($transaction['jenis']) ?></h6>
                            <p class="timeline-detail">
                                <?= htmlspecialchars($transaction['detail']) ?>
                                <?php if ($transaction['berat']): ?>
                                    <span class="badge bg-light text-dark ms-2">
                                        <?= number_format($transaction['berat'], 2) ?> kg
                                    </span>
                                <?php endif; ?>
                            </p>
                            <div class="timeline-date">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?= date('d F Y, H:i', strtotime($transaction['tanggal'])) ?>
                            </div>
                        </div>
                        <div class="timeline-amount">
                            <div class="<?= $transaction['jenis'] === 'Setor Sampah' ? 'amount-positive' : 'amount-negative' ?>">
                                <?= $transaction['jenis'] === 'Setor Sampah' ? '+' : '-' ?>
                                <?= format_rupiah($transaction['jumlah']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox empty-icon"></i>
                        <h5>Tidak ada transaksi</h5>
                        <p>Belum ada transaksi yang sesuai dengan filter Anda.</p>
                        <a href="riwayat.php" class="btn btn-primary">
                            <i class="fas fa-refresh me-2"></i>
                            Lihat Semua Transaksi
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fade in animation
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

        // Auto show first elements
        setTimeout(() => {
            document.querySelectorAll('.fade-in').forEach((el, index) => {
                if (index < 2) el.classList.add('visible');
            });
        }, 100);

        // Export function - Fixed to properly trigger CSV download
        function exportData() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('export', 'csv');
            
            // Navigate to the export URL
            window.location.href = '?' + urlParams.toString();
        }

        // Handle filter button clicks
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'type';
                typeInput.value = this.getAttribute('name') === 'type' ? this.value : this.textContent.toLowerCase().trim();
                form.appendChild(typeInput);
                form.submit();
            });
        });
    </script>
</body>
</html>