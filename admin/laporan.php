<?php
// /admin/laporan.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

// Tentukan rentang tanggal default (bulan ini)
$tanggal_mulai = $_GET['start'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['end'] ?? date('Y-m-t');

// --- 1. AMBIL DATA RINGKASAN ---
$query_ringkasan = "
    SELECT 
        (SELECT COALESCE(SUM(total_harga), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) BETWEEN :start1 AND :end1) AS total_setoran,
        (SELECT COALESCE(SUM(jumlah_tarik), 0) FROM transaksi_tarik WHERE DATE(tanggal_tarik) BETWEEN :start2 AND :end2) AS total_tarikan,
        (SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) BETWEEN :start3 AND :end3) AS total_berat
";
$stmt_ringkasan = $pdo->prepare($query_ringkasan);
$stmt_ringkasan->execute([
    'start1' => $tanggal_mulai, 'end1' => $tanggal_akhir,
    'start2' => $tanggal_mulai, 'end2' => $tanggal_akhir,
    'start3' => $tanggal_mulai, 'end3' => $tanggal_akhir,
]);
$ringkasan = $stmt_ringkasan->fetch();

$saldo_akhir_periode = $ringkasan['total_setoran'] - $ringkasan['total_tarikan'];

// --- 2. AMBIL DATA DETAIL SETORAN ---
$stmt_setoran = $pdo->prepare("
    SELECT ts.*, n.nama_lengkap, n.kelas, js.nama_sampah, u.username AS admin_pencatat
    FROM transaksi_setor ts
    JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
    JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
    JOIN users u ON ts.dicatat_oleh = u.id_user
    WHERE DATE(ts.tanggal_setor) BETWEEN :start AND :end
    ORDER BY ts.tanggal_setor DESC
");
$stmt_setoran->execute(['start' => $tanggal_mulai, 'end' => $tanggal_akhir]);
$laporan_setoran = $stmt_setoran->fetchAll();

// --- 3. AMBIL DATA DETAIL TARIKAN ---
$stmt_tarikan = $pdo->prepare("
    SELECT tt.*, n.nama_lengkap, n.kelas, u.username AS admin_pencatat
    FROM transaksi_tarik tt
    JOIN nasabah n ON tt.id_nasabah = n.id_nasabah
    JOIN users u ON tt.dicatat_oleh = u.id_user
    WHERE DATE(tt.tanggal_tarik) BETWEEN :start AND :end
    ORDER BY tt.tanggal_tarik DESC
");
$stmt_tarikan->execute(['start' => $tanggal_mulai, 'end' => $tanggal_akhir]);
$laporan_tarikan = $stmt_tarikan->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 250px; padding-top: 20px; background-color: #343a40; color: white; }
        .sidebar .nav-link { color: #ccc; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background-color: #495057; }
        .content { margin-left: 260px; padding: 20px; }
        
        /* CSS Khusus untuk Halaman Cetak (Print) */
        @media print {
            body {
                background-color: white;
            }
            .sidebar, .filter-card, .btn-print, .nav-tabs {
                display: none !important; /* Sembunyikan sidebar dan form filter saat print */
            }
            .content {
                margin-left: 0;
                padding: 0;
            }
            .card {
                border: none;
                box-shadow: none;
            }
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }
            .tab-pane {
                display: block !important; /* Tampilkan semua tab-pane saat print */
                opacity: 1 !important;
            }
        }
        
        .print-header {
            display: none; /* Sembunyi secara default */
        }
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
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="mt-4">Laporan Transaksi</h1>
                <button class="btn btn-secondary btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
            </div>
            
            <div class="card mb-4 filter-card">
                <div class="card-body">
                    <form method="GET" action="laporan.php" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="start" class="form-label">Dari Tanggal</label>
                            <input type="date" class="form-control" id="start" name="start" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="end" class="form-label">Sampai Tanggal</label>
                            <input type="date" class="form-control" id="end" name="end" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="print-header">
                <h3>Laporan Transaksi Bank Sampah</h3>
                <h5>SD SMP Bahrul Ulum</h5>
                <p>Periode: <?= htmlspecialchars($tanggal_mulai) ?> s/d <?= htmlspecialchars($tanggal_akhir) ?></p>
                <hr>
            </div>
            
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-chart-pie"></i> Ringkasan Periode</div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Total Setoran</h5>
                                    <h3 class_text-success="fw-bold"><?= format_rupiah($ringkasan['total_setoran']) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-danger">Total Penarikan</h5>
                                    <h3 class="text-danger fw-bold"><?= format_rupiah($ringkasan['total_tarikan']) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">Laba/Rugi Periode</h5>
                                    <h3 class="text-primary fw-bold"><?= format_rupiah($saldo_akhir_periode) ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title text-info">Total Sampah Masuk</h5>
                                    <h3 class="text-info fw-bold"><?= number_format($ringkasan['total_berat'], 2) ?> kg</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-3" id="laporanTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="setoran-tab" data-bs-toggle="tab" data-bs-target="#setoran" type="button" role="tab" aria-controls="setoran" aria-selected="true">
                        <i class="fas fa-arrow-down text-success"></i> Detail Laporan Setoran
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tarikan-tab" data-bs-toggle="tab" data-bs-target="#tarikan" type="button" role="tab" aria-controls="tarikan" aria-selected="false">
                        <i class="fas fa-arrow-up text-danger"></i> Detail Laporan Penarikan
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="laporanTabContent">
                <div class="tab-pane fade show active" id="setoran" role="tabpanel" aria-labelledby="setoran-tab">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-table"></i> Laporan Detail Setoran Sampah</div>
                        <div class="card-body">
                            <table class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nasabah</th>
                                        <th>Kelas</th>
                                        <th>Jenis Sampah</th>
                                        <th>Berat (kg)</th>
                                        <th>Total Harga</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_setoran as $data): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($data['tanggal_setor'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($data['kelas']) ?></td>
                                        <td><?= htmlspecialchars($data['nama_sampah']) ?></td>
                                        <td><?= $data['berat'] ?></td>
                                        <td><?= format_rupiah($data['total_harga']) ?></td>
                                        <td><?= htmlspecialchars($data['admin_pencatat']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($laporan_setoran)): ?>
                                        <tr><td colspan="7" class="text-center">Tidak ada data setoran pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tarikan" role="tabpanel" aria-labelledby="tarikan-tab">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-table"></i> Laporan Detail Penarikan Saldo</div>
                        <div class="card-body">
                            <table class="table table-striped table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nasabah</th>
                                        <th>Kelas</th>
                                        <th>Jumlah Penarikan</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_tarikan as $data): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($data['tanggal_tarik'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($data['kelas']) ?></td>
                                        <td><?= format_rupiah($data['jumlah_tarik']) ?></td>
                                        <td><?= htmlspecialchars($data['admin_pencatat']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($laporan_tarikan)): ?>
                                        <tr><td colspan="5" class="text-center">Tidak ada data penarikan pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>