<?php
require_once '../../config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Unauthorized — <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body { background:#1a1a2e; color:#e0e0e0; font-family:'DM Sans',sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .err-box { text-align:center; }
  .err-code { font-size:6rem; font-weight:900; color:#e94560; line-height:1; }
</style>
</head>
<body>
<div class="err-box">
  <div class="err-code">403</div>
  <h3>Access Denied</h3>
  <p style="color:#666;">You don't have permission to view this page.</p>
  <a href="<?= APP_URL ?>/index.php" class="btn mt-3" style="background:#e94560;color:#fff;border-radius:10px;padding:10px 28px;">Go to Dashboard</a>
</div>
</body>
</html>