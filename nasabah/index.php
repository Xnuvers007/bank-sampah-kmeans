<?php
// /nasabah/index.php
require '../config/db.php';
require '../config/functions.php';

require_login('nasabah');
$id_nasabah = $_SESSION['id_nasabah'];

try {
    // 1. Ambil data profil nasabah
    $stmt_profil = $pdo->prepare("
        SELECT n.*, ki.nama_klaster, ki.deskripsi
        FROM nasabah n
        LEFT JOIN klaster_info ki ON n.id_klaster = ki.id_klaster
        WHERE n.id_nasabah = ?
    ");
    $stmt_profil->execute([$id_nasabah]);
    $profil = $stmt_profil->fetch();

    // 2. Ambil data agregat (total setor, frekuensi)
    $stmt_agg = $pdo->prepare("
        SELECT COALESCE(SUM(berat), 0) AS total_berat, COALESCE(COUNT(id_setor), 0) AS frekuensi_setor
        FROM transaksi_setor WHERE id_nasabah = ?
    ");
    $stmt_agg->execute([$id_nasabah]);
    $agregat = $stmt_agg->fetch();

    // 3. Ambil 5 riwayat terakhir (campuran)
    $stmt_riwayat = $pdo->prepare("
        (SELECT tanggal_setor AS tanggal, 'Setor Sampah' AS jenis, total_harga AS jumlah, NULL AS berat
         FROM transaksi_setor WHERE id_nasabah = :id_nasabah)
        UNION
        (SELECT tanggal_tarik AS tanggal, 'Tarik Saldo' AS jenis, jumlah_tarik AS jumlah, NULL AS berat
         FROM transaksi_tarik WHERE id_nasabah = :id_nasabah)
        ORDER BY tanggal DESC LIMIT 5
    ");
    $stmt_riwayat->execute(['id_nasabah' => $id_nasabah]);
    $riwayat = $stmt_riwayat->fetchAll();
    
    // --- 4. (FITUR BARU) Ambil data Leaderboard ---
    $stmt_leaderboard = $pdo->query("
        SELECT n.nama_lengkap, SUM(ts.berat) AS total_kg
        FROM transaksi_setor ts
        JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
        GROUP BY ts.id_nasabah
        ORDER BY total_kg DESC
        LIMIT 5
    ");
    $leaderboard = $stmt_leaderboard->fetchAll();

} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nasabah - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .navbar { background-color: #343a40; }
        .navbar-brand, .nav-link { color: white !important; }
        .card-saldo { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; text-align: center; }
        .card-saldo h1 { font-size: 3.5rem; font-weight: 700; }
        .cluster-badge { font-size: 1.1rem; padding: 0.8rem; }
        .leaderboard-item .badge { font-size: 1rem; width: 60px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-leaf"></i> Bank Sampah BU</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="riwayat.php">Riwayat Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="alert alert-light border">
            Selamat datang, <strong class="text-primary"><?= htmlspecialchars($profil['nama_lengkap']) ?></strong>!
            (Kelas: <?= htmlspecialchars($profil['kelas']) ?>)
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card card-saldo shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h5 class="text-white-50">SALDO ANDA SAAT INI</h5>
                        <h1><?= format_rupiah($profil['saldo']) ?></h1>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Status Keaktifan Anda</div>
                    <div class="card-body text-center">
                        <?php if ($profil['id_klaster'] !== null): ?>
                            <span class="badge bg-success cluster-badge"><i class="fas fa-star"></i> <?= htmlspecialchars($profil['nama_klaster']) ?></span>
                            <p class="mt-3 text-muted"><?= htmlspecialchars($profil['deskripsi']) ?></p>
                        <?php else: ?>
                            <span class="badge bg-secondary cluster-badge"><i class="fas fa-question-circle"></i> Belum Terklasifikasi</span>
                            <p class="mt-3 text-muted">Status Anda akan muncul setelah admin melakukan proses clustering data.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark"><i class="fas fa-trophy"></i> Top 5 Nasabah Teraktif (by Berat)</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($leaderboard as $index => $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center leaderboard-item">
                                <div>
                                    <strong>#<?= $index + 1 ?></strong>. <?= htmlspecialchars($item['nama_lengkap']) ?>
                                    <?php if ($item['nama_lengkap'] == $profil['nama_lengkap']) echo '<span class="badge bg-primary ms-2">Anda</span>'; ?>
                                </div>
                                <span class="badge bg-dark rounded-pill"><?= number_format($item['total_kg'], 2) ?> kg</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Statistik Anda</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Sampah Disetor <span class="badge bg-primary rounded-pill"><?= number_format($agregat['total_berat'], 2) ?> kg</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Frekuensi Setoran <span class="badge bg-primary rounded-pill"><?= $agregat['frekuensi_setor'] ?> kali</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">5 Transaksi Terakhir</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($riwayat as $r): ?>
                                <li class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php if ($r['jenis'] == 'Setor Sampah'): ?><i class="fas fa-arrow-down text-success"></i> <?= $r['jenis'] ?><?php else: ?><i class="fas fa-arrow-up text-danger"></i> <?= $r['jenis'] ?><?php endif; ?>
                                        </h6>
                                        <small><?= date('d/m/Y', strtotime($r['tanggal'])) ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?php if ($r['jenis'] == 'Setor Sampah'): ?>Mendapat <span class="text-success">+<?= format_rupiah($r['jumlah']) ?></span><?php else: ?>Menarik <span class="text-danger">-<?= format_rupiah($r['jumlah']) ?></span><?php endif; ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($riwayat)): ?><li class="list-group-item text-center text-muted">Belum ada riwayat transaksi.</li><?php endif; ?>
                        </ul>
                        <a href="riwayat.php" class="btn btn-outline-primary btn-sm mt-3 w-100">Lihat Semua Riwayat</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>