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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }

        .form-card {
            background: white;
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            animation: slideInLeft 0.6s ease-out;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
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

        .data-card {
            background: white;
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            animation: slideInRight 0.6s ease-out;
        }

        .data-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #f093fb, #f5576c);
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

        .card-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
            position: relative;
        }

        .card-header-pink {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .form-floating {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating .form-control,
        .form-floating .form-select {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.25rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: auto;
        }

        .form-floating .form-control:focus,
        .form-floating .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
            background: white;
            transform: translateY(-2px);
        }

        .form-floating label {
            color: #6c757d;
            font-weight: 500;
            padding-left: 1rem;
        }

        .input-group-modern {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }

        .input-group-modern .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1.25rem 1rem;
        }

        .input-group-modern .form-control {
            border: 2px solid #e9ecef;
            border-left: none;
            font-size: 1rem;
            padding: 1.25rem 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .input-group-modern .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
            background: white;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-save::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }

        .btn-save:hover::before {
            left: 100%;
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            color: #8b4513;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(252, 182, 159, 0.3);
        }

        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(252, 182, 159, 0.4);
            color: #8b4513;
        }

        .table-modern {
            margin: 0;
            background: white;
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
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }

        .table-modern tbody tr {
            transition: all 0.3s ease;
            border: none;
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #fff5f5 100%);
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border: none;
            vertical-align: middle;
            font-weight: 500;
        }

        .price-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }

        .action-btn {
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            margin: 0 0.25rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-edit {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
            box-shadow: 0 4px 15px rgba(252, 182, 159, 0.3);
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(252, 182, 159, 0.4);
            color: #8b4513;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #dc3545;
            box-shadow: 0 4px 15px rgba(255, 154, 158, 0.3);
            text-decoration: none;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 154, 158, 0.4);
            color: #dc3545;
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
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 6px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 6px solid #dc3545;
        }

        .icon-badge {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-right: 1rem;
        }

        .icon-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .page-header {
                text-align: center;
                padding: 1.5rem 1rem;
            }
            
            .form-card, .data-card {
                margin-bottom: 2rem;
            }

            .table-responsive {
                border-radius: 15px;
                overflow: hidden;
            }
        }
        .rapi {
            padding-left: 7%;
        }
    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header fade-in">
                <div class="row align-items-center">
                    <div class="col-md-8 rapi">
                        <h1 class="mb-0 display-6">
                            <i class="fas fa-recycle me-3"></i>
                            Kelola Jenis Sampah
                        </h1>
                        <p class="mb-0 mt-2 opacity-75 fs-5">Manajemen kategori dan harga sampah dengan mudah</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="icon-badge icon-primary">
                            <i class="fas fa-leaf"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($pesan): ?>
                <div class="alert alert-success alert-modern">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $pesan ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Form Section -->
                <div class="col-lg-4 mb-4">
                    <div class="form-card">
                        <div class="card-header-custom" id="formCardTitle">
                            <i class="fas fa-plus me-2"></i>
                            Tambah Jenis Sampah
                        </div>
                        <div class="card-body p-4">
                            <form action="sampah.php" method="POST" id="sampahForm">
                                <input type="hidden" name="id_sampah" id="id_sampah">
                                
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="nama_sampah" name="nama_sampah" placeholder="Nama Sampah" required>
                                    <label for="nama_sampah">
                                        <i class="fas fa-trash me-2"></i>Nama Sampah
                                    </label>
                                </div>

                                <div class="form-floating">
                                    <input type="text" class="form-control" id="satuan" name="satuan" placeholder="Satuan" required>
                                    <label for="satuan">
                                        <i class="fas fa-weight me-2"></i>Satuan (kg, pcs, liter)
                                    </label>
                                </div>
                                
                                <div class="input-group-modern">
                                    <span class="input-group-text">
                                        <i class="fas fa-rupiah-sign me-1"></i>
                                        Rp
                                    </span>
                                    <input type="number" class="form-control" id="harga_beli" name="harga_beli" placeholder="Harga per satuan" required>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i>
                                        Simpan Data
                                    </button>
                                    <button type="button" class="btn btn-cancel" onclick="clearForm()">
                                        <i class="fas fa-times me-2"></i>
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Data Section -->
                <div class="col-lg-8">
                    <div class="data-card">
                        <div class="card-header-pink d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-table me-2"></i>
                                Daftar Harga Sampah
                            </span>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                <?= count($sampah_list) ?> Item
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($sampah_list) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-tag me-1"></i>Nama Sampah</th>
                                                <th><i class="fas fa-balance-scale me-1"></i>Satuan</th>
                                                <th><i class="fas fa-money-bill me-1"></i>Harga Beli</th>
                                                <th><i class="fas fa-cogs me-1"></i>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sampah_list as $sampah): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($sampah['nama_sampah']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark rounded-pill">
                                                        <?= htmlspecialchars($sampah['satuan']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="price-badge">
                                                        <?= format_rupiah($sampah['harga_beli']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="action-btn btn-edit" onclick="editSampah(<?= htmlspecialchars(json_encode($sampah)) ?>)">
                                                        <i class="fas fa-edit me-1"></i>Edit
                                                    </button>
                                                    <a href="sampah.php?action=delete&id=<?= $sampah['id_sampah'] ?>" 
                                                       class="action-btn btn-delete" 
                                                       onclick="return confirm('Yakin ingin menghapus jenis sampah ini?')">
                                                        <i class="fas fa-trash me-1"></i>Hapus
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h5>Belum ada data sampah</h5>
                                    <p>Tambahkan jenis sampah pertama Anda untuk memulai</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editSampah(data) {
            document.getElementById('formCardTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Jenis Sampah';
            document.getElementById('id_sampah').value = data.id_sampah;
            document.getElementById('nama_sampah').value = data.nama_sampah;
            document.getElementById('satuan').value = data.satuan;
            document.getElementById('harga_beli').value = data.harga_beli;
            
            // Smooth scroll to form
            document.querySelector('.form-card').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
            
            // Focus on nama sampah
            setTimeout(() => {
                document.getElementById('nama_sampah').focus();
            }, 500);
        }

        function clearForm() {
            document.getElementById('formCardTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Tambah Jenis Sampah';
            document.getElementById('sampahForm').reset();
            document.getElementById('id_sampah').value = '';
        }

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
        }, 5000);

        // Form validation animation
        document.getElementById('sampahForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Menyimpan...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>