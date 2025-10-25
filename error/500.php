<?php
// filepath: /C:/xampp/htdocs/bank_sampah/error/500.php
http_response_code(500);

$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appBase = rtrim(dirname($scriptDir), '/');
$home = $appBase . '/';
$errorId = date('YmdHis') . '-' . substr(md5($_SERVER['REQUEST_TIME'] ?? time()), 0, 6);
$redirectAfter = 10; // detik
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>500 - Kesalahan Server</title>
<meta name="robots" content="noindex, nofollow">
<style>
    :root{--bg1:#ef4444; --bg2:#f59e0b; --text:#1f2937; --muted:#6b7280}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
        margin:0; font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif; color:var(--text);
        background: radial-gradient(900px 900px at 0% 0%, rgba(255,255,255,.06), transparent),
                    radial-gradient(900px 900px at 100% 0%, rgba(255,255,255,.08), transparent),
                    linear-gradient(135deg, var(--bg1), var(--bg2));
        display:flex; align-items:center; justify-content:center; padding:24px;
    }
    .container{max-width:920px;width:100%}
    .card{position:relative;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-radius:22px;padding:28px;border:1px solid rgba(255,255,255,.4);box-shadow:0 20px 50px rgba(0,0,0,.15)}
    .ribbon{position:absolute;inset:0 0 auto 0;height:6px;background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e,#06b6d4);background-size:200% 100%;animation:move 6s linear infinite}
    @keyframes move{to{background-position:200% 0}}
    .flex{display:flex;gap:24px;align-items:center;flex-wrap:wrap}
    .glow{width:120px;height:120px;border-radius:22px;display:grid;place-items:center;background:linear-gradient(135deg,#ef4444,#f59e0b);color:#fff;box-shadow:0 12px 30px rgba(239,68,68,.35)}
    .glow svg{width:60px;height:60px}
    .code{font-size:64px;font-weight:800;background:linear-gradient(135deg,#ef4444,#f59e0b);-webkit-background-clip:text;background-clip:text;color:transparent;margin:0}
    h1{margin:4px 0 6px;font-size:30px}
    .muted{color:var(--muted)}
    .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .btn{appearance:none;border:none;border-radius:12px;padding:12px 16px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:.2s transform,.2s box-shadow}
    .btn:hover{transform:translateY(-1px)}
    .primary{background:linear-gradient(135deg,#ef4444,#f59e0b);color:#fff}
    .light{background:#f8fafc;color:#0f172a;border:1px solid #e5e7eb}
    .chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);font-size:13px;margin-top:10px}
    @media (prefers-color-scheme: dark){
        body{color:#e5e7eb}
        .card{background:rgba(17,24,39,.65);border-color:rgba(255,255,255,.08)}
        .light{background:#0b1220;color:#e5e7eb;border-color:#111827}
        .muted{color:#9ca3af}
    }
    @media (max-width:600px){.glow{width:92px;height:92px;border-radius:16px}.glow svg{width:48px;height:48px}.code{font-size:52px}h1{font-size:24px}}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="ribbon"></div>
        <div class="flex">
            <div class="glow" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 9v4m0 4h.01M4.93 4.93a10 10 0 1 1 14.14 14.14A10 10 0 0 1 4.93 4.93Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </div>
            <div style="flex:1 1 460px">
                <p class="code">500</p>
                <h1>Terjadi Kesalahan pada Server</h1>
                <p class="muted">Maaf, terjadi masalah tak terduga. Silakan coba lagi beberapa saat.</p>
                <div class="actions">
                    <button class="btn light" onclick="location.reload()">Coba Lagi</button>
                    <a class="btn primary" href="<?= htmlspecialchars($home) ?>">Ke Beranda</a>
                    <a class="btn light" href="mailto:xnuversh1kar4@gmail.com?subject=Error%20500%20<?= urlencode($errorId) ?>">Laporkan</a>
                </div>
                <div class="chip">
                    Error ID: <strong><?= htmlspecialchars($errorId) ?></strong>
                    <span id="cd" style="margin-left:8px">Redirect: <?= (int)$redirectAfter ?>s</span>
                </div>
            </div>
        </div>
        <p class="muted" style="margin:12px 2px 0">Path: <?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?></p>
    </div>
</div>
<script>
    // Redirect otomatis ke beranda setelah hitungan mundur
    (function(){
        var s = <?= (int)$redirectAfter ?>, el = document.getElementById('cd');
        var t = setInterval(function(){
            s--; if (s<=0){ clearInterval(t); location.href = <?= json_encode($home) ?>; }
            if (el) el.textContent = 'Redirect: ' + s + 's';
        }, 1000);
    })();
</script>
</body>
</html>