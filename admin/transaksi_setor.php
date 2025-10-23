<?php
// /admin/transaksi_setor.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

$pesan = '';
$error = '';

// Ambil data nasabah untuk dropdown
$stmt_nasabah = $pdo->query("SELECT id_nasabah, nama_lengkap, kelas FROM nasabah ORDER BY nama_lengkap");
$nasabah_list = $stmt_nasabah->fetchAll();

// Ambil data sampah untuk dropdown
$stmt_sampah = $pdo->query("SELECT id_sampah, nama_sampah, satuan, harga_beli FROM jenis_sampah ORDER BY nama_sampah");
$sampah_list = $stmt_sampah->fetchAll();

// Buat array JS untuk lookup harga
$sampah_js_array = [];
foreach ($sampah_list as $s) {
    $sampah_js_array[$s['id_sampah']] = [
        'harga' => $s['harga_beli'],
        'satuan' => $s['satuan']
    ];
}

// Logika Proses Form
if (isset($_POST['submit'])) {
    $id_nasabah = $_POST['id_nasabah'];
    $id_sampah = $_POST['id_sampah'];
    $berat = (float) $_POST['berat'];
    $total_harga = (int) $_POST['total_harga'];
    $id_admin = $_SESSION['user_id']; // Ambil ID admin yang sedang login

    if ($berat <= 0 || $total_harga <= 0) {
        $error = "Berat dan Total Harga harus lebih dari 0.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Masukkan ke tabel transaksi_setor
            $stmt_setor = $pdo->prepare("
                INSERT INTO transaksi_setor (id_nasabah, id_sampah, berat, total_harga, dicatat_oleh)
                VALUES (:id_nasabah, :id_sampah, :berat, :total_harga, :id_admin)
            ");
            $stmt_setor->execute([
                'id_nasabah' => $id_nasabah,
                'id_sampah' => $id_sampah,
                'berat' => $berat,
                'total_harga' => $total_harga,
                'id_admin' => $id_admin
            ]);

            // 2. Update saldo nasabah
            $stmt_update_saldo = $pdo->prepare("
                UPDATE nasabah SET saldo = saldo + :jumlah WHERE id_nasabah = :id_nasabah
            ");
            $stmt_update_saldo->execute([
                'jumlah' => $total_harga,
                'id_nasabah' => $id_nasabah
            ]);

            $pdo->commit();
            $pesan = "Setoran sampah berhasil dicatat. Saldo nasabah telah ditambahkan.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal mencatat setoran: " . $e->getMessage();
        }
    }
}

// Ambil riwayat 5 setoran terakhir
$stmt_riwayat = $pdo->query("
    SELECT ts.*, n.nama_lengkap, js.nama_sampah
    FROM transaksi_setor ts
    JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
    JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
    ORDER BY ts.tanggal_setor DESC
    LIMIT 5
");
$riwayat_setor = $stmt_riwayat->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setor Sampah - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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
            <h1 class="mt-4">Input Setoran Sampah</h1>

            <?php if ($pesan): ?><div class="alert alert-success"><?= $pesan ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-edit"></i> Form Setoran</div>
                        <div class="card-body">
                            <form action="transaksi_setor.php" method="POST" id="formSetor">
                                
                                <div class="mb-3">
                                    <label for="id_nasabah" class="form-label">Nama Nasabah (Siswa)</label>
                                    <select class="form-select" id="id_nasabah" name="id_nasabah" required>
                                        <option value="">-- Pilih Nasabah --</option>
                                        <?php foreach ($nasabah_list as $nasabah): ?>
                                            <option value="<?= $nasabah['id_nasabah'] ?>">
                                                <?= htmlspecialchars($nasabah['nama_lengkap']) ?> (<?= htmlspecialchars($nasabah['kelas']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="id_sampah" class="form-label">Jenis Sampah</label>
                                    <select class="form-select" id="id_sampah" name="id_sampah" required>
                                        <option value="">-- Pilih Jenis Sampah --</option>
                                        <?php foreach ($sampah_list as $sampah): ?>
                                            <option value="<?= $sampah['id_sampah'] ?>">
                                                <?= htmlspecialchars($sampah['nama_sampah']) ?> (<?= format_rupiah($sampah['harga_beli']) ?>/<?= $sampah['satuan'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="berat" class="form-label">Jumlah / Berat</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="berat" name="berat" required>
                                        <span class="input-group-text" id="satuan_text">--</span>
                                    </div>
                                </div>
                                
                                <hr>

                                <div class="mb-3">
                                    <label for="total_harga" class="form-label">Total Harga (Otomatis)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" id="total_harga" name="total_harga" readonly required>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit" class="btn btn-primary">Simpan Transaksi</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-history"></i> 5 Setoran Terakhir</div>
                        <div class="card-body">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama</th>
                                        <th>Sampah</th>
                                        <th>Berat</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat_setor as $riwayat): ?>
                                    <tr>
                                        <td><?= date('d/m/y H:i', strtotime($riwayat['tanggal_setor'])) ?></td>
                                        <td><?= htmlspecialchars($riwayat['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($riwayat['nama_sampah']) ?></td>
                                        <td><?= $riwayat['berat'] ?></td>
                                        <td><?= format_rupiah($riwayat['total_harga']) ?></td>
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Data sampah dari PHP
        const dataSampah = <?= json_encode($sampah_js_array) ?>;
        
        // Fungsi untuk menghitung total
        function hitungTotal() {
            let idSampah = $('#id_sampah').val();
            let berat = parseFloat($('#berat').val()) || 0;
            
            if (idSampah && dataSampah[idSampah]) {
                let harga = dataSampah[idSampah].harga;
                let satuan = dataSampah[idSampah].satuan;
                
                let total = Math.round(berat * harga);
                $('#total_harga').val(total);
                $('#satuan_text').text(satuan);
            } else {
                $('#total_harga').val(0);
                $('#satuan_text').text('--');
            }
        }

        $(document).ready(function() {
            // Inisialisasi Select2 (agar dropdown bisa dicari)
            $('#id_nasabah').select2({
                theme: "bootstrap-5",
                dropdownParent: $('#id_nasabah').parent() // Penting jika di dalam modal
            });
            $('#id_sampah').select2({
                theme: "bootstrap-5",
                dropdownParent: $('#id_sampah').parent()
            });

            // Pasang event listener
            $('#id_sampah, #berat').on('change keyup', hitungTotal);
        });
    </script>
</body>
</html>