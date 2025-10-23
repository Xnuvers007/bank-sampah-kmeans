<?php
// /admin/stok.php
require '../config/db.php';
require '../config/functions.php';

require_login('admin');

try {
    // Ambil semua jenis sampah
    $sampah_list = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_sampah")->fetchAll();
    
    $stok_data = [];
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
        
        $stok_data[] = [
            'nama_sampah' => $sampah['nama_sampah'],
            'satuan' => $sampah['satuan'],
            'total_masuk' => $total_masuk,
            'total_keluar' => $total_keluar,
            'stok_akhir' => $stok_akhir
        ];
    }
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
            <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" href="nasabah.php"><i class="fas fa-users"></i> Data Nasabah</a></li>
            <li class="nav-item"><a class="nav-link" href="sampah.php"><i class="fas fa-trash"></i> Data Sampah</a></li>
            <li class="nav-item"><a class="nav-link" href="transaksi_setor.php"><i class="fas fa-download"></i> Setor Sampah</a></li>
            <li class="nav-item"><a class="nav-link" href="transaksi_tarik.php"><i class="fas fa-upload"></i> Tarik Saldo</a></li>
            <hr class="text-secondary">
            <li class="nav-item"><a class="nav-link" href="penjualan.php"><i class="fas fa-truck-loading"></i> Penjualan Sampah</a></li>
            <li class="nav-item"><a class="nav-link active" href="stok.php"><i class="fas fa-warehouse"></i> Stok Gudang</a></li>
            <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="fas fa-file-alt"></i> Laporan</a></li>
            <li class="nav-item"><a class="nav-link" href="clustering.php"><i class="fas fa-project-diagram"></i> Clustering K-Means</a></li>
            <hr class="text-secondary">
            <li class="nav-item mt-auto"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mt-4">Laporan Stok Gudang</h1>
            <?php if (isset($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header"><i class="fas fa-warehouse"></i> Stok Sampah Terkumpul</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr class="table-light">
                                <th>Nama Sampah</th>
                                <th>Total Masuk (Setoran)</th>
                                <th>Total Keluar (Penjualan)</th>
                                <th>Stok Akhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stok_data as $stok): ?>
                            <tr>
                                <td><?= htmlspecialchars($stok['nama_sampah']) ?></td>
                                <td><?= number_format($stok['total_masuk'], 2) ?> <?= $stok['satuan'] ?></td>
                                <td><?= number_format($stok['total_keluar'], 2) ?> <?= $stok['satuan'] ?></td>
                                <td class="fw-bold fs-5"><?= number_format($stok['stok_akhir'], 2) ?> <?= $stok['satuan'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>