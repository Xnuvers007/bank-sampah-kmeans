<div class="sidebar">
    <a href="index.php" class="brand">
        <i class="fas fa-recycle"></i>
        <span class="brand-text">Bank Sampah</span>
    </a>
    
    <div class="nav-category">Dashboard</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-category">Transaksi</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'transaksi_setor.php' ? 'active' : '' ?>" href="transaksi_setor.php">
                <i class="fas fa-download"></i>
                <span class="nav-text">Setor Sampah</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'transaksi_tarik.php' ? 'active' : '' ?>" href="transaksi_tarik.php">
                <i class="fas fa-upload"></i>
                <span class="nav-text">Tarik Saldo</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'penjualan.php' ? 'active' : '' ?>" href="penjualan.php">
                <i class="fas fa-truck-loading"></i>
                <span class="nav-text">Penjualan</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-category">Data Master</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'nasabah.php' ? 'active' : '' ?>" href="nasabah.php">
                <i class="fas fa-users"></i>
                <span class="nav-text">Nasabah</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sampah.php' ? 'active' : '' ?>" href="sampah.php">
                <i class="fas fa-trash"></i>
                <span class="nav-text">Jenis Sampah</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'stok.php' ? 'active' : '' ?>" href="stok.php">
                <i class="fas fa-warehouse"></i>
                <span class="nav-text">Stok Gudang</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-category">Analisis & Laporan</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'laporan.php' ? 'active' : '' ?>" href="laporan.php">
                <i class="fas fa-file-alt"></i>
                <span class="nav-text">Laporan</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'clustering.php' ? 'active' : '' ?>" href="clustering.php">
                <i class="fas fa-project-diagram"></i>
                <span class="nav-text">Clustering</span>
            </a>
        </li>
    </ul>
    
    <div class="nav-category mt-4">Akun</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </li>
    </ul>
</div>