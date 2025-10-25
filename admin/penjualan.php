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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(240, 147, 251, 0.4);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><polygon points="25,5 45,40 5,40" fill="rgba(255,255,255,0.05)"/></svg>') repeat;
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sales-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInLeft 0.6s ease-out;
        }

        .sales-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #f093fb, #f5576c);
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

        .history-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInRight 0.6s ease-out;
            margin-bottom: 2rem;
        }

        .history-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #667eea, #764ba2);
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

        .pengepul-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 25px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            overflow: hidden;
            position: relative;
            animation: slideInUp 0.6s ease-out;
        }

        .pengepul-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #a8edea, #fed6e3);
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

        .card-header-sales {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .card-header-history {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .card-header-pengepul {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #2c3e50;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.2rem;
        }

        .form-floating {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating .form-control,
        .form-floating .form-select {
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 15px;
            padding: 1.25rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
        }

        .form-floating .form-control:focus,
        .form-floating .form-select:focus {
            border-color: #f093fb;
            box-shadow: 0 0 0 0.25rem rgba(240, 147, 251, 0.25);
            background: white;
            transform: translateY(-2px);
        }

        .form-floating label {
            color: #6c757d;
            font-weight: 500;
            padding-left: 1rem;
        }

        .btn-sell {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(240, 147, 251, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-sell::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }

        .btn-sell:hover::before {
            left: 100%;
        }

        .btn-sell:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(240, 147, 251, 0.5);
        }

        .btn-add-pengepul {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(168, 237, 234, 0.3);
        }

        .btn-add-pengepul:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(168, 237, 234, 0.4);
            color: #2c3e50;
        }

        .table-modern {
            margin: 0;
            background: transparent;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,249,250,0.9) 100%);
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
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
        }

        .table-modern tbody tr:hover {
            background: rgba(255,255,255,0.95);
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border: none;
            vertical-align: middle;
            font-weight: 500;
        }

        .revenue-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }

        .date-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .weight-badge {
            background: rgba(255,255,255,0.9);
            color: #2c3e50;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            border: 2px solid #a8edea;
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
            background: linear-gradient(135deg, rgba(212, 237, 218, 0.9) 0%, rgba(195, 230, 203, 0.9) 100%);
            color: #155724;
            border-left: 6px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(248, 215, 218, 0.9) 0%, rgba(245, 198, 203, 0.9) 100%);
            color: #721c24;
            border-left: 6px solid #dc3545;
        }

        .icon-badge {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-right: 1rem;
        }

        .icon-sales {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(240, 147, 251, 0.4);
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

        .pengepul-input {
            border: 2px solid rgba(168, 237, 234, 0.5);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.9);
            transition: all 0.3s ease;
        }

        .pengepul-input:focus {
            border-color: #a8edea;
            box-shadow: 0 0 0 0.2rem rgba(168, 237, 234, 0.25);
            background: white;
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
                padding: 2rem 1rem;
            }
            
            .sales-card, .history-card, .pengepul-card {
                margin-bottom: 2rem;
            }

            .icon-badge {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
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
                        <h1 class="mb-0 display-5">
                            <i class="fas fa-truck-loading me-3"></i>
                            Penjualan Sampah ke Pengepul
                        </h1>
                        <p class="mb-0 mt-2 opacity-75 fs-5">Kelola penjualan sampah dan data pengepul dengan efisien</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="icon-badge icon-sales">
                            <i class="fas fa-handshake"></i>
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
                <!-- Sales Form Section -->
                <div class="col-lg-6 mb-4">
                    <div class="sales-card">
                        <div class="card-header-sales">
                            <i class="fas fa-cash-register me-2"></i>
                            Catat Penjualan Sampah
                        </div>
                        <div class="card-body p-4">
                            <form action="penjualan.php" method="POST" id="formPenjualan">
                                
                                <div class="form-floating">
                                    <select name="id_sampah" class="form-select" id="id_sampah" required>
                                        <option value="">-- Pilih Jenis Sampah --</option>
                                        <?php foreach ($sampah_list as $s): ?>
                                            <option value="<?= $s['id_sampah'] ?>">
                                                <?= htmlspecialchars($s['nama_sampah']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_sampah">
                                        <i class="fas fa-recycle me-2"></i>Jenis Sampah
                                    </label>
                                </div>

                                <div class="form-floating">
                                    <input type="number" step="0.01" name="berat" class="form-control" id="berat" placeholder="Berat" required>
                                    <label for="berat">
                                        <i class="fas fa-weight me-2"></i>Berat (kg)
                                    </label>
                                </div>

                                <div class="form-floating">
                                    <input type="number" name="harga_jual_per_kg" class="form-control" id="harga_jual" placeholder="Harga Jual" required>
                                    <label for="harga_jual">
                                        <i class="fas fa-tags me-2"></i>Harga Jual (per kg)
                                    </label>
                                </div>

                                <div class="form-floating">
                                    <select name="id_pengepul" class="form-select" id="id_pengepul" required>
                                        <option value="">-- Pilih Pengepul --</option>
                                        <?php foreach ($pengepul_list as $p): ?>
                                            <option value="<?= $p['id_pengepul'] ?>">
                                                <?= htmlspecialchars($p['nama_pengepul']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_pengepul">
                                        <i class="fas fa-user-tie me-2"></i>Dijual Ke Pengepul
                                    </label>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" name="submit_jual" class="btn btn-sell">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        Simpan Penjualan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History Section -->
                <div class="col-lg-6">
                    <div class="history-card">
                        <div class="card-header-history d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-history me-2"></i>
                                5 Penjualan Terakhir
                            </span>
                            <span class="badge bg-light text-dark rounded-pill px-3 py-2">
                                <?= count($riwayat_jual) ?> Transaksi
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($riwayat_jual) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-calendar me-1"></i>Tanggal</th>
                                                <th><i class="fas fa-recycle me-1"></i>Sampah</th>
                                                <th><i class="fas fa-weight me-1"></i>Berat</th>
                                                <th><i class="fas fa-money-bill me-1"></i>Total</th>
                                                <th><i class="fas fa-user-tie me-1"></i>Pengepul</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($riwayat_jual as $r): ?>
                                            <tr>
                                                <td>
                                                    <span class="date-badge">
                                                        <?= date('d/m/y', strtotime($r['tanggal_jual'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($r['nama_sampah']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="weight-badge">
                                                        <?= $r['berat'] ?> kg
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="revenue-badge">
                                                        <?= format_rupiah($r['total_pendapatan']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($r['nama_pengepul']) ?>
                                                    </small>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h5>Belum ada riwayat penjualan</h5>
                                    <p>Mulai catat penjualan sampah pertama Anda</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pengepul Management -->
                    <div class="pengepul-card">
                        <div class="card-header-pengepul">
                            <i class="fas fa-users me-2"></i>
                            Kelola Data Pengepul
                        </div>
                        <div class="card-body p-4">
                            <!-- Add Pengepul Form -->
                            <form action="penjualan.php" method="POST" class="mb-4">
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <input type="text" name="nama_pengepul" class="pengepul-input form-control" placeholder="Nama Pengepul Baru" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="kontak_pengepul" class="pengepul-input form-control" placeholder="Kontak (WA)">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" name="submit_pengepul" class="btn btn-add-pengepul w-100">
                                            <i class="fas fa-plus me-1"></i>Tambah
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Pengepul List -->
                            <?php if (count($pengepul_list) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-user me-1"></i>Nama Pengepul</th>
                                                <th><i class="fas fa-phone me-1"></i>Kontak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pengepul_list as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($p['nama_pengepul']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?= htmlspecialchars($p['kontak_pengepul']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-plus"></i>
                                    <h6>Belum ada data pengepul</h6>
                                    <p>Tambahkan pengepul pertama Anda</p>
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

        // Form submission animation
        document.getElementById('formPenjualan').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            submitBtn.disabled = true;
        });

        // Auto calculate total when inputs change
        const beratInput = document.getElementById('berat');
        const hargaInput = document.getElementById('harga_jual');
        
        function updateTotal() {
            const berat = parseFloat(beratInput.value) || 0;
            const harga = parseFloat(hargaInput.value) || 0;
            const total = berat * harga;
            
            if (total > 0) {
                // You can add a total display element here if needed
                console.log('Total:', total);
            }
        }

        beratInput.addEventListener('input', updateTotal);
        hargaInput.addEventListener('input', updateTotal);
    </script>
</body>
</html>