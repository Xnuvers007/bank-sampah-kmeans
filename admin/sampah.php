<?php
// /admin/sampah.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

$pesan = '';
$error = '';

// Logika untuk menangani form (Create & Update)
if (isset($_POST['submit'])) {
    $nama_sampah = $_POST['nama_sampah'];
    $satuan = $_POST['satuan'];
    $harga_beli = (int)$_POST['harga_beli'];
    $id_sampah = $_POST['id_sampah']; // Untuk update

    if ($harga_beli <= 0) {
        $error = "Harga beli harus lebih dari 0.";
    } else {
        // Create
        if (empty($id_sampah)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO jenis_sampah (nama_sampah, satuan, harga_beli) VALUES (:nama, :satuan, :harga)");
                $stmt->execute(['nama' => $nama_sampah, 'satuan' => $satuan, 'harga' => $harga_beli]);
                $pesan = "Jenis sampah baru berhasil ditambahkan.";
            } catch (Exception $e) {
                $error = "Gagal menambahkan: " . $e->getMessage();
            }
        }
        // Update
        else {
            try {
                $stmt = $pdo->prepare("UPDATE jenis_sampah SET nama_sampah = :nama, satuan = :satuan, harga_beli = :harga WHERE id_sampah = :id");
                $stmt->execute(['nama' => $nama_sampah, 'satuan' => $satuan, 'harga' => $harga_beli, 'id' => $id_sampah]);
                $pesan = "Data sampah berhasil diperbarui.";
            } catch (Exception $e) {
                $error = "Gagal memperbarui: " . $e->getMessage();
            }
        }
    }
}

// Logika untuk Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_sampah = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM jenis_sampah WHERE id_sampah = ?");
        $stmt->execute([$id_sampah]);
        $pesan = "Jenis sampah berhasil dihapus.";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
             $error = "Gagal menghapus: Jenis sampah ini sudah pernah digunakan dalam transaksi.";
        } else {
             $error = "Gagal menghapus: " . $e->getMessage();
        }
    }
}

// Ambil semua data sampah (Read)
$stmt = $pdo->query("SELECT * FROM jenis_sampah ORDER BY nama_sampah");
$sampah_list = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Jenis Sampah - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <h1 class="mt-4">Kelola Jenis Sampah</h1>

            <?php if ($pesan): ?><div class="alert alert-success"><?= $pesan ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="row">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header" id="formCardTitle"><i class="fas fa-edit"></i> Tambah Jenis Sampah</div>
                        <div class="card-body">
                            <form action="sampah.php" method="POST" id="sampahForm">
                                <input type="hidden" name="id_sampah" id="id_sampah">
                                <div class="mb-3">
                                    <label for="nama_sampah" class="form-label">Nama Sampah</label>
                                    <input type="text" class="form-control" id="nama_sampah" name="nama_sampah" required>
                                </div>
                                <div class="mb-3">
                                    <label for="satuan" class="form-label">Satuan</label>
                                    <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Contoh: kg, pcs, liter" required>
                                </div>
                                <div class="mb-3">
                                    <label for="harga_beli" class="form-label">Harga Beli (per Satuan)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" id="harga_beli" name="harga_beli" required>
                                    </div>
                                </div>
                                <button type="submit" name="submit" class="btn btn-primary">Simpan</button>
                                <button type="button" class="btn btn-secondary" onclick="clearForm()">Batal</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-table"></i> Daftar Harga Sampah</div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Sampah</th>
                                        <th>Satuan</th>
                                        <th>Harga Beli</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sampah_list as $sampah): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sampah['nama_sampah']) ?></td>
                                        <td><?= htmlspecialchars($sampah['satuan']) ?></td>
                                        <td><?= format_rupiah($sampah['harga_beli']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="editSampah(<?= htmlspecialchars(json_encode($sampah)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="sampah.php?action=delete&id=<?= $sampah['id_sampah'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus jenis sampah ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
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
    <script>
        function editSampah(data) {
            document.getElementById('formCardTitle').innerText = 'Edit Jenis Sampah';
            document.getElementById('id_sampah').value = data.id_sampah;
            document.getElementById('nama_sampah').value = data.nama_sampah;
            document.getElementById('satuan').value = data.satuan;
            document.getElementById('harga_beli').value = data.harga_beli;
            window.scrollTo(0, 0); // Scroll ke atas
        }

        function clearForm() {
            document.getElementById('formCardTitle').innerText = 'Tambah Jenis Sampah';
            document.getElementById('sampahForm').reset();
            document.getElementById('id_sampah').value = '';
        }
    </script>
</body>
</html>