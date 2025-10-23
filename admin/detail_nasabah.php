<?php
// /admin/detail_nasabah.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

// Ambil ID nasabah dari parameter URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID nasabah tidak valid";
    header("Location: nasabah.php");
    exit;
}

$id_nasabah = $_GET['id'];

// Ambil data nasabah
try {
    $stmt = $pdo->prepare("
        SELECT n.*, u.username, k.nama_klaster
        FROM nasabah n
        LEFT JOIN users u ON n.id_user = u.id_user
        LEFT JOIN klaster_info k ON n.id_klaster = k.id_klaster
        WHERE n.id_nasabah = ?
    ");
    $stmt->execute([$id_nasabah]);
    $nasabah = $stmt->fetch();
    
    // Jika nasabah tidak ditemukan
    if (!$nasabah) {
        $_SESSION['error'] = "Nasabah tidak ditemukan";
        header("Location: nasabah.php");
        exit;
    }
    
    // Ambil data transaksi setoran nasabah
    $stmt = $pdo->prepare("
        SELECT 
            ts.tanggal_setor, 
            js.nama_sampah, 
            ts.berat, 
            js.harga_beli AS harga_per_kg, 
            ts.total_harga, 
            u.username AS admin_pencatat
        FROM transaksi_setor ts
        JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
        JOIN users u ON ts.dicatat_oleh = u.id_user
        WHERE ts.id_nasabah = ?
        ORDER BY ts.tanggal_setor DESC
    ");
    $stmt->execute([$id_nasabah]);
    $transaksi_setor = $stmt->fetchAll();
    
    // Ambil data transaksi penarikan nasabah
    $stmt_tarik = $pdo->prepare("
        SELECT tt.*, u.username as admin_pencatat
        FROM transaksi_tarik tt
        LEFT JOIN users u ON tt.dicatat_oleh = u.id_user
        WHERE tt.id_nasabah = ?
        ORDER BY tt.tanggal_tarik DESC
    ");
    $stmt_tarik->execute([$id_nasabah]);
    $transaksi_tarik = $stmt_tarik->fetchAll();

    // Hitung total statistik
    $stmt_stats = $pdo->prepare("
        SELECT 
            COALESCE(SUM(ts.berat), 0) as total_berat,
            COUNT(ts.id_setor) as jumlah_setor
        FROM transaksi_setor ts
        WHERE ts.id_nasabah = ?
    ");
    $stmt_stats->execute([$id_nasabah]);
    $stats = $stmt_stats->fetch();
    
    // Ambil jenis sampah yang paling banyak disetor
    $stmt_top_sampah = $pdo->prepare("
        SELECT js.nama_sampah, SUM(ts.berat) as total_berat
        FROM transaksi_setor ts
        JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
        WHERE ts.id_nasabah = ?
        GROUP BY js.id_sampah
        ORDER BY total_berat DESC
        LIMIT 1
    ");
    $stmt_top_sampah->execute([$id_nasabah]);
    $top_sampah = $stmt_top_sampah->fetch();
    
    // Ambil data untuk grafik setoran per bulan (6 bulan terakhir)
    $stmt_grafik = $pdo->prepare("
        SELECT 
            DATE_FORMAT(tanggal_setor, '%Y-%m') as bulan,
            SUM(berat) as total_berat,
            SUM(total_harga) as total_harga
        FROM transaksi_setor
        WHERE id_nasabah = ? AND tanggal_setor >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(tanggal_setor, '%Y-%m')
        ORDER BY bulan
    ");
    $stmt_grafik->execute([$id_nasabah]);
    $grafik_data = $stmt_grafik->fetchAll();
    
    // Format data untuk Chart.js
    $labels = [];
    $data_berat = [];
    $data_nominal = [];
    
    foreach ($grafik_data as $data) {
        $bulan = date('M Y', strtotime($data['bulan'] . '-01'));
        $labels[] = $bulan;
        $data_berat[] = $data['total_berat'];
        $data_nominal[] = $data['total_harga'];
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: nasabah.php");
    exit;
}

// Function untuk format rupiah (jika belum ada)
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return "Rp " . number_format($angka, 0, ',', '.');
    }
}

// Function untuk format tanggal
function format_tanggal($tanggal) {
    return date('d M Y H:i', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Nasabah - Bank Sampah Bahrul Ulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2e8b57;  /* Sea Green */
            --secondary: #3cb371; /* Medium Sea Green */
            --accent: #f0e68c;    /* Khaki */
            --light: #f5f5f5;     /* White Smoke */
            --dark: #2c3e50;      /* Dark Slate Gray */
        }
        
        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            position: relative;
            min-height: 100vh;
        }
        
        /* Sidebar styling */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            padding-top: 20px;
            background-color: var(--dark);
            color: white;
            z-index: 1030;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #6c757d #343a40;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #343a40;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background-color: #6c757d;
            border-radius: 20px;
        }
        
        .sidebar .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .sidebar .brand i {
            margin-right: 10px;
            font-size: 1.8rem;
            color: var(--accent);
        }
        
        .sidebar .nav-link {
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            border-radius: 5px;
            margin: 2px 10px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: var(--primary);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link.active i {
            color: var(--accent);
        }
        
        .sidebar .nav-category {
            font-size: 0.7rem;
            text-transform: uppercase;
            font-weight: 600;
            color: rgba(255,255,255,0.4);
            letter-spacing: 1px;
            padding: 20px 20px 5px;
        }
        
        /* Content area styling */
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Header styling */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .page-header h1 {
            font-weight: 600;
            color: var(--dark);
            font-size: 1.8rem;
            margin-bottom: 0;
        }
        
        /* Profile header */
        .profile-header {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-left: 5px solid var(--primary);
        }
        
        .profile-header .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .profile-header .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .profile-meta .meta-item {
            display: flex;
            align-items: center;
        }
        
        .profile-meta .meta-item i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        .profile-header .saldo-display {
            background-color: rgba(46, 139, 87, 0.1);
            border-radius: 8px;
            padding: 10px 15px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .profile-header .saldo-display .label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .profile-header .saldo-display .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        /* Card styling */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 15px 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-header i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Stats cards */
        .stats-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            padding: 20px;
            flex: 1;
            min-width: 200px;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            display: flex;
            align-items: center;
        }
        
        .stat-card .stat-label i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        /* Table styling */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            border-top: none;
            white-space: nowrap;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(46, 139, 87, 0.03);
        }
        
        /* Badge styling */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
            border-radius: 6px;
        }
        
        .badge.bg-success {
            background-color: rgba(60, 179, 113, 0.1) !important;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }
        
        .badge.bg-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        
        .badge.bg-info {
            background-color: rgba(23, 162, 184, 0.1) !important;
            color: #17a2b8;
            border: 1px solid #17a2b8;
        }
        
        .badge.bg-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
            color: #d39e00;
            border: 1px solid #d39e00;
        }
        
        /* Chart container */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Button styling */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #267349;
            border-color: #267349;
        }
        
        /* Footer */
        footer {
            background-color: white;
            padding: 15px;
            text-align: center;
            margin-top: 20px;
            border-radius: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        
        /* No data message */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        
        /* Responsive design */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .brand-text,
            .sidebar .nav-text,
            .sidebar .nav-category {
                display: none;
            }
            
            .sidebar .nav-link {
                justify-content: center;
                padding: 15px 5px;
            }
            
            .sidebar .nav-link i {
                margin-right: 0;
                font-size: 1.25rem;
            }
            
            .content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                flex-direction: column;
            }
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-header .profile-name {
                font-size: 1.5rem;
            }
            
            .tab-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="index.php" class="brand">
            <i class="fas fa-recycle"></i>
            <span class="brand-text">Bank Sampah</span>
        </a>
        
        <div class="nav-category">Dashboard</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
        </ul>
        
        <div class="nav-category">Transaksi</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="transaksi_setor.php">
                    <i class="fas fa-download"></i>
                    <span class="nav-text">Setor Sampah</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="transaksi_tarik.php">
                    <i class="fas fa-upload"></i>
                    <span class="nav-text">Tarik Saldo</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="penjualan.php">
                    <i class="fas fa-truck-loading"></i>
                    <span class="nav-text">Penjualan</span>
                </a>
            </li>
        </ul>
        
        <div class="nav-category">Data Master</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="nasabah.php">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">Nasabah</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="sampah.php">
                    <i class="fas fa-trash"></i>
                    <span class="nav-text">Jenis Sampah</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="stok.php">
                    <i class="fas fa-warehouse"></i>
                    <span class="nav-text">Stok Gudang</span>
                </a>
            </li>
        </ul>
        
        <div class="nav-category">Analisis & Laporan</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="laporan.php">
                    <i class="fas fa-file-alt"></i>
                    <span class="nav-text">Laporan</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="clustering.php">
                    <i class="fas fa-project-diagram"></i>
                    <span class="nav-text">Clustering</span>
                </a>
            </li>
        </ul>
        
        <div class="nav-category">Akun</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="page-header">
                <h1><i class="fas fa-user text-primary me-2"></i> Detail Nasabah</h1>
                <div>
                    <a href="nasabah.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Kembali
                    </a>
                    <a href="transaksi_setor.php?nasabah=<?= $nasabah['id_nasabah'] ?>" class="btn btn-primary ms-2">
                        <i class="fas fa-plus me-1"></i> Transaksi Baru
                    </a>
                </div>
            </div>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row">
                    <div class="col-md-9">
                        <h2 class="profile-name"><?= htmlspecialchars($nasabah['nama_lengkap']) ?></h2>
                        <div class="profile-meta">
                            <div class="meta-item">
                                <i class="fas fa-id-card"></i>
                                <span><?= htmlspecialchars($nasabah['nis'] ?: 'Tidak ada NIS') ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-school"></i>
                                <span>Kelas <?= htmlspecialchars($nasabah['kelas']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-user-shield"></i>
                                <span>Username: <?= htmlspecialchars($nasabah['username']) ?></span>
                            </div>
                            <?php if ($nasabah['nama_klaster']): ?>
                            <div class="meta-item">
                                <i class="fas fa-layer-group"></i>
                                <span>Klaster: <span class="badge bg-info"><?= htmlspecialchars($nasabah['nama_klaster']) ?></span></span>
                            </div>
                            <?php else: ?>
                            <div class="meta-item">
                                <i class="fas fa-layer-group"></i>
                                <span>Klaster: <span class="badge bg-warning">Belum</span></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <div class="saldo-display">
                            <div class="label">Saldo</div>
                            <div class="value"><?= format_rupiah($nasabah['saldo']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($stats['jumlah_setor']) ?></div>
                    <div class="stat-label"><i class="fas fa-exchange-alt"></i> Total Transaksi Setor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($stats['total_berat'], 1) ?> kg</div>
                    <div class="stat-label"><i class="fas fa-weight"></i> Total Sampah Disetor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $top_sampah ? htmlspecialchars($top_sampah['nama_sampah']) : 'Belum ada' ?></div>
                    <div class="stat-label"><i class="fas fa-trophy"></i> Jenis Sampah Terbanyak</div>
                </div>
            </div>
            
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs mb-3" id="nasabahTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="setor-tab" data-bs-toggle="tab" data-bs-target="#setor-tab-pane" type="button" role="tab" aria-controls="setor-tab-pane" aria-selected="true">
                        <i class="fas fa-download me-1"></i> Riwayat Setoran
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tarik-tab" data-bs-toggle="tab" data-bs-target="#tarik-tab-pane" type="button" role="tab" aria-controls="tarik-tab-pane" aria-selected="false">
                        <i class="fas fa-upload me-1"></i> Riwayat Penarikan
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="grafik-tab" data-bs-toggle="tab" data-bs-target="#grafik-tab-pane" type="button" role="tab" aria-controls="grafik-tab-pane" aria-selected="false">
                        <i class="fas fa-chart-bar me-1"></i> Grafik Aktivitas
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="nasabahTabContent">
                <!-- Tab Riwayat Setoran -->
                <div class="tab-pane fade show active" id="setor-tab-pane" role="tabpanel" aria-labelledby="setor-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Riwayat Setoran Sampah
                            <a href="transaksi_setor.php?filter=nasabah&id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-sm btn-outline-primary float-end">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($transaksi_setor) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis Sampah</th>
                                            <th>Berat</th>
                                            <th>Harga/kg</th>
                                            <th>Total</th>
                                            <th>Dicatat oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($transaksi_setor as $ts): ?>
                                        <tr>
                                            <td><?= format_tanggal($ts['tanggal_setor']) ?></td>
                                            <td><?= htmlspecialchars($ts['nama_sampah']) ?></td>
                                            <td><?= number_format($ts['berat'], 1) ?> kg</td>
                                            <td><?= isset($ts['harga_per_kg']) ? format_rupiah($ts['harga_per_kg']) : 'N/A' ?></td>
                                            <td><?= format_rupiah($ts['total_harga']) ?></td>
                                            <td><?= htmlspecialchars($ts['admin_pencatat']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-info-circle"></i>
                                <p>Nasabah belum memiliki riwayat setoran sampah</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Riwayat Penarikan -->
                <div class="tab-pane fade" id="tarik-tab-pane" role="tabpanel" aria-labelledby="tarik-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Riwayat Penarikan Saldo
                            <a href="transaksi_tarik.php?filter=nasabah&id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-sm btn-outline-primary float-end">
                                Lihat Semua
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($transaksi_tarik) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jumlah Penarikan</th>
                                            <th>Catatan</th>
                                            <th>Dicatat oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($transaksi_tarik as $tt): ?>
                                        <tr>
                                            <td><?= format_tanggal($tt['tanggal_tarik']) ?></td>
                                            <td><?= format_rupiah($tt['jumlah_tarik']) ?></td>
                                            <td><?= htmlspecialchars($tt['catatan'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($tt['admin_pencatat']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-info-circle"></i>
                                <p>Nasabah belum memiliki riwayat penarikan saldo</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Grafik Aktivitas -->
                <div class="tab-pane fade" id="grafik-tab-pane" role="tabpanel" aria-labelledby="grafik-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-bar"></i> Grafik Setoran 6 Bulan Terakhir
                        </div>
                        <div class="card-body">
                            <?php if (count($labels) > 0): ?>
                            <div class="chart-container">
                                <canvas id="chartSetoran"></canvas>
                            </div>
                            <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-chart-line"></i>
                                <p>Belum ada data setoran yang cukup untuk ditampilkan dalam grafik</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Buttons at bottom -->
            <div class="mt-4 d-flex gap-2">
                <a href="transaksi_setor.php?nasabah=<?= $nasabah['id_nasabah'] ?>" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i> Catat Setoran Baru
                </a>
                <a href="transaksi_tarik.php?nasabah=<?= $nasabah['id_nasabah'] ?>" class="btn btn-danger">
                    <i class="fas fa-money-bill-wave me-2"></i> Catat Penarikan
                </a>
                <a href="laporan.php?filter=nasabah&id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-info text-white">
                    <i class="fas fa-file-export me-2"></i> Laporan Lengkap
                </a>
            </div>
            
            <footer>
                <p class="mb-0">&copy; <?= date('Y') ?> Bank Sampah Bahrul Ulum</p>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($labels) > 0): ?>
            // Chart Setoran
            const ctxSetoran = document.getElementById('chartSetoran').getContext('2d');
            
            new Chart(ctxSetoran, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels) ?>,
                    datasets: [
                        {
                            label: 'Berat Sampah (kg)',
                            data: <?= json_encode($data_berat) ?>,
                            backgroundColor: 'rgba(46, 139, 87, 0.6)',
                            borderColor: 'rgba(46, 139, 87, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Nominal (Rp)',
                            data: <?= json_encode($data_nominal) ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.6)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1,
                            type: 'line',
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Aktivitas Setoran Sampah - 6 Bulan Terakhir',
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.y + ' kg';
                                    } else {
                                        label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Berat (kg)'
                            },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Nominal (Rp)'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>