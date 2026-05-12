<?php
require_once '../../config/app.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, full_name, username, password, role, status FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $conn->close();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            redirect(APP_URL . '/index.php');
        } else {
            $error = 'Invalid credentials or account inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &mdash; <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #1a1a2e;
    --accent:  #e94560;
    --accent2: #0f3460;
    --surface: #16213e;
    --text:    #e0e0e0;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh;
    background: var(--primary);
    font-family: 'DM Sans', sans-serif;
    display: flex; align-items: center; justify-content: center;
    background-image: radial-gradient(ellipse at 20% 50%, #0f3460 0%, transparent 60%),
                      radial-gradient(ellipse at 80% 20%, #e9456020 0%, transparent 50%);
  }
  .login-card {
    background: var(--surface);
    border: 1px solid #ffffff12;
    border-radius: 20px;
    padding: 48px 40px;
    width: 100%; max-width: 420px;
    box-shadow: 0 40px 80px #00000060;
  }
  .brand-logo { font-family: 'Syne', sans-serif; font-size: 1.6rem; font-weight: 800; color: #fff; }
  .brand-logo span { color: var(--accent); }
  .brand-sub { color: #888; font-size: .85rem; margin-top: 4px; }
  .form-label { color: var(--text); font-size: .85rem; font-weight: 500; }
  .form-control {
    background: #ffffff0d; border: 1px solid #ffffff1a;
    color: #fff; border-radius: 10px; padding: 12px 16px;
  }
  .form-control:focus { background: #ffffff14; border-color: var(--accent); color: #fff; box-shadow: 0 0 0 3px #e9456030; }
  .form-control::placeholder { color: #555; }
  .input-group-text { background: #ffffff0d; border: 1px solid #ffffff1a; color: #888; border-radius: 0 10px 10px 0; }
  .btn-login {
    background: var(--accent); border: none; color: #fff;
    border-radius: 10px; padding: 12px; font-weight: 600;
    letter-spacing: .5px; font-size: .95rem; width: 100%;
    transition: all .2s;
  }
  .btn-login:hover { background: #c73652; transform: translateY(-1px); box-shadow: 0 8px 20px #e9456040; }
  .divider { border-color: #ffffff12; }
  .role-hint { background: #ffffff08; border-radius: 10px; padding: 14px 16px; }
  .role-hint code { background: #ffffff14; padding: 2px 6px; border-radius: 4px; color: var(--accent); font-size: .8rem; }
</style>
</head>
<body>
<div class="login-card">
  <div class="mb-4">
    <div class="brand-logo"><i class="bi bi-bag-check-fill me-2"></i>POS<span>Pro</span></div>
    <div class="brand-sub">Point of Sales &amp; Inventory System</div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger border-0 rounded-3 py-2" style="background:#e9456020;color:#ff6b80;font-size:.875rem;">
      <i class="bi bi-exclamation-circle me-1"></i><?= $error ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <div class="input-group">
        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter password" required style="border-radius:10px 0 0 10px;">
        <span class="input-group-text" style="cursor:pointer;" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></span>
      </div>
    </div>
    <button type="submit" class="btn btn-login">
      <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
    </button>
  </form>

  <hr class="divider my-4">
</div>
<script>
function togglePwd() {
  const f = document.getElementById('passwordField');
  const i = document.getElementById('eyeIcon');
  if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>