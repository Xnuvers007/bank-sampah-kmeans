<?php
// /admin/laporan.php
require '../config/db.php';
require '../config/functions.php';

// Wajibkan login sebagai admin
require_login('admin');

// Tentukan rentang tanggal default (bulan ini)
$tanggal_mulai = $_GET['start'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['end'] ?? date('Y-m-t');

// --- 1. AMBIL DATA RINGKASAN ---
$query_ringkasan = "
    SELECT 
        (SELECT COALESCE(SUM(total_harga), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) BETWEEN :start1 AND :end1) AS total_setoran,
        (SELECT COALESCE(SUM(jumlah_tarik), 0) FROM transaksi_tarik WHERE DATE(tanggal_tarik) BETWEEN :start2 AND :end2) AS total_tarikan,
        (SELECT COALESCE(SUM(berat), 0) FROM transaksi_setor WHERE DATE(tanggal_setor) BETWEEN :start3 AND :end3) AS total_berat,
        (SELECT COALESCE(SUM(total_pendapatan), 0) FROM transaksi_jual WHERE DATE(tanggal_jual) BETWEEN :start4 AND :end4) AS total_penjualan
";
$stmt_ringkasan = $pdo->prepare($query_ringkasan);
$stmt_ringkasan->execute([
    'start1' => $tanggal_mulai, 'end1' => $tanggal_akhir,
    'start2' => $tanggal_mulai, 'end2' => $tanggal_akhir,
    'start3' => $tanggal_mulai, 'end3' => $tanggal_akhir,
    'start4' => $tanggal_mulai, 'end4' => $tanggal_akhir
]);
$ringkasan = $stmt_ringkasan->fetch();

// Hitung laba/rugi
$total_pembelian = (int)($ringkasan['total_setoran'] ?? 0);
$total_penjualan = (int)($ringkasan['total_penjualan'] ?? 0);
$laba_kotor = $total_penjualan - $total_pembelian;
$margin_profit = $total_pembelian > 0 ? ($laba_kotor / $total_pembelian) * 100 : 0;

// --- 2. AMBIL DATA DETAIL SETORAN ---
$stmt_setoran = $pdo->prepare("
    SELECT ts.*, n.nama_lengkap, n.kelas, js.nama_sampah, u.username AS admin_pencatat
    FROM transaksi_setor ts
    JOIN nasabah n ON ts.id_nasabah = n.id_nasabah
    JOIN jenis_sampah js ON ts.id_sampah = js.id_sampah
    JOIN users u ON ts.dicatat_oleh = u.id_user
    WHERE DATE(ts.tanggal_setor) BETWEEN :start AND :end
    ORDER BY ts.tanggal_setor DESC
");
$stmt_setoran->execute(['start' => $tanggal_mulai, 'end' => $tanggal_akhir]);
$laporan_setoran = $stmt_setoran->fetchAll();

// --- 3. AMBIL DATA DETAIL TARIKAN ---
$stmt_tarikan = $pdo->prepare("
    SELECT tt.*, n.nama_lengkap, n.kelas, u.username AS admin_pencatat
    FROM transaksi_tarik tt
    JOIN nasabah n ON tt.id_nasabah = n.id_nasabah
    JOIN users u ON tt.dicatat_oleh = u.id_user
    WHERE DATE(tt.tanggal_tarik) BETWEEN :start AND :end
    ORDER BY tt.tanggal_tarik DESC
");
$stmt_tarikan->execute(['start' => $tanggal_mulai, 'end' => $tanggal_akhir]);
$laporan_tarikan = $stmt_tarikan->fetchAll();

// --- 4. AMBIL DATA PENJUALAN ---
$stmt_penjualan = $pdo->prepare("
    SELECT tj.*, js.nama_sampah, p.nama_pengepul, u.username AS admin_pencatat
    FROM transaksi_jual tj
    LEFT JOIN jenis_sampah js ON tj.id_sampah = js.id_sampah
    LEFT JOIN pengepul p ON tj.id_pengepul = p.id_pengepul
    JOIN users u ON tj.dicatat_oleh = u.id_user
    WHERE DATE(tj.tanggal_jual) BETWEEN :start AND :end
    ORDER BY tj.tanggal_jual DESC
");
$stmt_penjualan->execute(['start' => $tanggal_mulai, 'end' => $tanggal_akhir]);
$laporan_penjualan = $stmt_penjualan->fetchAll();

// --- 5. AMBIL DATA KEUANGAN PER JENIS SAMPAH ---
$stmt_profitabilitas = $pdo->prepare("
    SELECT 
        js.id_sampah,
        js.nama_sampah,
        COALESCE(SUM(ts.berat), 0) AS total_berat_beli,
        COALESCE(SUM(ts.total_harga), 0) AS total_biaya,
        (
            SELECT COALESCE(SUM(tj.berat), 0)
            FROM transaksi_jual tj
            WHERE tj.id_sampah = js.id_sampah
            AND DATE(tj.tanggal_jual) BETWEEN :start1 AND :end1
        ) AS total_berat_jual,
        (
            SELECT COALESCE(SUM(tj.total_pendapatan), 0)
            FROM transaksi_jual tj
            WHERE tj.id_sampah = js.id_sampah
            AND DATE(tj.tanggal_jual) BETWEEN :start2 AND :end2
        ) AS total_pendapatan
    FROM jenis_sampah js
    LEFT JOIN transaksi_setor ts ON js.id_sampah = ts.id_sampah
        AND DATE(ts.tanggal_setor) BETWEEN :start3 AND :end3
    GROUP BY js.id_sampah, js.nama_sampah
    ORDER BY (
        (SELECT COALESCE(SUM(tj.total_pendapatan), 0)
        FROM transaksi_jual tj
        WHERE tj.id_sampah = js.id_sampah
        AND DATE(tj.tanggal_jual) BETWEEN :start4 AND :end4) - COALESCE(SUM(ts.total_harga), 0)
    ) DESC
");
$stmt_profitabilitas->execute([
    'start1' => $tanggal_mulai, 'end1' => $tanggal_akhir,
    'start2' => $tanggal_mulai, 'end2' => $tanggal_akhir,
    'start3' => $tanggal_mulai, 'end3' => $tanggal_akhir,
    'start4' => $tanggal_mulai, 'end4' => $tanggal_akhir
]);
$profitabilitas_sampah = $stmt_profitabilitas->fetchAll();

// Tampilkan hanya yang punya aktivitas
$profitabilitas_sampah = array_filter($profitabilitas_sampah, function ($item) {
    return ($item['total_berat_beli'] ?? 0) > 0 || ($item['total_berat_jual'] ?? 0) > 0;
});

// --- 6. TREN PENDAPATAN & PENGELUARAN PER HARI ---
$stmt_tren = $pdo->prepare("
    SELECT 
        tanggal,
        SUM(pendapatan) AS pendapatan,
        SUM(pengeluaran) AS pengeluaran
    FROM (
        SELECT DATE(tanggal_jual) AS tanggal, SUM(total_pendapatan) AS pendapatan, 0 AS pengeluaran
        FROM transaksi_jual
        WHERE DATE(tanggal_jual) BETWEEN :start1 AND :end1
        GROUP BY DATE(tanggal_jual)
        UNION ALL
        SELECT DATE(tanggal_setor) AS tanggal, 0 AS pendapatan, SUM(total_harga) AS pengeluaran
        FROM transaksi_setor
        WHERE DATE(tanggal_setor) BETWEEN :start2 AND :end2
        GROUP BY DATE(tanggal_setor)
    ) AS combined
    GROUP BY tanggal
    ORDER BY tanggal
");
$stmt_tren->execute([
    'start1' => $tanggal_mulai, 'end1' => $tanggal_akhir,
    'start2' => $tanggal_mulai, 'end2' => $tanggal_akhir
]);
$tren_keuangan = $stmt_tren->fetchAll();

// Data Chart.js
$labels_tren = [];
$data_pendapatan = [];
$data_pengeluaran = [];
$data_profit = [];
foreach ($tren_keuangan as $tren) {
    $labels_tren[] = date('d/m', strtotime($tren['tanggal']));
    $data_pendapatan[] = (int)$tren['pendapatan'];
    $data_pengeluaran[] = (int)$tren['pengeluaran'];
    $data_profit[] = (int)$tren['pendapatan'] - (int)$tren['pengeluaran'];
}
$labels_tren_json = json_encode($labels_tren);
$data_pendapatan_json = json_encode($data_pendapatan);
$data_pengeluaran_json = json_encode($data_pengeluaran);
$data_profit_json = json_encode($data_profit);

// Helper format rupiah
if (!function_exists('format_rupiah')) {
    function format_rupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - Bank Sampah BU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/laporan.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Inline style khusus halaman ini (tidak mengubah style.css/laporan.css) -->
    <style>
        .page-hero {
            background: linear-gradient(135deg, #0d6efd 0%, #20c997 100%);
            border-radius: 18px;
            padding: 24px 24px;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .page-hero::after {
            content: '';
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(rgba(255,255,255,.25), rgba(255,255,255,0));
            border-radius: 50%;
            filter: blur(2px);
            pointer-events: none;
            z-index: 0;
        }
        .page-hero .toolbar {
            position: relative;
            z-index: 1;
        }
        .page-hero .title {
            font-weight: 700;
            letter-spacing: .3px;
            margin: 0;
        }
        .page-hero .sub {
            opacity: .9;
        }
        .kpi-badge {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            padding: .4rem .65rem;
            border-radius: 8px;
            color: #fff;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .toolbar .btn {
            border-radius: 10px;
        }
        .filter-card .form-label { font-weight: 600; }
        .card .card-header i { margin-right: .5rem; }
        .table thead th i { opacity: .6; margin-right: .35rem; }
        .table tfoot { font-weight: 700; }
        .brand-mini {
            display: inline-flex; align-items: center; gap:.5rem;
            font-weight: 600; letter-spacing: .2px;
        }
        .brand-mini i { opacity: .9; }

        @media print {
            body { background: #fff !important; }
            .page-hero, .toolbar, .brand-mini { display: none !important; }
            .no-print { display: none !important; }
            .card { page-break-inside: avoid; break-inside: avoid; }
            .table { font-size: 12px; }

            .chart-container { height: auto !important; overflow: visible !important; }
            img.print-chart-image { display: block !important; max-width: 100% !important; height: auto !important; }
            canvas.print-hidden-canvas { display: none !important; }
        }
    </style>
</head>
<body>
<?php include 'sidebar/sidebar.php'; ?>
<div class="content">
    <div class="container-fluid">

        <!-- Hero Header -->
        <div class="page-hero mt-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="mb-3 mb-md-0">
                    <div class="brand-mini mb-1">
                        <i class="fas fa-recycle"></i> Bank Sampah Bahrul Ulum
                    </div>
                    <h1 class="title">Laporan Bank Sampah</h1>
                    <div class="sub">Ringkasan transaksi, profitabilitas, dan tren periode berjalan</div>
                </div>
                <div class="d-flex flex-column align-items-md-end">
                    <div class="kpi-badge mb-2">
                        <i class="far fa-calendar"></i>
                        Periode: <?= htmlspecialchars(date('d F Y', strtotime($tanggal_mulai))) ?> &mdash; <?= htmlspecialchars(date('d F Y', strtotime($tanggal_akhir))) ?>
                    </div>
                    <div class="toolbar">
                        <button class="btn btn-light text-success me-2 no-print" onclick="exportToExcel()" title="Export ke Excel">
                            <i class="fas fa-file-excel me-2"></i>Export Excel
                        </button>
                        <button class="btn btn-outline-light btn-print no-print" onclick="printLaporan()" title="Cetak laporan">
                            <i class="fas fa-print me-2"></i>Cetak
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card mb-4 filter-card">
            <div class="card-body">
                <form method="GET" action="laporan.php" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="start" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" id="start" name="start" value="<?= htmlspecialchars($tanggal_mulai) ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="end" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" id="end" name="end" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4" id="laporanTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="ringkasan-tab" data-bs-toggle="tab" data-bs-target="#ringkasan" type="button" role="tab">
                    <i class="fas fa-chart-pie"></i> Ringkasan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="keuangan-tab" data-bs-toggle="tab" data-bs-target="#keuangan" type="button" role="tab">
                    <i class="fas fa-file-invoice-dollar"></i> Laporan Keuangan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="setoran-tab" data-bs-toggle="tab" data-bs-target="#setoran" type="button" role="tab">
                    <i class="fas fa-arrow-down text-success"></i> Detail Setoran
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tarikan-tab" data-bs-toggle="tab" data-bs-target="#tarikan" type="button" role="tab">
                    <i class="fas fa-arrow-up text-danger"></i> Detail Penarikan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="penjualan-tab" data-bs-toggle="tab" data-bs-target="#penjualan" type="button" role="tab">
                    <i class="fas fa-truck text-primary"></i> Detail Penjualan
                </button>
            </li>
        </ul>

        <!-- Tab Contents -->
        <div class="tab-content" id="laporanTabContent">

            <!-- Tab 1: Ringkasan -->
            <div class="tab-pane fade show active" id="ringkasan" role="tabpanel" aria-labelledby="ringkasan-tab">
                <!-- Stat Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card card-green">
                            <div class="icon"><i class="fas fa-coins"></i></div>
                            <div class="title">Total Pembelian Sampah</div>
                            <div class="value"><?= format_rupiah($total_pembelian) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card card-blue">
                            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                            <div class="title">Total Penjualan Sampah</div>
                            <div class="value"><?= format_rupiah($total_penjualan) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card <?= $laba_kotor >= 0 ? 'card-green' : 'card-red' ?>">
                            <div class="icon"><i class="fas fa-chart-line"></i></div>
                            <div class="title">Laba/Rugi Kotor</div>
                            <div class="value"><?= format_rupiah($laba_kotor) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card card-purple">
                            <div class="icon"><i class="fas fa-balance-scale"></i></div>
                            <div class="title">Total Berat Sampah</div>
                            <div class="value"><?= number_format($ringkasan['total_berat'] ?? 0, 1) ?> kg</div>
                        </div>
                    </div>
                </div>

                <!-- Chart Tren Keuangan -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Tren Keuangan
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trenKeuanganChart" style="height: 300px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan per Jenis Sampah -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-recycle"></i> Profitabilitas per Jenis Sampah
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-leaf"></i> Jenis Sampah</th>
                                        <th class="text-end">Total Beli (kg)</th>
                                        <th class="text-end">Total Jual (kg)</th>
                                        <th class="text-end">Biaya Beli (Rp)</th>
                                        <th class="text-end">Pendapatan Jual (Rp)</th>
                                        <th class="text-end">Laba/Rugi (Rp)</th>
                                        <th class="text-end">Margin (%)</th>
                                        <th>Profitabilitas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($profitabilitas_sampah as $item):
                                        $total_biaya = (int)($item['total_biaya'] ?? 0);
                                        $total_pendapatan = (int)($item['total_pendapatan'] ?? 0);
                                        $laba_item = $total_pendapatan - $total_biaya;
                                        $margin_item = $total_biaya > 0 ? ($laba_item / $total_biaya) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nama_sampah']) ?></td>
                                        <td class="text-end"><?= number_format((float)$item['total_berat_beli'], 1) ?></td>
                                        <td class="text-end"><?= number_format((float)$item['total_berat_jual'], 1) ?></td>
                                        <td class="text-end"><?= format_rupiah($total_biaya) ?></td>
                                        <td class="text-end"><?= format_rupiah($total_pendapatan) ?></td>
                                        <td class="text-end <?= $laba_item >= 0 ? 'text-profit' : 'text-loss' ?>">
                                            <?= format_rupiah($laba_item) ?>
                                        </td>
                                        <td class="text-end <?= $margin_item >= 0 ? 'text-profit' : 'text-loss' ?>">
                                            <?= number_format($margin_item, 1) ?>%
                                        </td>
                                        <td>
                                            <div class="progress" title="Margin: <?= number_format($margin_item,1) ?>%">
                                                <?php if ($margin_item >= 0): ?>
                                                    <div class="progress-bar bg-success" style="width: <?= min($margin_item, 100) ?>%"></div>
                                                <?php else: ?>
                                                    <div class="progress-bar bg-danger" style="width: <?= min(abs($margin_item), 100) ?>%"></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($profitabilitas_sampah)): ?>
                                        <tr><td colspan="8" class="text-center">Tidak ada data profitabilitas pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Laporan Keuangan -->
            <div class="tab-pane fade" id="keuangan" role="tabpanel" aria-labelledby="keuangan-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-file-invoice-dollar"></i> Laporan Laba/Rugi
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="text-muted">Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?></h5>
                        </div>

                        <div class="row mb-4">
                            <div class="col-lg-6">
                                <!-- Income Statement -->
                                <h5 class="border-bottom pb-2 mb-3">Laporan Laba/Rugi</h5>

                                <div class="financial-highlight bg-light">
                                    <strong>A. Pendapatan</strong>
                                </div>
                                <div class="row px-3 py-2">
                                    <div class="col-8">Penjualan sampah ke pengepul</div>
                                    <div class="col-4 text-end"><?= format_rupiah($total_penjualan) ?></div>
                                </div>
                                <div class="row px-3 py-2 fw-bold">
                                    <div class="col-8">Total Pendapatan</div>
                                    <div class="col-4 text-end"><?= format_rupiah($total_penjualan) ?></div>
                                </div>

                                <div class="financial-highlight bg-light mt-3">
                                    <strong>B. Biaya Operasional</strong>
                                </div>
                                <div class="row px-3 py-2">
                                    <div class="col-8">Pembelian sampah dari nasabah</div>
                                    <div class="col-4 text-end"><?= format_rupiah($total_pembelian) ?></div>
                                </div>
                                <div class="row px-3 py-2 fw-bold">
                                    <div class="col-8">Total Biaya</div>
                                    <div class="col-4 text-end"><?= format_rupiah($total_pembelian) ?></div>
                                </div>

                                <div class="financial-highlight <?= $laba_kotor >= 0 ? 'bg-success text-white' : 'bg-danger text-white' ?> mt-3">
                                    <div class="row">
                                        <div class="col-8"><strong>Laba/Rugi Operasional</strong></div>
                                        <div class="col-4 text-end"><strong><?= format_rupiah($laba_kotor) ?></strong></div>
                                    </div>
                                </div>

                                <div class="row px-3 py-2 mt-3">
                                    <div class="col-8">Margin Profit</div>
                                    <div class="col-4 text-end"><?= number_format($margin_profit, 2) ?>%</div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="chart-container" style="position: relative; height: 350px; overflow-y: auto;">
                                    <h5 class="border-bottom pb-2 mb-3">Visualisasi Laba/Rugi</h5>
                                    <canvas id="profitChart" style="height: 350px;"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Rekomendasi -->
                        <div class="card mt-4 <?= $laba_kotor >= 0 ? 'border-success' : 'border-danger' ?>">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-lightbulb text-warning me-2"></i>
                                    Analisis Keuangan
                                </h5>
                                <div class="card-text">
                                    <?php if ($laba_kotor > 0): ?>
                                        <p>Pada periode <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>, Bank Sampah Bahrul Ulum mencatat laba operasional sebesar <?= format_rupiah($laba_kotor) ?> dengan margin profit <?= number_format($margin_profit, 2) ?>%.</p>
                                        <p><strong>Rekomendasi:</strong></p>
                                        <ul>
                                            <li>Prioritaskan jenis sampah dengan profitabilitas tertinggi.</li>
                                            <li>Evaluasi peluang peningkatan harga jual ke pengepul.</li>
                                            <li>Alokasikan laba untuk pengembangan program.</li>
                                        </ul>
                                    <?php else: ?>
                                        <p>Pada periode <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>, Bank Sampah Bahrul Ulum mencatat kerugian operasional sebesar <?= format_rupiah(abs($laba_kotor)) ?>.</p>
                                        <p><strong>Rekomendasi:</strong></p>
                                        <ul>
                                            <li>Tinjau harga beli dari nasabah dan harga jual ke pengepul.</li>
                                            <li>Fokus pada jenis sampah dengan margin lebih tinggi.</li>
                                            <li>Periksa stok belum terjual pada periode ini.</li>
                                            <li>Cari pengepul dengan harga lebih baik.</li>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Detail Setoran -->
            <div class="tab-pane fade" id="setoran" role="tabpanel" aria-labelledby="setoran-tab">
                <div class="card">
                    <div class="card-header"><i class="fas fa-table"></i> Laporan Detail Setoran Sampah</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="far fa-calendar"></i> Tanggal</th>
                                        <th><i class="far fa-user"></i> Nasabah</th>
                                        <th>Kelas</th>
                                        <th>Jenis Sampah</th>
                                        <th class="text-end">Berat (kg)</th>
                                        <th class="text-end">Total Harga</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_setoran as $data): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($data['tanggal_setor'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($data['kelas']) ?></td>
                                        <td><?= htmlspecialchars($data['nama_sampah']) ?></td>
                                        <td class="text-end"><?= number_format((float)$data['berat'], 2) ?></td>
                                        <td class="text-end"><?= format_rupiah($data['total_harga']) ?></td>
                                        <td><?= htmlspecialchars($data['admin_pencatat']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($laporan_setoran)): ?>
                                        <tr><td colspan="7" class="text-center">Tidak ada data setoran pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="4" class="text-end">Total:</td>
                                        <td class="text-end"><?= number_format(array_sum(array_map('floatval', array_column($laporan_setoran, 'berat'))), 1) ?> kg</td>
                                        <td class="text-end"><?= format_rupiah(array_sum(array_map('floatval', array_column($laporan_setoran, 'total_harga')))) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Detail Penarikan (tambah kolom Catatan/Alasan) -->
            <div class="tab-pane fade" id="tarikan" role="tabpanel" aria-labelledby="tarikan-tab">
                <div class="card">
                    <div class="card-header"><i class="fas fa-table"></i> Laporan Detail Penarikan Saldo</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="far fa-calendar"></i> Tanggal</th>
                                        <th><i class="far fa-user"></i> Nasabah</th>
                                        <th>Kelas</th>
                                        <th class="text-end">Jumlah Penarikan</th>
                                        <th>Catatan</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_tarikan as $data): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($data['tanggal_tarik'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($data['kelas']) ?></td>
                                        <td class="text-end"><?= format_rupiah($data['jumlah_tarik']) ?></td>
                                        <td><?= htmlspecialchars($data['catatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($data['admin_pencatat']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($laporan_tarikan)): ?>
                                        <tr><td colspan="6" class="text-center">Tidak ada data penarikan pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end">Total:</td>
                                        <td class="text-end"><?= format_rupiah(array_sum(array_map('floatval', array_column($laporan_tarikan, 'jumlah_tarik')))) ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 5: Detail Penjualan -->
            <div class="tab-pane fade" id="penjualan" role="tabpanel" aria-labelledby="penjualan-tab">
                <div class="card">
                    <div class="card-header"><i class="fas fa-table"></i> Laporan Detail Penjualan ke Pengepul</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th><i class="far fa-calendar"></i> Tanggal</th>
                                        <th>Jenis Sampah</th>
                                        <th>Pengepul</th>
                                        <th class="text-end">Berat (kg)</th>
                                        <th class="text-end">Harga Jual/kg</th>
                                        <th class="text-end">Total Pendapatan</th>
                                        <th>Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($laporan_penjualan as $data): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($data['tanggal_jual'])) ?></td>
                                        <td><?= htmlspecialchars($data['nama_sampah']) ?></td>
                                        <td><?= htmlspecialchars($data['nama_pengepul'] ?? 'Tidak tercatat') ?></td>
                                        <td class="text-end"><?= number_format((float)$data['berat'], 2) ?></td>
                                        <td class="text-end"><?= format_rupiah($data['harga_jual_per_kg']) ?></td>
                                        <td class="text-end"><?= format_rupiah($data['total_pendapatan']) ?></td>
                                        <td><?= htmlspecialchars($data['admin_pencatat']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($laporan_penjualan)): ?>
                                        <tr><td colspan="7" class="text-center">Tidak ada data penjualan pada periode ini.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary fw-bold">
                                        <td colspan="3" class="text-end">Total:</td>
                                        <td class="text-end"><?= number_format(array_sum(array_map('floatval', array_column($laporan_penjualan, 'berat'))), 1) ?> kg</td>
                                        <td></td>
                                        <td class="text-end"><?= format_rupiah(array_sum(array_map('floatval', array_column($laporan_penjualan, 'total_pendapatan')))) ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Print header only -->
        <div class="print-header">
            <h2 class="text-center">Laporan Bank Sampah Bahrul Ulum</h2>
            <p class="text-center">Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?></p>
            <hr>
        </div>

        <footer class="mt-5 mb-3 text-center text-muted">
            <p>&copy; <?= date('Y') ?> Bank Sampah Bahrul Ulum</p>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Chart 1: Tren Keuangan (dengan gradient)
    const trenCtx = document.getElementById('trenKeuanganChart').getContext('2d');

    const gradGreen = trenCtx.createLinearGradient(0, 0, 0, 300);
    gradGreen.addColorStop(0, 'rgba(40,167,69,0.35)');
    gradGreen.addColorStop(1, 'rgba(40,167,69,0.05)');

    const gradRed = trenCtx.createLinearGradient(0, 0, 0, 300);
    gradRed.addColorStop(0, 'rgba(220,53,69,0.35)');
    gradRed.addColorStop(1, 'rgba(220,53,69,0.05)');

    const gradBlue = trenCtx.createLinearGradient(0, 0, 0, 300);
    gradBlue.addColorStop(0, 'rgba(0,123,255,0.35)');
    gradBlue.addColorStop(1, 'rgba(0,123,255,0.05)');

    const trenChart = new Chart(trenCtx, {
        type: 'line',
        data: {
            labels: <?= $labels_tren_json ?>,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: <?= $data_pendapatan_json ?>,
                    borderColor: '#28a745',
                    backgroundColor: gradGreen,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0
                },
                {
                    label: 'Pengeluaran',
                    data: <?= $data_pengeluaran_json ?>,
                    borderColor: '#dc3545',
                    backgroundColor: gradRed,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0
                },
                {
                    label: 'Laba/Rugi',
                    data: <?= $data_profit_json ?>,
                    borderColor: '#007bff',
                    backgroundColor: gradBlue,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                title: { display: true, text: 'Tren Pendapatan, Pengeluaran, dan Laba/Rugi' },
                tooltip: {
                    mode: 'index',
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label ? context.dataset.label + ': ' : '';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                },
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Chart 2: Profit Breakdown
    const profitCtx = document.getElementById('profitChart').getContext('2d');
    const profitChart = new Chart(profitCtx, {
        type: 'bar',
        data: {
            labels: ['Penjualan', 'Pembelian', 'Laba/Rugi'],
            datasets: [{
                label: 'Jumlah (Rp)',
                data: [<?= $total_penjualan ?>, <?= $total_pembelian ?>, <?= $laba_kotor ?>],
                backgroundColor: [
                    'rgba(0, 123, 255, 0.7)',
                    'rgba(220, 53, 69, 0.7)',
                    <?= $laba_kotor >= 0 ? "'rgba(40, 167, 69, 0.7)'" : "'rgba(220, 53, 69, 0.7)'" ?>
                ],
                borderColor: [
                    'rgb(0, 123, 255)',
                    'rgb(220, 53, 69)',
                    <?= $laba_kotor >= 0 ? "'rgb(40, 167, 69)'" : "'rgb(220, 53, 69)'" ?>
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 900, easing: 'easeOutQuart' },
            plugins: {
                title: { display: true, text: 'Perbandingan Penjualan, Pembelian, dan Laba/Rugi' },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label ? context.dataset.label + ': ' : '';
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                },
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
                        }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
    // Simpan kedua chart ke global scope
    window.__charts = [trenChart, profitChart];
});

// Export Excel (Tidak berubah)
function exportToExcel() {
    const workSheets = {
        'Ringkasan': [
            ['LAPORAN BANK SAMPAH BAHRUL ULUM'],
            ['Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>'],
            [''],
            ['Ringkasan Keuangan'],
            ['Total Pembelian Sampah', '<?= $total_pembelian ?>'],
            ['Total Penjualan Sampah', '<?= $total_penjualan ?>'],
            ['Laba/Rugi Kotor', '<?= $laba_kotor ?>'],
            ['Margin Profit', '<?= number_format($margin_profit, 2) ?>%'],
            ['Total Berat Sampah', '<?= number_format($ringkasan['total_berat'] ?? 0, 1) ?> kg'],
        ],
        'Profitabilitas': [
            ['PROFITABILITAS PER JENIS SAMPAH'],
            ['Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>'],
            [''],
            ['Jenis Sampah', 'Total Beli (kg)', 'Total Jual (kg)', 'Biaya Beli (Rp)', 'Pendapatan Jual (Rp)', 'Laba/Rugi (Rp)', 'Margin (%)'],
            <?php foreach ($profitabilitas_sampah as $item):
                $total_biaya = (int)($item['total_biaya'] ?? 0);
                $total_pendapatan = (int)($item['total_pendapatan'] ?? 0);
                $laba_item = $total_pendapatan - $total_biaya;
                $margin_item = $total_biaya > 0 ? ($laba_item / $total_biaya) * 100 : 0;
            ?>
            ['<?= $item['nama_sampah'] ?>', <?= (float)$item['total_berat_beli'] ?>, <?= (float)$item['total_berat_jual'] ?>, <?= $total_biaya ?>, <?= $total_pendapatan ?>, <?= $laba_item ?>, <?= number_format($margin_item, 2) ?>],
            <?php endforeach; ?>
        ],
        'Detail Setoran': [
            ['LAPORAN DETAIL SETORAN'],
            ['Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>'],
            [''],
            ['Tanggal', 'Nasabah', 'Kelas', 'Jenis Sampah', 'Berat (kg)', 'Total Harga', 'Admin'],
            <?php foreach ($laporan_setoran as $data): ?>
            ['<?= date('d/m/Y H:i', strtotime($data['tanggal_setor'])) ?>', '<?= $data['nama_lengkap'] ?>', '<?= $data['kelas'] ?>', '<?= $data['nama_sampah'] ?>', <?= (float)$data['berat'] ?>, <?= (int)$data['total_harga'] ?>, '<?= $data['admin_pencatat'] ?>'],
            <?php endforeach; ?>
        ],
        'Detail Penjualan': [
            ['LAPORAN DETAIL PENJUALAN'],
            ['Periode: <?= date('d F Y', strtotime($tanggal_mulai)) ?> s/d <?= date('d F Y', strtotime($tanggal_akhir)) ?>'],
            [''],
            ['Tanggal', 'Jenis Sampah', 'Pengepul', 'Berat (kg)', 'Harga Jual/kg', 'Total Pendapatan', 'Admin'],
            <?php foreach ($laporan_penjualan as $data): ?>
            ['<?= date('d/m/Y H:i', strtotime($data['tanggal_jual'])) ?>', '<?= $data['nama_sampah'] ?>', '<?= $data['nama_pengepul'] ?? "Tidak tercatat" ?>', <?= (float)$data['berat'] ?>, <?= (int)$data['harga_jual_per_kg'] ?>, <?= (int)$data['total_pendapatan'] ?>, '<?= $data['admin_pencatat'] ?>'],
            <?php endforeach; ?>
        ]
    };

    const workbook = XLSX.utils.book_new();
    for (const [sheetName, sheetData] of Object.entries(workSheets)) {
        const worksheet = XLSX.utils.aoa_to_sheet(sheetData);
        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName.substring(0,31));
    }
    const filename = 'Laporan_Bank_Sampah_' + new Date().toISOString().slice(0, 10) + '.xlsx';
    XLSX.writeFile(workbook, filename);
}

// Fungsi konversi canvas (Tidak berubah)
function __canvasesToImages() {
    const canvases = Array.from(document.querySelectorAll('canvas'));
    let pending = 0;

    return new Promise((resolve) => {
        if (canvases.length === 0) return resolve();

        canvases.forEach((cvs) => {
            try {
                const img = new Image();
                img.className = 'print-chart-image';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.src = cvs.toDataURL('image/png');

                pending++;
                img.onload = () => {
                    pending--;
                    if (pending === 0) resolve();
                };
                img.onerror = () => {
                    // Jika gagal, tetap lanjut
                    pending--;
                    if (pending === 0) resolve();
                };

                cvs.insertAdjacentElement('beforebegin', img);
                cvs.classList.add('print-hidden-canvas');
                cvs.style.display = 'none';
            } catch (e) {
                // Abaikan jika canvas belum siap
            }
        });

        // Jaga-jaga jika tidak ada yang pending
        if (pending === 0) resolve();
    });
}


// =======================================================
// MODIFIKASI DI BAWAH INI
// =======================================================

// Variabel global untuk menyimpan state tab yang tersembunyi
let hiddenPanes = [];

// Handler cetak (FUNGSI BARU)
async function printLaporan() {
    // 1. Tampilkan SEMUA tab-pane yang tersembunyi
    hiddenPanes = []; // Reset
    document.querySelectorAll('.tab-pane').forEach(pane => {
        // Cek apakah pane sedang tidak terlihat
        const isHidden = (pane.style.display === 'none') || (getComputedStyle(pane).display === 'none');
        
        if (isHidden) {
            // Simpan untuk dipulihkan
            hiddenPanes.push(pane); 
            
            // Paksa tampil agar chart bisa render
            pane.style.display = 'block'; 
            pane.style.visibility = 'visible';
            pane.style.opacity = '1';
        }
    });

    // 2. Beri waktu agar DOM update (penting!)
    // (100ms biasanya cukup)
    await new Promise(resolve => setTimeout(resolve, 100));

    // 3. Paksa update semua chart SEKARANG setelah terlihat
    if (Array.isArray(window.__charts)) {
        window.__charts.forEach(ch => { 
            try { 
                ch.resize(); // Wajib resize
                ch.update('none'); // Update tanpa animasi
            } catch(e){ 
                console.error('Chart update/resize failed', e); 
            } 
        });
    }

    // 4. Beri waktu lagi agar chart selesai RENDER di canvas
    // (Chart.js butuh waktu render)
    await new Promise(resolve => setTimeout(resolve, 300));

    // 5. Konversi canvas ke image (fungsi lama Anda)
    await __canvasesToImages();
    
    // 6. Panggil print
    window.print();

    // 7. Fungsi __restoreCanvases (yang sudah di-hook ke afterprint)
    //    sekarang akan juga memulihkan tab.
}

// FUNGSI BARU (Menggantikan yang lama)
function __restoreCanvases() {
    // Bagian 1: Kembalikan canvas (kode lama Anda)
    document.querySelectorAll('img.print-chart-image').forEach((img) => {
        const next = img.nextElementSibling;
        if (next && next.tagName === 'CANVAS' && next.classList.contains('print-hidden-canvas')) {
            next.style.display = '';
            next.classList.remove('print-hidden-canvas');
        }
        img.remove();
    });

    // Bagian 2: Kembalikan state tab (tambahan)
    if (hiddenPanes.length > 0) {
        hiddenPanes.forEach(pane => {
            // Hapus style inline agar kembali dikontrol Bootstrap
            pane.style.display = ''; 
            pane.style.visibility = '';
            pane.style.opacity = '';
        });
    }
    hiddenPanes = []; // Reset
}

// Kembalikan canvas setelah cetak (Kode lama Anda, biarkan)
if ('onafterprint' in window) {
    window.addEventListener('afterprint', __restoreCanvases);
} else {
    // Fallback
    const mql = window.matchMedia('print');
    if (mql && mql.addEventListener) {
        mql.addEventListener('change', (evt) => {
            if (!evt.matches) __restoreCanvases();
        });
    }
}
</script>
</body>
</html>