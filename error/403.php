<?php
// filepath: /C:/xampp/htdocs/bank_sampah/error/403.php
http_response_code(403);

$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appBase = rtrim(dirname($scriptDir), '/');
$home = $appBase . '/';
$errorId = date('YmdHis') . '-' . substr(md5($_SERVER['REQUEST_TIME'] ?? time()), 0, 6);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>403 - Akses Ditolak</title>
<meta name="robots" content="noindex, nofollow">
<style>
    :root{
        --bg1:#0d6efd; --bg2:#20c997; --card:#ffffff; --text:#1f2937; --muted:#6b7280;
        --accent:#8b5cf6; --danger:#ef4444; --ok:#22c55e; --shadow: 0 20px 50px rgba(0,0,0,.15);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
        margin:0; font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif; color:var(--text);
        background: radial-gradient(1000px 800px at 10% 10%, rgba(255,255,255,.06), transparent),
                    radial-gradient(900px 700px at 90% 20%, rgba(255,255,255,.08), transparent),
                    linear-gradient(135deg, var(--bg1), var(--bg2));
        display:flex; align-items:center; justify-content:center; padding:24px;
    }
    .wrap{max-width:880px; width:100%}
    .card{
        position:relative; background:rgba(255,255,255,.9); backdrop-filter: blur(14px);
        border-radius:22px; box-shadow:var(--shadow); overflow:hidden; padding:28px;
        border:1px solid rgba(255,255,255,.4);
    }
    .ribbon{
        position:absolute; inset:0 0 auto 0; height:6px;
        background:linear-gradient(90deg,#8b5cf6,#06b6d4,#10b981,#f59e0b);
        background-size:200% 100%; animation:move 6s linear infinite;
    }
    @keyframes move{0%{background-position:0 0}100%{background-position:200% 0}}
    .row{display:flex; gap:24px; align-items:center; flex-wrap:wrap}
    .icon{
        width:110px; height:110px; border-radius:22px;
        display:grid; place-items:center; background:linear-gradient(135deg,#f43f5e,#f59e0b);
        color:#fff; box-shadow:0 12px 30px rgba(244,63,94,.35);
    }
    .icon svg{width:54px; height:54px}
    h1{margin:0 0 6px; font-size:32px; letter-spacing:.2px}
    .code{font-weight:800; font-size:64px; line-height:1; background:linear-gradient(135deg,#8b5cf6,#06b6d4);
        -webkit-background-clip:text; background-clip:text; color:transparent;}
    .muted{color:var(--muted)}
    .kpi{display:flex; gap:14px; flex-wrap:wrap; margin-top:12px}
    .badge{
        display:inline-flex; align-items:center; gap:8px;
        padding:8px 12px; border-radius:999px; background:rgba(139,92,246,.1); color:white; font-weight:600; font-size:13px;
        border:1px solid rgba(139,92,246,.25);
    }
    .actions{display:flex; gap:12px; flex-wrap:wrap; margin-top:18px}
    .btn{
        appearance:none; border:none; border-radius:12px; padding:12px 16px; font-weight:700; cursor:pointer;
        display:inline-flex; align-items:center; gap:8px; transition:.2s box-shadow,.2s transform,.2s background;
    }
    .btn:hover{transform:translateY(-1px)}
    .primary{background:linear-gradient(135deg,#8b5cf6,#06b6d4); color:#fff; box-shadow:0 8px 22px rgba(16,185,129,.35)}
    .light{background:#f8fafc; color:#0f172a; border:1px solid #e5e7eb}
    .danger{background:#fee2e2; color:#991b1b; border:1px solid #fecaca}
    .footer{margin-top:18px; font-size:13px; color:var(--muted); display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap}
    @media (prefers-color-scheme: dark){
        body{color:#e5e7eb}
        .card{background:rgba(17,24,39,.65); border-color:rgba(255,255,255,.08)}
        .muted{color:#9ca3af}
        .light{background:#0b1220; color:#e5e7eb; border-color:#111827}
        .danger{background:#220e0e; color:#fecaca; border-color:#7f1d1d}
    }
    @media (max-width:600px){
        .icon{width:84px;height:84px;border-radius:16px}
        .icon svg{width:42px;height:42px}
        .code{font-size:52px}
        h1{font-size:26px}
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="ribbon"></div>
        <div class="row">
            <div class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 9v4m0 4h.01M2.25 12a9.75 9.75 0 1 0 19.5 0 9.75 9.75 0 0 0-19.5 0Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </div>
            <div style="flex:1 1 420px">
                <div class="code">403</div>
                <h1>Akses Ditolak</h1>
                <div class="muted">Anda tidak memiliki izin untuk mengakses halaman ini.</div>
                <div class="kpi">
                    <span class="badge">Error ID: <strong><?= htmlspecialchars($errorId) ?></strong></span>
                    <span class="badge">Path: <strong><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?></strong></span>
                </div>
                <div class="actions">
                    <button class="btn light" onclick="history.back()">&larr; Kembali</button>
                    <a class="btn primary" href="<?= htmlspecialchars($home) ?>"><span>Beranda</span></a>
                    <a class="btn danger" href="mailto:xnuversh1kar4@gmail.com?subject=Error%20403%20<?= urlencode($errorId) ?>"><span>Bantuan</span></a>
                </div>
            </div>
        </div>
        <div class="footer">
            <span>&copy; <?= date('Y') ?> Bank Sampah Bahrul Ulum</span>
            <span>Jika Anda yakin ini kesalahan, hubungi admin dengan menyertakan Error ID.</span>
        </div>
    </div>
</div>
</body>
</html>