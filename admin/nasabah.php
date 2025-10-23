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
    <title>Kelola Nasabah - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <h1 class="mt-4">Kelola Data Nasabah</h1>

            <?php if ($pesan): ?><div class="alert alert-success"><?= $pesan ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#nasabahModal" onclick="clearForm()">
                <i class="fas fa-plus"></i> Tambah Nasabah Baru
            </button>

            <div class="card">
                <div class="card-header"><i class="fas fa-table"></i> Daftar Nasabah</div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama Lengkap</th>
                                    <th>Kelas</th>
                                    <th>Username</th>
                                    <th>Saldo</th>
                                    <th>Klaster</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($nasabah_list as $nasabah): ?>
                                <tr>
                                    <td><?= htmlspecialchars($nasabah['nis']) ?></td>
                                    <td><?= htmlspecialchars($nasabah['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($nasabah['kelas']) ?></td>
                                    <td><?= htmlspecialchars($nasabah['username']) ?></td>
                                    <td><?= format_rupiah($nasabah['saldo']) ?></td>
                                    <td><span class="badge bg-info"><?= htmlspecialchars($nasabah['nama_klaster'] ?? 'Belum') ?></span></td>
                                    <td>
                                        <button classa="btn btn-sm btn-warning" onclick="editNasabah(<?= htmlspecialchars(json_encode($nasabah)) ?>)" data-bs-toggle="modal" data-bs-target="#nasabahModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="nasabah.php?action=delete&id=<?= $nasabah['id_nasabah'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus nasabah ini? Tindakan ini akan menghapus user login-nya juga.')">
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

    <div class="modal fade" id="nasabahModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tambah Nasabah Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="nasabah.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_nasabah" id="id_nasabah">
                        
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label for="nis" class="form-label">NIS (Nomor Induk Siswa)</label>
                            <input type="text" class="form-control" id="nis" name="nis">
                        </div>
                        <div class="mb-3">
                            <label for="kelas" class="form-label">Kelas</label>
                            <input type="text" class="form-control" id="kelas" name="kelas" required>
                        </div>
                        
                        <hr>
                        <div id="authSection">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username (untuk login nasabah)</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <div class="form-text">Username ini akan dipakai siswa untuk login.</div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JS untuk mengisi form modal saat tombol edit ditekan
        function editNasabah(data) {
            document.getElementById('modalTitle').innerText = 'Edit Data Nasabah';
            document.getElementById('id_nasabah').value = data.id_nasabah;
            document.getElementById('nama_lengkap').value = data.nama_lengkap;
            document.getElementById('nis').value = data.nis;
            document.getElementById('kelas').value = data.kelas;
            
            // Sembunyikan dan non-aktifkan field username/password saat edit
            document.getElementById('authSection').style.display = 'none';
            document.getElementById('username').required = false;
            document.getElementById('password').required = false;
        }

        // JS untuk membersihkan form saat modal ditutup atau tombol "tambah" ditekan
        function clearForm() {
            document.getElementById('modalTitle').innerText = 'Tambah Nasabah Baru';
            document.getElementById('id_nasabah').value = '';
            document.getElementById('nama_lengkap').value = '';
            document.getElementById('nis').value = '';
            document.getElementById('kelas').value = '';
            
            // Tampilkan dan aktifkan field username/password
            document.getElementById('authSection').style.display = 'block';
            document.getElementById('username').required = true;
            document.getElementById('password').required = true;
        }
    </script>
</body>
</html>