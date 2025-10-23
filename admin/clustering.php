<?php
// /admin/clustering.php
require '../config/db.php';
require '../config/functions.php';
require '../lib/KMeans.php';

require_login('admin');

$K = 3;
$dataToCluster = [];
$originalData = [];
$nasabahData = [];
$results = null;
$chart_datasets = []; // Untuk data chart
$cluster_colors = [ // Warna untuk chart
    'rgba(255, 99, 132, 0.7)', // Merah
    'rgba(54, 162, 235, 0.7)', // Biru
    'rgba(75, 192, 192, 0.7)', // Hijau
    'rgba(255, 206, 86, 0.7)', // Kuning
    'rgba(153, 102, 255, 0.7)' // Ungu
];

try {
    // 1. Ambil info klaster dari DB
    $klasterInfoStmt = $pdo->query("SELECT id_klaster, nama_klaster FROM klaster_info");
    $klasterInfo = $klasterInfoStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Query data nasabah (Data Latih)
    $query = "
        SELECT 
            n.id_nasabah, n.nama_lengkap, n.kelas,
            COALESCE(t.total_berat, 0) AS total_berat,
            COALESCE(t.frekuensi_setor, 0) AS frekuensi_setor
        FROM nasabah n
        LEFT JOIN (
            SELECT id_nasabah, SUM(berat) AS total_berat, COUNT(id_setor) AS frekuensi_setor
            FROM transaksi_setor GROUP BY id_nasabah
        ) t ON n.id_nasabah = t.id_nasabah
    ";
    
    $stmt = $pdo->query($query);
    while ($row = $stmt->fetch()) {
        $dataToCluster[] = [(float) $row['total_berat'], (int) $row['frekuensi_setor']];
        $originalData[] = ['id_nasabah' => $row['id_nasabah'], 'nama_lengkap' => $row['nama_lengkap'], 'kelas' => $row['kelas']];
        $nasabahData[] = $row;
    }

    // 3. Proses Clustering
    if (isset($_POST['proses_cluster']) && !empty($dataToCluster)) {
        
        $kmeans = new KMeans($K);
        $kmeans->loadData($dataToCluster, $originalData);
        $results = $kmeans->run();

        // 4. Simpan hasil ke DB
        $pdo->beginTransaction();
        try {
            $updateStmt = $pdo->prepare("UPDATE nasabah SET id_klaster = :id_klaster WHERE id_nasabah = :id_nasabah");
            foreach ($results as $clusterIndex => $cluster) {
                foreach ($cluster['data_points'] as $dataPoint) {
                    $updateStmt->execute(['id_klaster' => $clusterIndex, 'id_nasabah' => $dataPoint['original']['id_nasabah']]);
                }
            }
            $pdo->commit();
            // Redirect untuk menampilkan data yang sudah di-update
            header("Location: clustering.php?status=success");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal menyimpan hasil clustering: " . $e->getMessage();
        }
    }
    
    // 5. (FITUR BARU) Siapkan data untuk Chart jika proses selesai
    if (isset($_GET['status']) && $_GET['status'] == 'success' && !empty($nasabahData)) {
        // Ambil data nasabah yang SUDAH ADA ID KLASTER-nya
        $stmt_clustered = $pdo->query("SELECT * FROM nasabah WHERE id_klaster IS NOT NULL");
        $nasabah_clustered = $stmt_clustered->fetchAll();
        $id_nasabah_to_klaster = array_column($nasabah_clustered, 'id_klaster', 'id_nasabah');
        
        $temp_datasets = [];
        foreach ($nasabahData as $data) {
            $id_n = $data['id_nasabah'];
            $klaster_idx = $id_nasabah_to_klaster[$id_n] ?? null;
            
            if ($klaster_idx !== null) {
                $temp_datasets[$klaster_idx][] = [
                    'x' => (float) $data['total_berat'],
                    'y' => (int) $data['frekuensi_setor'],
                    'label' => $data['nama_lengkap'] // Data untuk tooltip
                ];
            }
        }
        
        // Format data sesuai kebutuhan Chart.js
        foreach ($temp_datasets as $idx => $points) {
            $chart_datasets[] = [
                'label' => $klasterInfo[$idx] ?? "Klaster $idx",
                'data' => $points,
                'backgroundColor' => $cluster_colors[$idx % count($cluster_colors)]
            ];
        }
        $results = true; // Tandai agar chart tampil
    }

} catch (Exception $e) {
    $error = "Terjadi error: " . $e->getMessage();
}
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
            <li class="nav-item"><a class="nav-link" href="stok.php"><i class="fas fa-warehouse"></i> Stok Gudang</a></li>
            <li class="nav-item"><a class="nav-link" href="laporan.php"><i class="fas fa-file-alt"></i> Laporan</a></li>
            <li class="nav-item"><a class="nav-link active" href="clustering.php"><i class="fas fa-project-diagram"></i> Clustering K-Means</a></li>
            <hr class="text-secondary">
            <li class="nav-item mt-auto"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mt-4">Clustering K-Means Nasabah</h1>
            <p>Kelompokkan nasabah (siswa) berdasarkan total berat dan frekuensi setoran sampah.</p>
            
            <?php if (isset($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="alert alert-success">Proses clustering berhasil dan data telah disimpan! Hasil klaster (termasuk grafik) ditampilkan di bawah.</div>
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

            <?php if ($results && !empty($chart_datasets)): // Tampilkan hanya jika ada hasil ?>
            <div class="card mb-4">
                <div class="card-header bg-success text-white"><i class="fas fa-chart-scatter"></i> Visualisasi Klaster</div>
                <div class="card-body">
                    <canvas id="clusterChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-table"></i> Data Awal Nasabah (Data Latih)</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Nama Nasabah</th>
                                <th>Kelas</th>
                                <th>Total Berat (kg)</th>
                                <th>Frekuensi Setor (kali)</th>
                                <?php if ($results) echo '<th>Hasil Klaster</th>'; // Tampilkan jika sudah dicluster ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nasabahData as $data): ?>
                            <tr>
                                <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($data['kelas']) ?></td>
                                <td><?= htmlspecialchars($data['total_berat']) ?></td>
                                <td><?= htmlspecialchars($data['frekuensi_setor']) ?></td>
                                <?php if ($results): ?>
                                    <td style="background-color: <?= $cluster_colors[($id_nasabah_to_klaster[$data['id_nasabah']] ?? 0) % count($cluster_colors)] ?>; color: white; font-weight: bold;">
                                        <?= htmlspecialchars($klasterInfo[$id_nasabah_to_klaster[$data['id_nasabah']] ?? 'N/A']) ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    <?php if ($results && !empty($chart_datasets)): // Hanya jalankan JS jika data ada ?>
        const ctxCluster = document.getElementById('clusterChart');
        new Chart(ctxCluster, {
            type: 'scatter',
            data: {
                datasets: <?= json_encode($chart_datasets) ?>
            },
            options: {
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Total Berat Setoran (kg)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Frekuensi Setoran (kali)'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let data = context.dataset.data[context.dataIndex];
                                // Tampilkan nama nasabah di tooltip
                                return data.label + ': (' + data.x + ' kg, ' + data.y + ' kali)';
                            }
                        }
                    }
                }
            }
        });
    <?php endif; ?>
    </script>
</body>
</html>