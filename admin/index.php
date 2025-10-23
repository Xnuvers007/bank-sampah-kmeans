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
} catch (Exception $e) {
    $total_nasabah = 0; $total_saldo = 0; $total_berat = 0;
}

// --- 2. Ambil data untuk chart (7 hari terakhir) ---
$chart_labels = [];
$chart_data = [];
$today = date('Y-m-d');
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) = ?");
    $stmt->execute([$date]);
    $chart_data[] = $stmt->fetchColumn();
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
    <style>
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; padding-top: 20px; background-color: #343a40; color: white; }
        .sidebar .nav-link { color: #ccc; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background-color: #495057; }
        .content { margin-left: 260px; padding: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-center mb-4">Bank Sampah BU</h4>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link active" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="nasabah.php"><i class="fas fa-users"></i> Data Nasabah</a></li>
            <li class="nav-item"><a class="nav-link" href="sampah.php"><i class="fas fa-trash"></i> Data Sampah</a></li>
            <li class="nav-item"><a class="nav-link" href="transaksi_setor.php"><i class="fas fa-download"></i> Setor Sampah</a></li>
            <li class="nav-item"><a class="nav-link" href="transaksi_tarik.php"><i class="fas fa-upload"></i> Tarik Saldo</a></li>
            <hr class="text-secondary">
            <li class="nav-item"><a class="nav-link" href="penjualan.php"><i class="fas fa-truck-loading"></i> Penjualan Sampah</a></li>
            <li class="nav-item"><a class="nav-link" href="stok.php"><i class="fas fa-warehouse"></i> Stok Gudang</a></li>
            <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="fas fa-file-alt"></i> Laporan</a></li>
            <li class="nav-item"><a class="nav-link" href="clustering.php"><i class="fas fa-project-diagram"></i> Clustering K-Means</a></li>
            <hr class="text-secondary">
            <li class="nav-item mt-auto"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mt-4">Dashboard Admin</h1>
            <p>Selamat datang, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!</p>

            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">Total Nasabah
                            <h3 class="display-6"><?= $total_nasabah ?></h3> 
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">Total Saldo Nasabah
                            <h3 class="display-6"><?= format_rupiah($total_saldo) ?></h3> 
                        </div>
                    </div>
                </div>
                 <div class="col-xl-3 col-md-6">
                    <div class="card bg-info text-white mb-4">
                        <div class="card-body">Total Sampah Masuk
                            <h3 class="display-6"><?= number_format($total_berat, 2) ?> kg</h3> 
                        </div>
                    </div>
                </div>
                </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-chart-bar"></i> Setoran Sampah (kg) - 7 Hari Terakhir</div>
                <div class="card-body">
                    <canvas id="myChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Data chart sekarang dinamis dari PHP
        const ctx = document.getElementById('myChart');
        new Chart(ctx, {
            type: 'line', // Ganti jadi 'line' agar lebih bagus
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Setoran Sampah (kg)',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>