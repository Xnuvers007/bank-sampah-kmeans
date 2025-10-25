<?php
// filepath: /C:/xampp/htdocs/bank_sampah/error/404.php
http_response_code(404);

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
<title>404 - Halaman Tidak Ditemukan</title>
<meta name="robots" content="noindex, nofollow">
<style>
    :root{--bg1:#0d6efd; --bg2:#20c997; --text:#1f2937; --muted:#6b7280}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
        margin:0; font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif; color:var(--text);
        background: radial-gradient(900px 900px at -10% -10%, rgba(255,255,255,.06), transparent),
                    radial-gradient(900px 900px at 110% 10%, rgba(255,255,255,.08), transparent),
                    linear-gradient(135deg, var(--bg1), var(--bg2));
        display:flex; align-items:center; justify-content:center; padding:24px;
    }
    .shell{max-width:920px;width:100%}
    .card{position:relative;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-radius:22px;padding:28px;border:1px solid rgba(255,255,255,.4);box-shadow:0 20px 50px rgba(0,0,0,.15)}
    .ribbon{position:absolute;inset:0 0 auto 0;height:6px;background:linear-gradient(90deg,#06b6d4,#8b5cf6,#f59e0b);background-size:200% 100%;animation:move 6s linear infinite}
    @keyframes move{to{background-position:200% 0}}
    .grid{display:flex;gap:24px;align-items:center;flex-wrap:wrap}
    .art{width:120px;height:120px;border-radius:22px;display:grid;place-items:center;background:linear-gradient(135deg,#06b6d4,#8b5cf6);color:#fff;box-shadow:0 12px 30px rgba(6,182,212,.35)}
    .art svg{width:64px;height:64px}
    .code{font-size:64px;font-weight:800;background:linear-gradient(135deg,#06b6d4,#8b5cf6);-webkit-background-clip:text;background-clip:text;color:transparent;margin:0}
    h1{margin:4px 0 6px;font-size:30px}
    .muted{color:var(--muted)}
    .tips{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
    .tip{font-size:13px;padding:8px 12px;border-radius:999px;background:rgba(6,182,212,.08);border:1px solid rgba(6,182,212,.25)}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .btn{appearance:none;border:none;border-radius:12px;padding:12px 16px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.2s transform,.2s box-shadow}
    .btn:hover{transform:translateY(-1px)}
    .primary{background:linear-gradient(135deg,#06b6d4,#8b5cf6);color:#fff}
    .light{background:#f8fafc;color:#0f172a;border:1px solid #e5e7eb}
    @media (prefers-color-scheme: dark){
        body{color:#e5e7eb}
        .card{background:rgba(17,24,39,.65);border-color:rgba(255,255,255,.08)}
        .light{background:#0b1220;color:#e5e7eb;border-color:#111827}
        .muted{color:#9ca3af}
    }
    @media (max-width:600px){.art{width:92px;height:92px;border-radius:16px}.art svg{width:50px;height:50px}.code{font-size:52px}h1{font-size:24px}}
</style>
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="ribbon"></div>
        <div class="grid">
            <div class="art" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 12h4l3 8 4-16 3 8h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div style="flex:1 1 460px">
                <p class="code">404</p>
                <h1>Halaman Tidak Ditemukan</h1>
                <p class="muted">URL yang Anda akses mungkin sudah dipindah, diganti, atau tidak pernah ada.</p>
                <div class="tips">
                    <span class="tip">Periksa kembali alamat URL</span>
                    <span class="tip">Gunakan menu navigasi</span>
                    <span class="tip">Kembali ke beranda</span>
                </div>
                <div class="actions">
                    <button class="btn light" onclick="history.back()">&larr; Kembali</button>
                    <a class="btn primary" href="<?= htmlspecialchars($home) ?>">Ke Beranda</a>
                    <a class="btn light" href="mailto:xnuversh1kar4@gmail.com?subject=Error%20404%20<?= urlencode($errorId) ?>">Laporkan</a>
                </div>
                <p class="muted" style="margin-top:10px;font-size:13px">Error ID: <?= htmlspecialchars($errorId) ?></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>