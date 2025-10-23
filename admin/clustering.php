<?php
// /admin/clustering.php

// 1. Include dependencies
require '../config/functions.php';
require '../lib/KMeans.php';
require '../config/db.php';

require_login('admin');

// Atur K (jumlah klaster)
$K = 3; // Misal kita ingin 3 klaster: Kurang Aktif, Cukup Aktif, Sangat Aktif

// Variabel untuk menampung hasil
$dataToCluster = [];
$originalData = [];
$nasabahData = [];
$results = null;

try {
    // 2. Query data untuk di-cluster
    // Kita akan meng-cluster berdasarkan:
    // 1. Total Berat Setoran (dimensi 1)
    // 2. Frekuensi Setoran (dimensi 2)
    
    // Siapkan query untuk mengambil data nasabah
    $stmtNasabah = $pdo->query("SELECT id_nasabah, nama_lengkap, kelas FROM nasabah");
    $nasabahTemp = $stmtNasabah->fetchAll(PDO::FETCH_ASSOC); // Array asosiatif untuk semua kolom    

    // Siapkan query untuk data agregat transaksi
    $query = "
        SELECT 
            id_nasabah,
            COALESCE(SUM(berat), 0) AS total_berat,
            COALESCE(COUNT(id_setor), 0) AS frekuensi_setor
        FROM transaksi_setor
        GROUP BY id_nasabah
    ";
    
    // Handle nasabah yg belum pernah setor (LEFT JOIN)
    $query = "
        SELECT 
            n.id_nasabah,
            n.nama_lengkap,
            n.kelas,
            COALESCE(t.total_berat, 0) AS total_berat,
            COALESCE(t.frekuensi_setor, 0) AS frekuensi_setor
        FROM nasabah n
        LEFT JOIN (
            SELECT 
                id_nasabah,
                SUM(berat) AS total_berat,
                COUNT(id_setor) AS frekuensi_setor
            FROM transaksi_setor
            GROUP BY id_nasabah
        ) t ON n.id_nasabah = t.id_nasabah
    ";
    
    $stmt = $pdo->query($query);
    
    while ($row = $stmt->fetch()) {
        // Data untuk K-Means (hanya angka)
        $dataToCluster[] = [
            (float) $row['total_berat'], // Dimensi 0
            (int) $row['frekuensi_setor'] // Dimensi 1
        ];
        
        // Data asli (untuk referensi siapa orangnya)
        $originalData[] = [
            'id_nasabah' => $row['id_nasabah'],
            'nama_lengkap' => $row['nama_lengkap'],
            'kelas' => $row['kelas']
        ];
        
        // Data lengkap untuk ditampilkan
        $nasabahData[] = $row;
    }

    // 3. Proses Clustering (jika ada data dan tombol 'Proses' ditekan)
    if (isset($_POST['proses_cluster']) && !empty($dataToCluster)) {
        
        $kmeans = new KMeans($K);
        $kmeans->loadData($dataToCluster, $originalData);
        $results = $kmeans->run();

        // 4. Simpan hasil clustering ke database
        $pdo->beginTransaction();
        try {
            // Update tabel 'nasabah'
            $updateStmt = $pdo->prepare("UPDATE nasabah SET id_klaster = :id_klaster WHERE id_nasabah = :id_nasabah");
            
            foreach ($results as $clusterIndex => $cluster) {
                foreach ($cluster['data_points'] as $dataPoint) {
                    $id_nasabah = $dataPoint['original']['id_nasabah'];
                    $updateStmt->execute([
                        'id_klaster' => $clusterIndex,
                        'id_nasabah' => $id_nasabah
                    ]);
                }
            }
            $pdo->commit();
            $message = "Proses clustering berhasil dan data telah disimpan!";
            // Refresh data setelah update
            header("Location: clustering.php?status=success");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal menyimpan hasil clustering: " . $e->getMessage();
        }
    }
    
    // Ambil info nama klaster dari DB
    $klasterInfoStmt = $pdo->query("SELECT id_klaster, nama_klaster FROM klaster_info");
    $klasterInfo = $klasterInfoStmt->fetchAll(PDO::FETCH_KEY_PAIR);


} catch (Exception $e) {
    $error = "Terjadi error: " . $e->getMessage();
}

// ---------------------------------------------------
// Bagian Tampilan (HTML)
// ---------------------------------------------------
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proses Clustering K-Means</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            padding-top: 20px;
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: #ccc;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            color: #fff;
            background-color: #495057;
        }
        .content { margin-left: 260px; padding: 20px; }
    </style>
</head>
<body>
    <?php // include 'sidebar.php'; // Sebaiknya sidebar dipisah ke file sendiri ?>
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
            <h1 class="mt-4">Clustering K-Means Nasabah</h1>
            <p>Kelompokkan nasabah (siswa) berdasarkan total berat dan frekuensi setoran sampah.</p>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="alert alert-success">Proses clustering berhasil dan data telah disimpan!</div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="clustering.php">
                        <p>Jumlah Klaster (K) saat ini: <strong><?= $K ?></strong>.</p>
                        <p>Total Nasabah terdeteksi: <strong><?= count($nasabahData) ?></strong>.</p>
                        <button type="submit" name="proses_cluster" class="btn btn-primary btn-lg">
                            <i class="fas fa-play-circle"></i> Mulai Proses Clustering
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-table"></i> Data Awal Nasabah (Data Latih)</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID Nasabah</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Total Berat (kg)</th>
                                <th>Frekuensi Setor (kali)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nasabahData as $data): ?>
                            <tr>
                                <td><?= htmlspecialchars($data['id_nasabah']) ?></td>
                                <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($data['kelas']) ?></td>
                                <td><?= htmlspecialchars($data['total_berat']) ?></td>
                                <td><?= htmlspecialchars($data['frekuensi_setor']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($nasabahData)): ?>
                                <tr><td colspan="5" class="text-center">Belum ada data transaksi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($results): ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white"><i class="fas fa-check-circle"></i> Hasil Clustering</div>
                <div class="card-body">
                    <?php foreach ($results as $clusterIndex => $cluster): ?>
                        <h4 class="mt-3">
                            <?= htmlspecialchars($klasterInfo[$clusterIndex] ?? "Klaster $clusterIndex") ?>
                        </h4>
                        <p>
                            Centroid (Titik Pusat): 
                            [Total Berat: <?= number_format($cluster['centroid'][0], 2) ?> kg, 
                            Frekuensi: <?= number_format($cluster['centroid'][1], 2) ?> kali]
                        </p>
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Nasabah</th>
                                    <th>Kelas</th>
                                    <th>Total Berat (kg)</th>
                                    <th>Frekuensi (kali)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cluster['data_points'] as $dataPoint): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dataPoint['original']['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($dataPoint['original']['kelas']) ?></td>
                                    <td><?= htmlspecialchars($dataPoint['point'][0]) ?></td>
                                    <td><?= htmlspecialchars($dataPoint['point'][1]) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>