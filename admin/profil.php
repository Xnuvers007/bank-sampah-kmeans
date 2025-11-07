<?php
// /admin/profil.php
require '../config/db.php';
require '../config/functions.php';
require '../config/password_helpers.php';

// Wajibkan login sebagai admin
require_login('admin');

$pesan = '';
$error = '';
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Proses ganti password
if (isset($_POST['submit_password'])) {
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    // Basic empty check
    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password)) {
        $error = "Semua field harus diisi.";
    }

    // Gunakan PasswordPolicy::validate yang mengembalikan array errors
    if (empty($error)) {
        $errs_new = PasswordPolicy::validate($password_baru);
        if (!empty($errs_new)) {
            $error = implode(' ', $errs_new); // gabungkan pesan error jadi string
        }
    }

    // Cukup cek kecocokan konfirmasi (jangan panggil validate lagi untuk konfirmasi)
    if (empty($error) && $password_baru !== $konfirmasi_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    }

    // Ambil password saat ini dari DB (plain text, tidak aman!)
    if (empty($error)) {
        // Ambil password saat ini dari DB
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id_user = ?");
        $stmt->execute([$user_id]);
        $current_password_db = $stmt->fetchColumn();

        if (!password_verify($password_lama, $current_password_db)) {
            $error = "Password lama yang Anda masukkan salah.";
        } else {
            try {
                $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                $stmt_update->execute([$hashed_password, $user_id]);
                $pesan = "Password berhasil diperbarui.";
            } catch (Exception $e) {
                $error = "Gagal memperbarui password: " . $e->getMessage();
            }
        }
    }
}

// Ambil data user (misal tanggal join)
$stmt_user = $pdo->prepare("SELECT created_at FROM users WHERE id_user = ?");
$stmt_user->execute([$user_id]);
$user_data = $stmt_user->fetch();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/footer.css">

    <style>
        /* Style tambahan khusus untuk halaman profil jika diperlukan */
        .profile-card {
            max-width: 600px;
            margin: 30px auto;
        }
    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">

            <div class="page-header">
                <h1><i class="fas fa-id-card text-primary me-2"></i> Profil Admin</h1>
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

            <div class="card profile-card">
                <div class="card-header">
                    <i class="fas fa-user-circle"></i> Informasi Akun
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-4">Username</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($username) ?></dd>

                        <dt class="col-sm-4">Role</dt>
                        <dd class="col-sm-8"><span class="badge bg-primary"><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span></dd>

                        <dt class="col-sm-4">Bergabung Sejak</dt>
                        <dd class="col-sm-8"><?= date('d F Y H:i', strtotime($user_data['created_at'])) ?></dd>
                    </dl>
                    <hr>
                    <h5 class="mb-3">Ganti Password</h5>
                    <form action="profil.php" method="POST">
                        <div class="mb-3">
                            <label for="password_lama" class="form-label">Password Lama</label>
                            <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                        </div>
                        <div class="mb-3">
                            <label for="password_baru" class="form-label">Password Baru</label>
                            <input type="password" class="form-control" id="password_baru" name="password_baru" required>
                            <div class="form-text">Minimal 8 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password" required>
                        </div>
                         <!-- <div class="text-muted mb-3 small">
                             <i class="fas fa-exclamation-triangle text-warning"></i> Menggunakan password plain text sangat tidak aman. Sebaiknya gunakan metode hashing.
                         </div> -->
                        <button type="submit" name="submit_password" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Simpan Password Baru
                        </button>
                    </form>
                </div>
            </div>

        <?php include 'footer/footer.php'; ?>

        </div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight sidebar menu item
            const sidebarItem = document.getElementById('menu-profil');
            if (sidebarItem) {
                sidebarItem.classList.add('active');
            }
        });
    </script>
</body>
</html>