<?php
// /admin/transaksi_tarik.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

$pesan = '';
$error = '';

// Ambil data nasabah untuk dropdown (beserta saldonya)
$stmt_nasabah = $pdo->query("SELECT id_nasabah, nama_lengkap, kelas, saldo FROM nasabah ORDER BY nama_lengkap");
$nasabah_list = $stmt_nasabah->fetchAll();

// Buat array JS untuk lookup saldo
$saldo_js_array = [];
foreach ($nasabah_list as $n) {
    $saldo_js_array[$n['id_nasabah']] = $n['saldo'];
}

// Logika Proses Form
if (isset($_POST['submit'])) {
    $id_nasabah = $_POST['id_nasabah'];
    $jumlah_tarik = (int) $_POST['jumlah_tarik'];
    $catatan = $_POST['catatan'] ?? null;
    $id_admin = $_SESSION['user_id'];

    // Validasi
    $stmt_saldo = $pdo->prepare("SELECT saldo FROM nasabah WHERE id_nasabah = ?");
    $stmt_saldo->execute([$id_nasabah]);
    $saldo_sekarang = $stmt_saldo->fetchColumn();

    if ($jumlah_tarik <= 0) {
        $error = "Jumlah penarikan harus lebih dari 0.";
    } else if ($jumlah_tarik > $saldo_sekarang) {
        $error = "Gagal. Saldo nasabah ( " . format_rupiah($saldo_sekarang) . " ) tidak mencukupi untuk penarikan sebesar " . format_rupiah($jumlah_tarik) . ".";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Masukkan ke tabel transaksi_tarik
            $stmt_tarik = $pdo->prepare("
                INSERT INTO transaksi_tarik (id_nasabah, jumlah_tarik, catatan, dicatat_oleh)
                VALUES (:id_nasabah, :jumlah_tarik, :catatan, :id_admin)
            ");
            $stmt_tarik->execute([
                'id_nasabah' => $id_nasabah,
                'jumlah_tarik' => $jumlah_tarik,
                'catatan' => $catatan,
                'id_admin' => $id_admin
            ]);

            // 2. Update (KURANGI) saldo nasabah
            $stmt_update_saldo = $pdo->prepare("
                UPDATE nasabah SET saldo = saldo - :jumlah WHERE id_nasabah = :id_nasabah
            ");
            $stmt_update_saldo->execute([
                'jumlah' => $jumlah_tarik,
                'id_nasabah' => $id_nasabah
            ]);

            $pdo->commit();
            $pesan = "Penarikan saldo berhasil dicatat. Saldo nasabah telah diperbarui.";
            // Refresh data saldo di array JS
            $saldo_js_array[$id_nasabah] -= $jumlah_tarik;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal mencatat penarikan: " . $e->getMessage();
        }
    }
}

// Ambil riwayat 5 penarikan terakhir
$stmt_riwayat = $pdo->query("
    SELECT tt.*, n.nama_lengkap
    FROM transaksi_tarik tt
    JOIN nasabah n ON tt.id_nasabah = n.id_nasabah
    ORDER BY tt.tanggal_tarik DESC
    LIMIT 5
");
$riwayat_tarik = $stmt_riwayat->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tarik Saldo - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
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
            background: linear-gradient(90deg, #dc3545, #e74c3c);
        }

        .card-header-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
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
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
            background: white;
        }

        .form-floating label {
            color: #6c757d;
            font-weight: 500;
        }

        .saldo-info {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .input-group-text {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            border: none;
            font-weight: 600;
        }

        .btn-withdraw {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-withdraw::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn-withdraw:hover::before {
            left: 100%;
        }

        .btn-withdraw:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
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
            color: #dc3545;
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
            background: linear-gradient(135deg, #f8f9fa 0%, #ffe6e6 100%);
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

        .icon-danger {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
        }

        .icon-info {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg, #dc3545, #e74c3c);
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

        .rapi 
        {
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
                            <i class="fas fa-upload me-3"></i>
                            Input Penarikan Saldo
                        </h1>
                        <p class="mb-0 mt-2 opacity-75">Proses penarikan saldo nasabah dengan aman</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="icon-badge icon-danger">
                            <i class="fas fa-money-bill-wave"></i>
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
                        <div class="card-header-danger">
                            <i class="fas fa-edit me-2"></i>
                            Form Penarikan Saldo
                        </div>
                        <div class="card-body p-4">
                            <form action="transaksi_tarik.php" method="POST" id="formTarik">
                                
                                <div class="form-floating">
                                    <select class="form-select" id="id_nasabah" name="id_nasabah" required>
                                        <option value="">-- Pilih Nasabah --</option>
                                        <?php foreach ($nasabah_list as $nasabah): ?>
                                            <option value="<?= $nasabah['id_nasabah'] ?>">
                                                <?= htmlspecialchars($nasabah['nama_lengkap']) ?> (Saldo: <?= format_rupiah($nasabah['saldo']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="id_nasabah">
                                        <i class="fas fa-user me-2"></i>Nama Nasabah (Siswa)
                                    </label>
                                </div>
                                
                                <div class="saldo-info" id="infoSaldo">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-wallet me-3 fs-4"></i>
                                        <div>
                                            <strong>Saldo Tersedia</strong><br>
                                            <span id="saldoText" class="fs-5"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="jumlah_tarik" class="form-label">
                                        <i class="fas fa-money-bill me-2"></i>Jumlah Penarikan
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-rupiah-sign"></i>
                                        </span>
                                        <input type="number" class="form-control" id="jumlah_tarik" name="jumlah_tarik" required>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="catatan" class="form-label">
                                        <i class="fas fa-sticky-note me-2"></i>Alasan Penarikan
                                    </label>
                                    <textarea class="form-control" id="catatan" name="catatan" rows="3" 
                                              style="border: 2px solid #e9ecef; border-radius: 12px; background: #f8f9fa;"
                                              placeholder="Masukkan alasan penarikan (opsional)"></textarea>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit" class="btn btn-withdraw">
                                        <i class="fas fa-save me-2"></i>
                                        Simpan Penarikan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- History Section -->
                <div class="col-lg-7">
                    <div class="history-card fade-in">
                        <div class="card-header-danger d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-history me-2"></i>
                                5 Penarikan Terakhir
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
                                            <th><i class="fas fa-money-bill me-1"></i>Jumlah</th>
                                            <th><i class="fas fa-sticky-note me-1"></i>Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($riwayat_tarik) > 0): ?>
                                            <?php foreach ($riwayat_tarik as $riwayat): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?= date('d/m/y H:i', strtotime($riwayat['tanggal_tarik'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($riwayat['nama_lengkap']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="text-danger fw-bold">
                                                        <?= format_rupiah($riwayat['jumlah_tarik']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($riwayat['catatan'] ?? 'Tidak ada catatan') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5">
                                                    <i class="fas fa-info-circle text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                                                    <span class="text-muted">Belum ada riwayat penarikan</span>
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
        // Data saldo dari PHP
        const dataSaldo = <?= json_encode($saldo_js_array) ?>;
        
        function formatRupiah(angka) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(angka);
        }

        $(document).ready(function() {
            // Inisialisasi Select2
            $('#id_nasabah').select2({
                theme: "bootstrap-5",
                dropdownParent: $('#id_nasabah').parent(),
                placeholder: "Cari nama nasabah...",
                allowClear: true
            });

            // Tampilkan saldo saat nasabah dipilih
            $('#id_nasabah').on('change', function() {
                let id = $(this).val();
                if (id && dataSaldo[id] !== undefined) {
                    let saldo = dataSaldo[id];
                    $('#saldoText').text(formatRupiah(saldo));
                    $('#infoSaldo').show();
                    $('#jumlah_tarik').attr('max', saldo);
                } else {
                    $('#infoSaldo').hide();
                    $('#jumlah_tarik').removeAttr('max');
                }
            });

            // Auto dismiss alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>