<?php
// filepath: /C:/xampp/htdocs/bank_sampah/admin/stok.php
require '../config/db.php';
require '../config/functions.php';

require_login('admin');

try {
    // Ambil semua jenis sampah
    $sampah_list = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_sampah")->fetchAll();
    
    $stok_data = [];
    $total_value_in = 0;
    $total_value_out = 0;
    $total_items = 0;
    
    foreach ($sampah_list as $sampah) {
        $id_s = $sampah['id_sampah'];
        
        // Hitung total masuk
        $stmt_in = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE id_sampah = ?");
        $stmt_in->execute([$id_s]);
        $total_masuk = $stmt_in->fetchColumn();
        
        // Hitung total keluar (dijual)
        $stmt_out = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_jual WHERE id_sampah = ?");
        $stmt_out->execute([$id_s]);
        $total_keluar = $stmt_out->fetchColumn();
        
        $stok_akhir = $total_masuk - $total_keluar;
        
        // Hitung nilai uang
        $nilai_masuk = $total_masuk * $sampah['harga_beli'];
        $nilai_keluar = $total_keluar * $sampah['harga_beli'];
        $nilai_stok = $stok_akhir * $sampah['harga_beli'];
        
        $total_value_in += $nilai_masuk;
        $total_value_out += $nilai_keluar;
        $total_items += $stok_akhir;
        
        $stok_data[] = [
            'nama_sampah' => $sampah['nama_sampah'],
            'satuan' => $sampah['satuan'],
            'harga_beli' => $sampah['harga_beli'],
            'total_masuk' => $total_masuk,
            'total_keluar' => $total_keluar,
            'stok_akhir' => $stok_akhir,
            'nilai_masuk' => $nilai_masuk,
            'nilai_keluar' => $nilai_keluar,
            'nilai_stok' => $nilai_stok
        ];
    }
    
    $total_stok_value = $total_value_in - $total_value_out;
    
} catch (Exception $e) {
    $error = "Gagal mengambil data stok: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Gudang - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.03)"/><circle cx="80" cy="20" r="2" fill="rgba(255,255,255,0.03)"/><circle cx="20" cy="80" r="2" fill="rgba(255,255,255,0.03)"/><circle cx="80" cy="80" r="2" fill="rgba(255,255,255,0.03)"/></svg>') repeat;
            z-index: -1;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-20px) translateY(-20px); }
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.8s ease-out;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(30deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(30deg); }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: slideInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.6s;
        }

        .stat-card:hover .stat-icon::before {
            left: 100%;
        }

        .icon-warehouse {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .icon-income {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.4);
        }

        .icon-outcome {
            background: linear-gradient(135deg, #fd746c 0%, #ff9068 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(253, 116, 108, 0.4);
        }

        .icon-value {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(240, 147, 251, 0.4);
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .main-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInUp 0.8s ease-out 0.5s forwards;
            opacity: 0;
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border: none;
            font-weight: 700;
            font-size: 1.3rem;
            position: relative;
        }

        .card-header-custom::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, rgba(255,255,255,0.3), rgba(255,255,255,0.7), rgba(255,255,255,0.3));
        }

        .table-modern {
            margin: 0;
            background: transparent;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            color: #495057;
            font-weight: 700;
            padding: 1.5rem 1rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            position: relative;
            white-space: nowrap;
        }

        .table-modern thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .table-modern tbody tr {
            transition: all 0.3s ease;
            border: none;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
        }

        .table-modern tbody tr:nth-child(odd) {
            background: rgba(248,249,250,0.8);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            transform: scale(1.01);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border: none;
            vertical-align: middle;
            font-weight: 500;
        }

        .waste-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1rem;
        }

        .quantity-badge {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
        }

        .quantity-out {
            background: linear-gradient(135deg, #fd746c 0%, #ff9068 100%);
            box-shadow: 0 4px 15px rgba(253, 116, 108, 0.3);
        }

        .stock-final {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-block;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            text-align: center;
            min-width: 120px;
        }

        .value-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(240, 147, 251, 0.3);
        }

        .unit-badge {
            background: rgba(255,255,255,0.9);
            color: #6c757d;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
            border: 2px solid #e9ecef;
            margin-left: 0.5rem;
        }

        .alert-modern {
            border: none;
            border-radius: 15px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            animation: slideInRight 0.5s ease-out;
            backdrop-filter: blur(10px);
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
            background: linear-gradient(135deg, rgba(248, 215, 218, 0.9) 0%, rgba(245, 198, 203, 0.9) 100%);
            color: #721c24;
            border-left: 6px solid #dc3545;
        }

        .icon-badge {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-right: 1rem;
        }

        .icon-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            margin-left: 1rem;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
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

        @media (max-width: 768px) {
            .page-header {
                text-align: center;
                padding: 2rem 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .icon-badge {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .table-responsive {
                border-radius: 15px;
                overflow: hidden;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-value {
                font-size: 1.8rem;
            }
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .rapi {
            padding-left: 7%;
        }
    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8 rapi">
                        <h1 class="mb-0 display-5">
                            <i class="fas fa-warehouse me-3"></i>
                            Laporan Stok Gudang
                        </h1>
                        <p class="mb-0 mt-2 opacity-75 fs-5">Monitoring real-time inventori sampah dan nilai aset gudang</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <div class="icon-badge icon-header">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <button class="refresh-btn" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-modern">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-warehouse">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-value"><?= count($stok_data) ?></div>
                    <div class="stat-label">Jenis Sampah</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-income">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-value"><?= format_rupiah($total_value_in) ?></div>
                    <div class="stat-label">Nilai Total Masuk</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-outcome">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-value"><?= format_rupiah($total_value_out) ?></div>
                    <div class="stat-label">Nilai Total Keluar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-value">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="stat-value"><?= format_rupiah($total_stok_value) ?></div>
                    <div class="stat-label">Nilai Stok Saat Ini</div>
                </div>
            </div>

            <!-- Main Data Table -->
            <div class="main-card">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-inventory me-2"></i>
                        Detail Stok Sampah Terkumpul
                    </span>
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2 fs-6">
                        <i class="fas fa-clock me-1"></i>
                        <?= date('d M Y, H:i') ?> WIB
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($stok_data) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-tag me-1"></i>Nama Sampah</th>
                                        <th><i class="fas fa-download me-1"></i>Total Masuk</th>
                                        <th><i class="fas fa-upload me-1"></i>Total Keluar</th>
                                        <th><i class="fas fa-warehouse me-1"></i>Stok Akhir</th>
                                        <th><i class="fas fa-money-bill me-1"></i>Nilai Masuk</th>
                                        <th><i class="fas fa-money-bill me-1"></i>Nilai Keluar</th>
                                        <th><i class="fas fa-gem me-1"></i>Nilai Stok</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stok_data as $index => $stok): ?>
                                    <tr style="animation-delay: <?= $index * 0.1 ?>s">
                                        <td>
                                            <div class="waste-name">
                                                <?= htmlspecialchars($stok['nama_sampah']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i>
                                                <?= format_rupiah($stok['harga_beli']) ?>/<?= $stok['satuan'] ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="quantity-badge">
                                                <?= number_format($stok['total_masuk'], 2) ?>
                                            </span>
                                            <span class="unit-badge"><?= $stok['satuan'] ?></span>
                                        </td>
                                        <td>
                                            <span class="quantity-badge quantity-out">
                                                <?= number_format($stok['total_keluar'], 2) ?>
                                            </span>
                                            <span class="unit-badge"><?= $stok['satuan'] ?></span>
                                        </td>
                                        <td>
                                            <span class="stock-final">
                                                <?= number_format($stok['stok_akhir'], 2) ?> <?= $stok['satuan'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="value-badge">
                                                <?= format_rupiah($stok['nilai_masuk']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="value-badge">
                                                <?= format_rupiah($stok['nilai_keluar']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="value-badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                                <?= format_rupiah($stok['nilai_stok']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>Belum Ada Data Stok</h4>
                            <p>Data stok akan muncul setelah ada transaksi setoran sampah</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show loading on page load
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.remove('show');
        });

        // Add loading on refresh
        function showLoading() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.add('show');
        }

        // Auto refresh setiap 5 menit
        setInterval(function() {
            showLoading();
            location.reload();
        }, 300000); // 5 menit

        // Counter animation untuk nilai statistik
        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                
                if (element.textContent.includes('Rp')) {
                    element.textContent = 'Rp ' + current.toLocaleString('id-ID');
                } else {
                    element.textContent = current.toLocaleString('id-ID');
                }
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // Animasi hover untuk table rows
        document.querySelectorAll('.table-modern tbody tr').forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Tooltip untuk badges
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Smooth scroll untuk mobile
        if (window.innerWidth <= 768) {
            document.body.style.scrollBehavior = 'smooth';
        }

        // Auto dismiss alerts jika ada
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 5000);

        console.log('📊 Stok Gudang Dashboard loaded successfully!');
    </script>
</body>
</html>