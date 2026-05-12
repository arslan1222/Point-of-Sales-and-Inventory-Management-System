<?php
require_once '../../config/app.php';
requireRole('admin');

$conn = getDBConnection();
$errors = [];

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = sanitize($_POST['full_name'] ?? '');
        $username  = sanitize($_POST['username'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = in_array($_POST['role'],['admin','manager','cashier']) ? $_POST['role'] : 'cashier';

        if (!$full_name) $errors[] = 'Full name required.';
        if (!$username)  $errors[] = 'Username required.';
        if (!$email)     $errors[] = 'Email required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

        if (empty($errors)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $full_name,$username,$email,$hashed,$role);
            if ($stmt->execute()) {
                setFlash('success', "User '$username' created.");
                redirect(APP_URL.'/modules/users/index.php');
            } else {
                $errors[] = 'Username or email already exists.';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $_SESSION['user_id']) {
            $conn->query("UPDATE users SET status=IF(status='active','inactive','active') WHERE id=$id");
            setFlash('success','User status toggled.');
        }
        redirect(APP_URL.'/modules/users/index.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id=$id");
            setFlash('success','User deleted.');
        }
        redirect(APP_URL.'/modules/users/index.php');
    } elseif ($action === 'reset_password') {
        $id = (int)($_POST['id'] ?? 0);
        $np = $_POST['new_password'] ?? '';
        if ($id && strlen($np) >= 6) {
            $hashed = password_hash($np, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed' WHERE id=$id");
            setFlash('success','Password reset successfully.');
        } else {
            setFlash('error','Password must be at least 6 characters.');
        }
        redirect(APP_URL.'/modules/users/index.php');
    }
}

$users = $conn->query("SELECT * FROM users ORDER BY role ASC, full_name ASC");
$conn->close();

$pageTitle = 'User Management';
require_once '../../includes/header.php';
?>

<div class="row g-4">
  <!-- Add User Form -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus me-2"></i>Add New User</div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger border-0 rounded-3 mb-3 py-2" style="font-size:.85rem;">
            <?php foreach($errors as $e): ?><?= $e ?><br><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password (min 6 chars)</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="cashier">Cashier</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="btn btn-accent w-100"><i class="bi bi-plus-lg me-2"></i>Create User</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Users List -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-people me-2"></i>System Users (<?= $users->num_rows ?>)</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>User</th><th>Username</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php while($u=$users->fetch_assoc()): ?>
          <?php $roleColors = ['admin'=>['#e94560','rgba(233,69,96,0.1)'],'manager'=>['#5bc0de','rgba(91,192,222,0.1)'],'cashier'=>['#2ecc71','rgba(46,204,113,0.1)']]; $rc=$roleColors[$u['role']]; ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-3">
                <span style="width:36px;height:36px;border-radius:50%;background:<?= $rc[1] ?>;color:<?= $rc[0] ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;">
                  <?= strtoupper(substr($u['full_name'],0,1)) ?>
                </span>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['full_name']) ?></div>
                  <div style="font-size:.75rem;color:#666;"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td><code style="color:#888;"><?= htmlspecialchars($u['username']) ?></code></td>
            <td><span class="badge" style="color:<?= $rc[0] ?>;background:<?= $rc[1] ?>;"><?= ucfirst($u['role']) ?></span></td>
            <td>
              <span class="badge" style="<?= $u['status']==='active'?'color:#2ecc71;background:rgba(46,204,113,0.1);':'color:#888;background:rgba(255,255,255,0.05);' ?>"><?= ucfirst($u['status']) ?></span>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end gap-1">
                <!-- Toggle Status -->
                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#aaa;border-radius:8px;" title="Toggle Status"><i class="bi bi-toggle-on"></i></button>
                </form>
                <!-- Reset Password -->
                <button class="btn btn-sm" style="background:rgba(243,156,18,0.1);color:#f39c12;border-radius:8px;" onclick="openPwdReset(<?= $u['id'] ?>, '<?= addslashes($u['username']) ?>')" title="Reset Password"><i class="bi bi-key"></i></button>
                <!-- Delete -->
                <form method="POST" style="display:inline;" id="delu<?= $u['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="button" class="btn btn-sm" style="background:rgba(231,76,60,0.1);color:#e74c3c;border-radius:8px;" onclick="confirmDelete('Delete user &quot;<?= addslashes($u['username']) ?>&quot;?','delu<?= $u['id'] ?>')"><i class="bi bi-trash"></i></button>
                </form>
                <?php else: ?>
                <span style="color:#555;font-size:.8rem;">Current user</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Password Reset Modal -->
<div class="modal fade" id="pwdModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password — <span id="pwdUsername"></span></h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="id" id="pwdUserId">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-accent btn-sm"><i class="bi bi-check me-1"></i>Reset</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openPwdReset(id, username) {
  document.getElementById('pwdUserId').value = id;
  document.getElementById('pwdUsername').textContent = username;
  new bootstrap.Modal(document.getElementById('pwdModal')).show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>