<?php
// /admin/nasabah.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

$pesan = '';
$error = '';

// Logika untuk menangani form (Create & Update)
if (isset($_POST['submit'])) {
    $nama_lengkap = $_POST['nama_lengkap'];
    $nis = $_POST['nis'];
    $kelas = $_POST['kelas'];
    $id_nasabah = $_POST['id_nasabah']; // Untuk update

    // Untuk Nasabah Baru (Create)
    if (empty($id_nasabah)) {
        $username = $_POST['username'];
        // $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password
        // $password = base64_encode($_POST['password']);
        $password = $_POST['password']; // PERINGATAN: SANGAT TIDAK AMAN
        
        try {
            $pdo->beginTransaction();
            
            // 1. Buat user baru
            $stmt_user = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'nasabah')");
            $stmt_user->execute(['username' => $username, 'password' => $password]);
            $id_user = $pdo->lastInsertId();

            // 2. Buat nasabah baru
            $stmt_nasabah = $pdo->prepare("INSERT INTO nasabah (id_user, nis, nama_lengkap, kelas) VALUES (:id_user, :nis, :nama, :kelas)");
            $stmt_nasabah->execute([
                'id_user' => $id_user,
                'nis' => $nis,
                'nama' => $nama_lengkap,
                'kelas' => $kelas
            ]);

            $pdo->commit();
            $pesan = "Nasabah baru berhasil ditambahkan.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal menambahkan nasabah: " . $e->getMessage();
        }
    }
    // Untuk Update Nasabah
    else {
        try {
            $stmt = $pdo->prepare("UPDATE nasabah SET nama_lengkap = :nama, nis = :nis, kelas = :kelas WHERE id_nasabah = :id");
            $stmt->execute([
                'nama' => $nama_lengkap,
                'nis' => $nis,
                'kelas' => $kelas,
                'id' => $id_nasabah
            ]);
            $pesan = "Data nasabah berhasil diperbarui.";
        } catch (Exception $e) {
            $error = "Gagal memperbarui nasabah: " . $e->getMessage();
        }
    }
}

// Logika untuk Delete
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_nasabah = $_GET['id'];
    
    // Kita harus hapus dari tabel 'users' juga. Ambil id_user dulu.
    try {
        $stmt_get = $pdo->prepare("SELECT id_user FROM nasabah WHERE id_nasabah = ?");
        $stmt_get->execute([$id_nasabah]);
        $id_user = $stmt_get->fetchColumn();

        if ($id_user) {
            $pdo->beginTransaction();
            // Hapus nasabah (akan otomatis ON DELETE CASCADE ke transaksi jika diset)
            $stmt_del_nasabah = $pdo->prepare("DELETE FROM nasabah WHERE id_nasabah = ?");
            $stmt_del_nasabah->execute([$id_nasabah]);
            
            // Hapus user
            $stmt_del_user = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt_del_user->execute([$id_user]);
            
            $pdo->commit();
            $pesan = "Nasabah berhasil dihapus.";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        // Cek jika error karena foreign key (sudah ada transaksi)
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
             $error = "Gagal menghapus: Nasabah ini sudah memiliki riwayat transaksi. Hapus transaksinya terlebih dahulu.";
        } else {
             $error = "Gagal menghapus nasabah: " . $e->getMessage();
        }
    }
}

// Ambil semua data nasabah untuk ditampilkan (Read)
$stmt = $pdo->query("
    SELECT n.id_nasabah, n.nis, n.nama_lengkap, n.kelas, n.saldo, u.username, ki.nama_klaster
    FROM nasabah n
    JOIN users u ON n.id_user = u.id_user
    LEFT JOIN klaster_info ki ON n.id_klaster = ki.id_klaster
    ORDER BY n.nama_lengkap
");
$nasabah_list = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Nasabah - Bank Sampah Bahrul Ulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/nasabah.css">
</head>
<body>
<?php include 'sidebar/sidebar.php'; ?>

<div class="content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fas fa-users text-primary me-2"></i> Kelola Data Nasabah</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nasabahModal" onclick="clearForm()">
                <i class="fas fa-plus me-2"></i> Tambah Nasabah Baru
            </button>
        </div>
        
        <?php if ($pesan): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $pesan ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <?php
        $total_nasabah = count($nasabah_list);
        $total_saldo = array_sum(array_column($nasabah_list, 'saldo'));
        
        $stmt_stats = $pdo->query("SELECT COUNT(*) as total_transaksi FROM transaksi_setor");
        $total_transaksi = $stmt_stats->fetch()['total_transaksi'];
        ?>
        
        <div class="user-stats">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($total_nasabah) ?></h3>
                    <p>Total Nasabah</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <h3><?= format_rupiah($total_saldo) ?></h3>
                    <p>Total Saldo Nasabah</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($total_transaksi) ?></h3>
                    <p>Total Transaksi</p>
                </div>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="row align-items-center">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Cari nama, NIS, kelas...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterKelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php
                        $kelas_unique = array_unique(array_column($nasabah_list, 'kelas'));
                        foreach ($kelas_unique as $kelas) {
                            echo "<option value='".htmlspecialchars($kelas)."'>".htmlspecialchars($kelas)."</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Nasabah Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Daftar Nasabah
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="nasabahTable">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                                <th>Username</th>
                                <th>Saldo</th>
                                <th>Klaster</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nasabah_list as $nasabah): ?>
                            <tr>
                                <td><?= htmlspecialchars($nasabah['nis']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($nasabah['nama_lengkap']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($nasabah['kelas']) ?></td>
                                <td><?= htmlspecialchars($nasabah['username']) ?></td>
                                <td><?= format_rupiah($nasabah['saldo']) ?></td>
                                <td>
                                    <?php if ($nasabah['nama_klaster']): ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($nasabah['nama_klaster']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning" onclick='editNasabah(<?= htmlspecialchars(json_encode($nasabah)) ?>)' data-bs-toggle="modal" data-bs-target="#nasabahModal" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="detail_nasabah.php?id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-info text-white" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="nasabah.php?action=delete&id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus nasabah ini? Tindakan ini akan menghapus user login-nya juga.')" title="Hapus">
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
        
        <?php include 'footer/footer.php'; ?>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="nasabahModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-user-plus me-2 text-primary"></i>
                    Tambah Nasabah Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="nasabah.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_nasabah" id="id_nasabah">
                    
                    <div class="mb-3">
                        <label for="nama_lengkap" class="form-label">
                            <i class="fas fa-user text-primary me-1"></i> Nama Lengkap
                        </label>
                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label for="nis" class="form-label">
                            <i class="fas fa-id-card text-primary me-1"></i> NIS (Nomor Induk Siswa)
                        </label>
                        <input type="text" class="form-control" id="nis" name="nis">
                    </div>
                    <div class="mb-3">
                        <label for="kelas" class="form-label">
                            <i class="fas fa-school text-primary me-1"></i> Kelas
                        </label>
                        <input type="text" class="form-control" id="kelas" name="kelas" required>
                    </div>
                    
                    <div id="authSection">
                        <hr>
                        <h6 class="mb-3 text-muted">Informasi Akun</h6>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user-shield text-primary me-1"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">Username ini akan dipakai siswa untuk login.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock text-primary me-1"></i> Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="showPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Perhatian: Pastikan password yang dibuat mudah diingat oleh siswa.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
    // DataTables initialization
    $(document).ready(function() {
        var table = $('#nasabahTable').DataTable({
            "language": {
                "lengthMenu": "Tampilkan _MENU_ data per halaman",
                "zeroRecords": "Tidak ada data nasabah",
                "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                "infoEmpty": "Tidak ada data nasabah",
                "infoFiltered": "(difilter dari _MAX_ total data)",
                "search": "Cari:",
                "paginate": {
                    "first": "Pertama",
                    "last": "Terakhir",
                    "next": "Selanjutnya",
                    "previous": "Sebelumnya"
                }
            },
            "pageLength": 10,
            "responsive": true,
            "order": [[1, 'asc']] // Sort by name column
        });
        
        // Custom filter
        $('#searchInput').on('keyup', function() {
            table.search(this.value).draw();
        });
        
        $('#filterKelas').on('change', function() {
            table.column(2).search(this.value).draw(); // 2 is the "Kelas" column index
        });
        
        // Show/hide password
        $('#showPassword').on('click', function() {
            var passwordField = $('#password');
            var passwordFieldType = passwordField.attr('type');
            
            if (passwordFieldType === 'password') {
                passwordField.attr('type', 'text');
                $(this).html('<i class="fas fa-eye-slash"></i>');
            } else {
                passwordField.attr('type', 'password');
                $(this).html('<i class="fas fa-eye"></i>');
            }
        });
    });
    
    // Edit nasabah function
    function editNasabah(data) {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit me-2 text-primary"></i> Edit Data Nasabah';
        document.getElementById('id_nasabah').value = data.id_nasabah;
        document.getElementById('nama_lengkap').value = data.nama_lengkap;
        document.getElementById('nis').value = data.nis;
        document.getElementById('kelas').value = data.kelas;
        
        // Sembunyikan dan non-aktifkan field username/password saat edit
        document.getElementById('authSection').style.display = 'none';
        document.getElementById('username').required = false;
        document.getElementById('password').required = false;
    }

    // Clear form function
    function clearForm() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2 text-primary"></i> Tambah Nasabah Baru';
        document.getElementById('id_nasabah').value = '';
        document.getElementById('nama_lengkap').value = '';
        document.getElementById('nis').value = '';
        document.getElementById('kelas').value = '';
        
        // Tampilkan dan aktifkan field username/password
        document.getElementById('authSection').style.display = 'block';
        document.getElementById('username').required = true;
        document.getElementById('password').required = true;
    }
    
    // Warn before leaving the page with unsaved changes
    const form = document.querySelector('form');
    let formChanged = false;
    
    form.addEventListener('input', function() {
        formChanged = true;
    });
    
    form.addEventListener('submit', function() {
        formChanged = false;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
</script>
</body>
</html>