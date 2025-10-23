<?php
// /admin/penjualan.php
require '../config/db.php';
require '../config/functions.php';

require_login('admin');

$pesan = '';
$error = '';

// --- LOGIKA PENJUALAN SAMPAH ---
if (isset($_POST['submit_jual'])) {
    $id_sampah = $_POST['id_sampah'];
    $id_pengepul = $_POST['id_pengepul'];
    $berat = (float)$_POST['berat'];
    $harga_jual_per_kg = (int)$_POST['harga_jual_per_kg'];
    $total_pendapatan = $berat * $harga_jual_per_kg;
    $id_admin = $_SESSION['user_id'];

    // Validasi Stok
    $stmt_stok_in = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE id_sampah = ?");
    $stmt_stok_in->execute([$id_sampah]);
    $total_masuk = $stmt_stok_in->fetchColumn();

    $stmt_stok_out = $pdo->prepare("SELECT COALESCE(SUM(berat), 0) FROM transaksi_jual WHERE id_sampah = ?");
    $stmt_stok_out->execute([$id_sampah]);
    $total_keluar = $stmt_stok_out->fetchColumn();
    
    $stok_tersedia = $total_masuk - $total_keluar;

    if ($berat > $stok_tersedia) {
        $error = "Gagal. Stok tersedia untuk sampah ini hanya " . number_format($stok_tersedia, 2) . " kg.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO transaksi_jual (id_sampah, id_pengepul, berat, harga_jual_per_kg, total_pendapatan, dicatat_oleh) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_sampah, $id_pengepul, $berat, $harga_jual_per_kg, $total_pendapatan, $id_admin]);
            $pesan = "Penjualan sampah berhasil dicatat.";
        } catch (Exception $e) {
            $error = "Gagal mencatat penjualan: " . $e->getMessage();
        }
    }
}

// --- LOGIKA KELOLA PENGEPUL ---
if (isset($_POST['submit_pengepul'])) {
    $nama_pengepul = $_POST['nama_pengepul'];
    $kontak_pengepul = $_POST['kontak_pengepul'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO pengepul (nama_pengepul, kontak_pengepul) VALUES (?, ?)");
        $stmt->execute([$nama_pengepul, $kontak_pengepul]);
        $pesan = "Pengepul baru berhasil ditambahkan.";
    } catch (Exception $e) {
        $error = "Gagal menambah pengepul: " . $e->getMessage();
    }
}

// Ambil data untuk form
try {
    $stmt_sampah = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_sampah");
    $sampah_list = $stmt_sampah->fetchAll();

    $stmt_pengepul = $pdo->query("SELECT * FROM pengepul ORDER BY nama_pengepul");
    $pengepul_list = $stmt_pengepul->fetchAll();

    $stmt_riwayat = $pdo->query("
        SELECT pj.*, js.nama_sampah, p.nama_pengepul
        FROM penjualan pj
        JOIN jenis_sampah js ON pj.id_sampah = js.id_sampah
        JOIN pengepul p ON pj.id_pengepul = p.id_pengepul
        ORDER BY pj.tanggal_jual DESC
        LIMIT 5
    ");
    $riwayat_jual = $stmt_riwayat->fetchAll();
} catch (Exception $e) {
    die("Gagal mengambil data: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penjualan Sampah - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/penjualan.css">
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mt-4">Penjualan Sampah ke Pengepul</h1>
            <?php if ($pesan): ?><div class="alert alert-success"><?= $pesan ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-truck-loading"></i> Catat Penjualan</div>
                        <div class="card-body">
                            <form action="penjualan.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Sampah</label>
                                    <select name="id_sampah" class="form-select" required>
                                        <option value="">-- Pilih Sampah --</option>
                                        <?php foreach ($sampah_list as $s): ?>
                                            <option value="<?= $s['id_sampah'] ?>"><?= htmlspecialchars($s['nama_sampah']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Berat (kg)</label>
                                    <input type="number" step="0.01" name="berat" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Harga Jual (per kg)</label>
                                    <input type="number" name="harga_jual_per_kg" class="form-control" placeholder="Harga dari pengepul" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dijual Ke Pengepul</label>
                                    <select name="id_pengepul" class="form-select" required>
                                        <option value="">-- Pilih Pengepul --</option>
                                        <?php foreach ($pengepul_list as $p): ?>
                                            <option value="<?= $p['id_pengepul'] ?>"><?= htmlspecialchars($p['nama_pengepul']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="submit_jual" class="btn btn-primary">Simpan Penjualan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-history"></i> 5 Penjualan Terakhir</div>
                        <div class="card-body">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Sampah</th>
                                        <th>Berat</th>
                                        <th>Total</th>
                                        <th>Pengepul</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat_jual as $r): ?>
                                    <tr>
                                        <td><?= date('d/m/y', strtotime($r['tanggal_jual'])) ?></td>
                                        <td><?= htmlspecialchars($r['nama_sampah']) ?></td>
                                        <td><?= $r['berat'] ?> kg</td>
                                        <td><?= format_rupiah($r['total_pendapatan']) ?></td>
                                        <td><?= htmlspecialchars($r['nama_pengepul']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><i class="fas fa-user-plus"></i> Kelola Data Pengepul</div>
                        <div class="card-body">
                            <form action="penjualan.php" method="POST" class="mb-3">
                                <div class="row">
                                    <div class="col-md-6"><input type="text" name="nama_pengepul" class="form-control" placeholder="Nama Pengepul Baru" required></div>
                                    <div class="col-md-4"><input type="text" name="kontak_pengepul" class="form-control" placeholder="Kontak (WA)"></div>
                                    <div class="col-md-2"><button type="submit" name="submit_pengepul" class="btn btn-success w-100">Tambah</button></div>
                                </div>
                            </form>
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Pengepul</th>
                                        <th>Kontak</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pengepul_list as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['nama_pengepul']) ?></td>
                                        <td><?= htmlspecialchars($p['kontak_pengepul']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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