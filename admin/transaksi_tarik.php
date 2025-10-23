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
                INSERT INTO transaksi_tarik (id_nasabah, jumlah_tarik, dicatat_oleh)
                VALUES (:id_nasabah, :jumlah_tarik, :id_admin)
            ");
            $stmt_tarik->execute([
                'id_nasabah' => $id_nasabah,
                'jumlah_tarik' => $jumlah_tarik,
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
            <h1 class="mt-4">Input Penarikan Saldo</h1>

            <?php if ($pesan): ?><div class="alert alert-success"><?= $pesan ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="row">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-edit"></i> Form Penarikan</div>
                        <div class="card-body">
                            <form action="transaksi_tarik.php" method="POST" id="formTarik">
                                
                                <div class="mb-3">
                                    <label for="id_nasabah" class="form-label">Nama Nasabah (Siswa)</label>
                                    <select class="form-select" id="id_nasabah" name="id_nasabah" required>
                                        <option value="">-- Pilih Nasabah --</option>
                                        <?php foreach ($nasabah_list as $nasabah): ?>
                                            <option value="<?= $nasabah['id_nasabah'] ?>">
                                                <?= htmlspecialchars($nasabah['nama_lengkap']) ?> (Saldo: <?= format_rupiah($nasabah['saldo']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="alert alert-info" id="infoSaldo" style="display: none;">
                                    Saldo saat ini: <strong id="saldoText"></strong>
                                </div>

                                <div class="mb-3">
                                    <label for="jumlah_tarik" class="form-label">Jumlah Penarikan</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Rp</span>
                                        <input type="number" class="form-control" id="jumlah_tarik" name="jumlah_tarik" required>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="submit" class="btn btn-danger">Simpan Penarikan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-history"></i> 5 Penarikan Terakhir</div>
                        <div class="card-body">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama</th>
                                        <th>Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat_tarik as $riwayat): ?>
                                    <tr>
                                        <td><?= date('d/m/y H:i', strtotime($riwayat['tanggal_tarik'])) ?></td>
                                        <td><?= htmlspecialchars($riwayat['nama_lengkap']) ?></td>
                                        <td><?= format_rupiah($riwayat['jumlah_tarik']) ?></td>
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
                dropdownParent: $('#id_nasabah').parent()
            });

            // Tampilkan saldo saat nasabah dipilih
            $('#id_nasabah').on('change', function() {
                let id = $(this).val();
                if (id && dataSaldo[id] !== undefined) {
                    let saldo = dataSaldo[id];
                    $('#saldoText').text(formatRupiah(saldo));
                    $('#infoSaldo').show();
                    // Set max penarikan
                    $('#jumlah_tarik').attr('max', saldo);
                } else {
                    $('#infoSaldo').hide();
                    $('#jumlah_tarik').removeAttr('max');
                }
            });
        });
    </script>
</body>
</html>