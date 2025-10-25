<?php
// filepath: /C:/xampp/htdocs/bank_sampah/admin/clustering.php
require '../config/db.php';
require '../config/functions.php';
require '../lib/KMeans.php';

require_login('admin');

$K = 3;
$dataToCluster = [];
$originalData = [];
$nasabahData = [];
$results = null;
$chart_datasets = [];
$cluster_colors = [
    'rgba(255, 99, 132, 0.8)', // Merah
    'rgba(54, 162, 235, 0.8)', // Biru
    'rgba(75, 192, 192, 0.8)', // Hijau
    'rgba(255, 206, 86, 0.8)', // Kuning
    'rgba(153, 102, 255, 0.8)' // Ungu
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
            header("Location: clustering.php?status=success");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal menyimpan hasil clustering: " . $e->getMessage();
        }
    }
    
    // 5. Siapkan data untuk Chart jika proses selesai
    if (isset($_GET['status']) && $_GET['status'] == 'success' && !empty($nasabahData)) {
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
                    'label' => $data['nama_lengkap']
                ];
            }
        }
        
        foreach ($temp_datasets as $idx => $points) {
            $chart_datasets[] = [
                'label' => $klasterInfo[$idx] ?? "Klaster $idx",
                'data' => $points,
                'backgroundColor' => $cluster_colors[$idx % count($cluster_colors)]
            ];
        }
        $results = true;
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
    <title>AI Clustering K-Means - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="20" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="20" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="50" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            z-index: -1;
            animation: floating 20s linear infinite;
        }

        @keyframes floating {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-20px) translateY(-20px); }
        }

        .page-header {
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 50%, #C084FC 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(139, 92, 246, 0.4);
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.8s ease-out;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.03) 10px,
                rgba(255,255,255,0.03) 20px
            );
            animation: diagonal-move 20s linear infinite;
        }

        @keyframes diagonal-move {
            0% { transform: translateX(-50%) translateY(-50%); }
            100% { transform: translateX(-48%) translateY(-48%); }
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

        .ai-badge {
            background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .control-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInLeft 0.6s ease-out;
        }

        .control-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #8B5CF6, #A855F7, #C084FC);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .visualization-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInRight 0.6s ease-out 0.3s forwards;
            opacity: 0;
        }

        .visualization-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #FF6B6B, #4ECDC4, #45B7D1);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
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

        .data-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInUp 0.6s ease-out 0.6s forwards;
            opacity: 0;
        }

        .data-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
        }

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

        .card-header-ai {
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 700;
            font-size: 1.2rem;
            position: relative;
        }

        .card-header-chart {
            background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .card-header-data {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #8B5CF6, #A855F7);
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.5rem;
        }

        .btn-process {
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 3rem;
            font-weight: 700;
            font-size: 1.2rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-process::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }

        .btn-process:hover::before {
            left: 100%;
        }

        .btn-process:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(139, 92, 246, 0.5);
            color: white;
        }

        .btn-process:disabled {
            background: linear-gradient(135deg, #9CA3AF 0%, #6B7280 100%);
            cursor: not-allowed;
            box-shadow: none;
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
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
        }

        .table-modern tbody tr:nth-child(odd) {
            background: rgba(248,249,250,0.9);
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            transform: scale(1.01);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border: none;
            vertical-align: middle;
            font-weight: 500;
        }

        .cluster-badge {
            color: white;
            padding: 0.8rem 1.2rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-block;
            text-align: center;
            min-width: 120px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-modern {
            border: none;
            border-radius: 15px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            color: #065f46;
            border-left: 6px solid #10b981;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            color: #7f1d1d;
            border-left: 6px solid #dc2626;
        }

        .icon-badge {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-right: 1.5rem;
        }

        .icon-ai {
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 100%);
            color: white;
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.4);
        }

        .chart-container {
            position: relative;
            height: 500px;
            padding: 2rem;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 15px;
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
            border-top: 5px solid #8B5CF6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing-text {
            color: #8B5CF6;
            font-weight: 600;
            font-size: 1.1rem;
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
            background: linear-gradient(135deg, #8B5CF6 0%, #A855F7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        @media (max-width: 768px) {
            .page-header {
                text-align: center;
                padding: 2rem 1rem;
            }
            
            .control-card, .visualization-card, .data-card {
                margin-bottom: 2rem;
            }

            .icon-badge {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .btn-process {
                width: 100%;
                margin-bottom: 1rem;
            }

            .chart-container {
                height: 400px;
                padding: 1rem;
            }
        }
        .rapi {
            animation: slideInLeft 0.8s ease-out;
            padding-left: 7%;
        }
    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-md-8 rapi">
                        <div class="ai-badge">
                            <i class="fas fa-robot me-2"></i>AI Powered
                        </div>
                        <h1 class="mb-0 display-5">
                            <i class="fas fa-project-diagram me-3"></i>
                            Clustering K-Means Analysis
                        </h1>
                        <p class="mb-0 mt-2 opacity-75 fs-5">Algoritma Machine Learning untuk pengelompokan nasabah berdasarkan pola setoran</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="icon-badge icon-ai">
                            <i class="fas fa-brain"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-modern">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                <div class="alert alert-success alert-modern">
                    <i class="fas fa-check-circle me-2"></i>
                    Proses clustering berhasil dan data telah disimpan! Hasil klaster (termasuk grafik) ditampilkan di bawah.
                </div>
            <?php endif; ?>

            <!-- Control Panel -->
            <div class="control-card mb-4">
                <div class="card-header-ai">
                    <i class="fas fa-cogs me-2"></i>
                    Control Panel Clustering
                </div>
                <div class="card-body p-4">
                    <!-- Statistics -->
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-value"><?= $K ?></div>
                            <div class="stat-label">Jumlah Klaster (K)</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= count($nasabahData) ?></div>
                            <div class="stat-label">Total Nasabah</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= count($dataToCluster) ?></div>
                            <div class="stat-label">Data Points</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value">2</div>
                            <div class="stat-label">Dimensi Fitur</div>
                        </div>
                    </div>

                    <!-- Process Form -->
                    <form method="POST" action="clustering.php" id="clusteringForm">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <h5 class="text-muted mb-2">
                                        <i class="fas fa-info-circle me-2"></i>Informasi Proses
                                    </h5>
                                    <p class="mb-1">
                                        <strong>Algoritma:</strong> K-Means Clustering
                                    </p>
                                    <p class="mb-1">
                                        <strong>Fitur Analisis:</strong> Total Berat Setoran & Frekuensi Setoran
                                    </p>
                                    <p class="mb-0">
                                        <strong>Tujuan:</strong> Mengelompokkan nasabah berdasarkan tingkat aktivitas
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <button type="submit" name="proses_cluster" class="btn btn-process" <?= empty($dataToCluster) ? 'disabled' : '' ?>>
                                    <i class="fas fa-play-circle me-2"></i>
                                    Mulai Clustering
                                </button>
                                <?php if (empty($dataToCluster)): ?>
                                    <small class="text-muted d-block mt-2">Tidak ada data untuk diproses</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Loading Overlay -->
                        <div class="loading-overlay" id="loadingOverlay">
                            <div class="spinner"></div>
                            <div class="processing-text">Memproses algoritma K-Means...</div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Visualization Chart -->
            <?php if ($results && !empty($chart_datasets)): ?>
            <div class="visualization-card mb-4">
                <div class="card-header-chart d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-chart-scatter me-2"></i>
                        Visualisasi Hasil Clustering
                    </span>
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                        <i class="fas fa-eye me-1"></i>
                        Real-time Results
                    </span>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="clusterChart"></canvas>
                    </div>
                    
                    <!-- Legend Info -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="mb-3">
                                <i class="fas fa-info-circle me-2"></i>Interpretasi Hasil:
                            </h6>
                            <div class="row">
                                <?php foreach ($chart_datasets as $index => $dataset): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 20px; height: 20px; border-radius: 50%; background: <?= $cluster_colors[$index] ?>; margin-right: 10px;"></div>
                                        <span class="fw-bold"><?= htmlspecialchars($dataset['label']) ?></span>
                                        <span class="ms-2 text-muted">(<?= count($dataset['data']) ?> nasabah)</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Data Table -->
            <div class="data-card">
                <div class="card-header-data d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-table me-2"></i>
                        Dataset Training & Hasil Clustering
                    </span>
                    <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                        <?= count($nasabahData) ?> Records
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($nasabahData) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i>Nama Nasabah</th>
                                        <th><i class="fas fa-graduation-cap me-1"></i>Kelas</th>
                                        <th><i class="fas fa-weight me-1"></i>Total Berat (kg)</th>
                                        <th><i class="fas fa-repeat me-1"></i>Frekuensi Setor</th>
                                        <?php if ($results): ?>
                                            <th><i class="fas fa-project-diagram me-1"></i>Hasil Klaster</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nasabahData as $index => $data): ?>
                                    <tr style="animation-delay: <?= $index * 0.05 ?>s">
                                        <td>
                                            <strong><?= htmlspecialchars($data['nama_lengkap']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= htmlspecialchars($data['kelas']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-primary">
                                                <?= number_format($data['total_berat'], 2) ?> kg
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                <?= $data['frekuensi_setor'] ?> kali
                                            </span>
                                        </td>
                                        <?php if ($results): ?>
                                            <td>
                                                <?php 
                                                $klaster_id = $id_nasabah_to_klaster[$data['id_nasabah']] ?? null;
                                                if ($klaster_id !== null): 
                                                ?>
                                                    <span class="cluster-badge" style="background: <?= $cluster_colors[$klaster_id % count($cluster_colors)] ?>;">
                                                        <?= htmlspecialchars($klasterInfo[$klaster_id] ?? "Klaster $klaster_id") ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak Terklaster</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-database"></i>
                            <h4>Tidak Ada Data Nasabah</h4>
                            <p>Silakan tambahkan data nasabah terlebih dahulu untuk melakukan clustering</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Show loading on form submit
        document.getElementById('clusteringForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').classList.add('show');
        });

        // Chart initialization
        <?php if ($results && !empty($chart_datasets)): ?>
        const ctxCluster = document.getElementById('clusterChart');
        new Chart(ctxCluster, {
            type: 'scatter',
            data: {
                datasets: <?= json_encode($chart_datasets) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribusi Nasabah Berdasarkan Klaster',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        color: '#374151'
                    },
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.3)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                let data = context.dataset.data[context.dataIndex];
                                return data.label + ': (' + data.x + ' kg, ' + data.y + ' kali)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Total Berat Setoran (kg)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#374151'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Frekuensi Setoran (kali)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            },
                            color: '#374151'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'point'
                },
                elements: {
                    point: {
                        radius: 8,
                        hoverRadius: 12,
                        borderWidth: 2,
                        hoverBorderWidth: 3
                    }
                }
            }
        });
        <?php endif; ?>

        // Auto dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 8000);

        // Animate table rows on load
        document.querySelectorAll('.table-modern tbody tr').forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateX(0)';
            }, index * 50);
        });

        // Console log for debugging
        console.log('🤖 AI Clustering K-Means Dashboard loaded successfully!');
        console.log('📊 Total nasabah:', <?= count($nasabahData) ?>);
        console.log('🎯 Total klaster:', <?= $K ?>);
    </script>
</body>
</html>