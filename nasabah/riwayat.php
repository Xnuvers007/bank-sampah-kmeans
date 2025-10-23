<?php
// /nasabah/riwayat.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai nasabah
require_login('nasabah');

$id_nasabah = $_SESSION['id_nasabah'];

try {
    // Ambil data riwayat setor
    $stmt_setor = $pdo->prepare("
        SELECT ts.tanggal_setor AS tanggal, js.nama_sampah, ts.berat, ts.total_harga
        FROM transaksi_setor ts
        JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
        WHERE ts.id_nasabah = ?
        ORDER BY ts.tanggal_setor DESC
    ");
    $stmt_setor->execute([$id_nasabah]);
    $riwayat_setor = $stmt_setor->fetchAll();

    // Ambil data riwayat tarik
    $stmt_tarik = $pdo->prepare("
        SELECT tanggal_tarik AS tanggal, jumlah_tarik
        FROM transaksi_tarik
        WHERE id_nasabah = ?
        ORDER BY tanggal_tarik DESC
    ");
    $stmt_tarik->execute([$id_nasabah]);
    $riwayat_tarik = $stmt_tarik->fetchAll();

} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .navbar { background-color: #343a40; }
        .navbar-brand, .nav-link { color: white !important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-leaf"></i> Bank Sampah BU</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="riwayat.php">Riwayat Transaksi</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="mb-4">Riwayat Transaksi Anda</h1>

        <div class="row">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-arrow-down"></i> Riwayat Setor Sampah
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis Sampah</th>
                                    <th>Berat/Jumlah</th>
                                    <th>Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat_setor as $r): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
                                    <td><?= htmlspecialchars($r['nama_sampah']) ?></td>
                                    <td><?= $r['berat'] ?></td>
                                    <td class="text-success">+<?= format_rupiah($r['total_harga']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($riwayat_setor)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada riwayat setoran.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <i class="fas fa-arrow-up"></i> Riwayat Tarik Saldo
                    </div>
                    <div class="card-body">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jumlah Penarikan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($riwayat_tarik as $r): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
                                    <td class="text-danger">-<?= format_rupiah($r['jumlah_tarik']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($riwayat_tarik)): ?>
                                    <tr><td colspan="2" class="text-center text-muted">Belum ada riwayat penarikan.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>