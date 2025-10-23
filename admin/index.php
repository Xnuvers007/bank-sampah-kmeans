<?php
// /admin/index.php
require '../config/db.php';
require '../config/functions.php';

require_login('admin');

// --- 1. Ambil data statistik ---
try {
    $total_nasabah = $pdo->query("SELECT COUNT(*) FROM nasabah")->fetchColumn();
    $total_saldo = $pdo->query("SELECT SUM(saldo) FROM nasabah")->fetchColumn();
    $total_berat = $pdo->query("SELECT SUM(berat) FROM transaksi_setor")->fetchColumn();
    $total_transaksi = $pdo->query("SELECT COUNT(*) FROM transaksi_setor")->fetchColumn();
    
    // Data untuk distribusi jenis sampah (pie chart)
    $stmt_jenis = $pdo->query("
        SELECT js.nama_sampah, COALESCE(SUM(ts.berat), 0) as total_berat 
        FROM jenis_sampah js
        LEFT JOIN transaksi_setor ts ON js.id_sampah = ts.id_sampah
        GROUP BY js.id_sampah
        ORDER BY total_berat DESC
        LIMIT 5
    ");
    $jenis_sampah_labels = [];
    $jenis_sampah_data = [];
    
    while ($row = $stmt_jenis->fetch()) {
        $jenis_sampah_labels[] = $row['nama_sampah'];
        $jenis_sampah_data[] = $row['total_berat'];
    }
    
    // Data transaksi terbaru
    $stmt_transaksi = $pdo->query("
        SELECT ts.tanggal_setor, n.nama_lengkap, n.kelas, js.nama_sampah, ts.berat, ts.total_harga
        FROM transaksi_setor ts
        JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
        JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
        ORDER BY ts.tanggal_setor DESC
        LIMIT 5
    ");
    $transaksi_terbaru = $stmt_transaksi->fetchAll();
    
    // Data nasabah teraktif
    $stmt_nasabah = $pdo->query("
        SELECT n.nama_lengkap, n.kelas, COUNT(ts.id_setor) as jumlah_transaksi, SUM(ts.berat) as total_berat
        FROM nasabah n
        LEFT JOIN transaksi_setor ts ON n.id_nasabah = ts.id_nasabah
        GROUP BY n.id_nasabah
        ORDER BY jumlah_transaksi DESC
        LIMIT 5
    ");
    $nasabah_teraktif = $stmt_nasabah->fetchAll();
    
} catch (Exception $e) {
    $total_nasabah = 0; $total_saldo = 0; $total_berat = 0; $total_transaksi = 0;
}

// --- 2. Ambil data untuk chart (7 hari terakhir) ---
$chart_labels = [];
$chart_data = [];
$chart_data_transaksi = [];
$today = date('Y-m-d');
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) = ?");
    $stmt->execute([$date]);
    $chart_data[] = $stmt->fetchColumn();
    
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM transaksi_setor WHERE DATE(tanggal_setor) = ?");
    $stmt2->execute([$date]);
    $chart_data_transaksi[] = $stmt2->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Bank Sampah Bahrul Ulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/index.css">
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
                <a class="nav-link active" href="index.php">
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
                <a class="nav-link" href="nasabah.php">
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
        
        <div class="nav-category mt-4">Akun</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Content area -->
    <div class="content">
        <!-- Header -->
        <div class="page-header">
            <h1>Dashboard</h1>
            <div class="user-dropdown dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                    <li>
                        <div class="user-info">
                            <h6 class="mb-0"><?= htmlspecialchars($_SESSION['username']) ?></h6>
                            <small class="text-muted">Administrator</small>
                        </div>
                    </li>
                    <li><a class="dropdown-item" href="profil.php"><i class="fas fa-id-card me-2"></i> Profil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Welcome Message -->
        <div class="welcome-message">
            <div>
                <p>Selamat datang, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</p>
                <small class="text-muted">Akses dashboard untuk melihat statistik dan aktivitas terbaru Bank Sampah Bahrul Ulum.</small>
            </div>
            <?php date_default_timezone_set('Asia/Jakarta'); ?>
            <div class="date">
                <i class="fas fa-calendar-alt me-1"></i> <?= date('l, d F Y') ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($total_nasabah) ?></h3>
                        <p class="stat-label">Total Nasabah</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= format_rupiah($total_saldo) ?></h3>
                        <p class="stat-label">Total Saldo Nasabah</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-info">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($total_berat, 1) ?> kg</h3>
                        <p class="stat-label">Total Sampah Masuk</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($total_transaksi) ?></h3>
                        <p class="stat-label">Total Transaksi</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Line Chart - Setoran 7 Hari -->
            <div class="col-xl-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Setoran Sampah - 7 Hari Terakhir
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="setoranChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pie Chart - Distribusi Jenis Sampah -->
            <div class="col-xl-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Distribusi Jenis Sampah
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="jenisSampahChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row - Tables -->
        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-xl-7 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Transaksi Terbaru
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nasabah</th>
                                        <th>Kelas</th>
                                        <th>Jenis Sampah</th>
                                        <th>Berat</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_terbaru as $tr): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($tr['tanggal_setor'])) ?></td>
                                        <td><?= htmlspecialchars($tr['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($tr['kelas']) ?></td>
                                        <td><?= htmlspecialchars($tr['nama_sampah']) ?></td>
                                        <td><?= number_format($tr['berat'], 1) ?> kg</td>
                                        <td><?= format_rupiah($tr['total_harga']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($transaksi_terbaru)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Belum ada transaksi</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="transaksi_setor.php" class="btn btn-sm btn-primary">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- Top Contributors -->
            <div class="col-xl-5 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-medal"></i> Nasabah Teraktif
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nasabah</th>
                                        <th>Kelas</th>
                                        <th>Transaksi</th>
                                        <th>Total Berat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nasabah_teraktif as $na): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($na['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($na['kelas']) ?></td>
                                        <td><?= $na['jumlah_transaksi'] ?></td>
                                        <td><?= number_format($na['total_berat'], 1) ?> kg</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($nasabah_teraktif)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada data nasabah</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <a href="nasabah.php" class="btn btn-sm btn-primary">Lihat Semua <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-bolt"></i> Aksi Cepat
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <a href="transaksi_setor.php?action=new" class="btn btn-success w-100 p-3">
                            <i class="fas fa-plus-circle mb-2 d-block" style="font-size: 2rem;"></i>
                            Setor Sampah Baru
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <a href="transaksi_tarik.php?action=new" class="btn btn-danger w-100 p-3">
                            <i class="fas fa-money-bill-wave mb-2 d-block" style="font-size: 2rem;"></i>
                            Tarik Saldo
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                        <a href="nasabah.php?action=new" class="btn btn-primary w-100 p-3">
                            <i class="fas fa-user-plus mb-2 d-block" style="font-size: 2rem;"></i>
                            Tambah Nasabah
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <a href="laporan.php" class="btn btn-info w-100 p-3 text-white">
                            <i class="fas fa-file-export mb-2 d-block" style="font-size: 2rem;"></i>
                            Laporan
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer>
            <p class="mb-0">&copy; <?= date('Y') ?> Bank Sampah Bahrul Ulum. Dibuat dengan <i class="fas fa-heart text-danger"></i></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Chart 1: Setoran 7 Hari Terakhir ---
            const setoranCtx = document.getElementById('setoranChart').getContext('2d');
            new Chart(setoranCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [
                        {
                            label: 'Berat Sampah (kg)',
                            data: <?= json_encode($chart_data) ?>,
                            backgroundColor: 'rgba(46, 139, 87, 0.1)',
                            borderColor: 'rgba(46, 139, 87, 1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Jumlah Transaksi',
                            data: <?= json_encode($chart_data_transaksi) ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
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
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            backgroundColor: 'rgba(255, 255, 255, 0.8)',
                            titleColor: '#333',
                            bodyColor: '#666',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            usePointStyle: true,
                            boxPadding: 6,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.y + ' kg';
                                    } else {
                                        label += context.parsed.y + ' transaksi';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Berat (kg)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Transaksi'
                            }
                        }
                    }
                }
            });

            // --- Chart 2: Distribusi Jenis Sampah ---
            const jenisSampahCtx = document.getElementById('jenisSampahChart').getContext('2d');
            new Chart(jenisSampahCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($jenis_sampah_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($jenis_sampah_data) ?>,
                        backgroundColor: [
                            'rgba(46, 139, 87, 0.8)',
                            'rgba(60, 179, 113, 0.8)',
                            'rgba(46, 204, 113, 0.8)',
                            'rgba(39, 174, 96, 0.8)',
                            'rgba(72, 201, 176, 0.8)'
                        ],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) label += ': ';
                                    label += context.formattedValue + ' kg';
                                    return label;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        });
    </script>
</body>
</html>