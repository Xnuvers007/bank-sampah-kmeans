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
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #2e8b57 0%, #3cb371 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(46, 139, 87, 0.3);
        }

        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #2e8b57, #3cb371, #20c997);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .form-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
        }

        .form-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #2e8b57, #3cb371);
        }

        .card-header-custom {
            background: linear-gradient(135deg, #2e8b57 0%, #3cb371 100%);
            color: white;
            padding: 1.5rem;
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
            border-radius: 12px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-floating .form-control:focus,
        .form-floating .form-select:focus {
            border-color: #2e8b57;
            box-shadow: 0 0 0 0.2rem rgba(46, 139, 87, 0.25);
            background: white;
        }

        .form-floating label {
            color: #6c757d;
            font-weight: 500;
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .input-group-text {
            background: linear-gradient(135deg, #2e8b57 0%, #3cb371 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2e8b57 0%, #3cb371 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 139, 87, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 139, 87, 0.4);
        }

        .history-card {
            background: white;
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            color: #2e8b57;
            font-weight: 600;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table-modern tbody tr {
            transition: all 0.3s ease;
            border: none;
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f5e8 100%);
            transform: scale(1.01);
        }

        .table-modern tbody td {
            padding: 1rem;
            border: none;
            vertical-align: middle;
        }

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .icon-badge {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .icon-primary {
            background: linear-gradient(135deg, #2e8b57 0%, #3cb371 100%);
            color: white;
        }

        .icon-info {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, #2e8b57, #3cb371);
            border-radius: 1px;
            margin: 1.5rem 0;
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

        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @media (max-width: 768px) {
            .page-header {
                text-align: center;
                padding: 1.5rem 1rem;
            }
            
            .form-card, .history-card {
                margin-bottom: 1.5rem;
            }
        }
        .rapi {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
                        <h1 class="mb-0">
                            <i class="fas fa-download me-3"></i>
                            Input Setoran Sampah
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">Kelola transaksi setoran sampah nasabah dengan mudah</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="icon-badge icon-primary">
                            <i class="fas fa-recycle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($pesan): ?>
                <div class="alert alert-success alert-modern fade-in">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $pesan ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Form Section -->
                <div class="col-lg-5 mb-4">
                    <div class="form-card fade-in">
                        <div class="card-header-custom">
                            <i class="fas fa-edit me-2"></i>
                            Form Setoran Sampah
                        </div>
                        <div class="card-body p-4">
                            <form action="transaksi_setor.php" method="POST" id="formSetor">
                                
                                <div class="form-floating">
                                    <select class="form-select" id="id_nasabah" name="id_nasabah" required>
                                        <option value="">-- Pilih Nasabah --</option>
                                        <?php foreach ($nasabah_list as $nasabah): ?>
                                            <option value="<?= $nasabah['id_nasabah'] ?>">
                                                <?= htmlspecialchars($nasabah['nama_lengkap']) ?> (<?= htmlspecialchars($nasabah['kelas']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_nasabah">
                                        <i class="fas fa-user me-2"></i>Nama Nasabah (Siswa)
                                    </label>
                                </div>

                                <div class="form-floating">
                                    <select class="form-select" id="id_sampah" name="id_sampah" required>
                                        <option value="">-- Pilih Jenis Sampah --</option>
                                        <?php foreach ($sampah_list as $sampah): ?>
                                            <option value="<?= $sampah['id_sampah'] ?>">
                                                <?= htmlspecialchars($sampah['nama_sampah']) ?> (<?= format_rupiah($sampah['harga_beli']) ?>/<?= $sampah['satuan'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_sampah">
                                        <i class="fas fa-trash me-2"></i>Jenis Sampah
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="berat" class="form-label">
                                        <i class="fas fa-weight me-2"></i>Jumlah / Berat
                                    </label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control" id="berat" name="berat" required>
                                        <span class="input-group-text" id="satuan_text">--</span>
                                    </div>
                                </div>
                                
                                <div class="divider"></div>

                                <div class="mb-4">
                                    <label for="total_harga" class="form-label">
                                        <i class="fas fa-calculator me-2"></i>Total Harga (Otomatis)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-rupiah-sign"></i>
                                        </span>
                                        <input type="number" class="form-control" id="total_harga" name="total_harga" readonly required>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit" class="btn btn-submit">
                                        <i class="fas fa-save me-2"></i>
                                        Simpan Transaksi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History Section -->
                <div class="col-lg-7">
                    <div class="history-card fade-in">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-history me-2"></i>
                                5 Setoran Terakhir
                            </span>
                            <div class="icon-badge icon-info">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-calendar me-1"></i>Tanggal</th>
                                            <th><i class="fas fa-user me-1"></i>Nama</th>
                                            <th><i class="fas fa-trash me-1"></i>Sampah</th>
                                            <th><i class="fas fa-weight me-1"></i>Berat</th>
                                            <th><i class="fas fa-money-bill me-1"></i>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($riwayat_setor) > 0): ?>
                                            <?php foreach ($riwayat_setor as $riwayat): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?= date('d/m/y H:i', strtotime($riwayat['tanggal_setor'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($riwayat['nama_lengkap']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?= htmlspecialchars($riwayat['nama_sampah']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= $riwayat['berat'] ?> kg</strong>
                                                </td>
                                                <td>
                                                    <span class="text-success fw-bold">
                                                        <?= format_rupiah($riwayat['total_harga']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-5">
                                                    <i class="fas fa-info-circle text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                                                    <span class="text-muted">Belum ada riwayat setoran</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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
            // Inisialisasi Select2
            $('#id_nasabah').select2({
                theme: "bootstrap-5",
                dropdownParent: $('#id_nasabah').parent(),
                placeholder: "Cari nama nasabah...",
                allowClear: true
            });
            
            $('#id_sampah').select2({
                theme: "bootstrap-5",
                dropdownParent: $('#id_sampah').parent(),
                placeholder: "Cari jenis sampah...",
                allowClear: true
            });

            // Pasang event listener
            $('#id_sampah, #berat').on('change keyup', hitungTotal);

            // Auto dismiss alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>